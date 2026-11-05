# Skema Database RentalPro

Semua uang disimpan sebagai **integer rupiah** (tanpa desimal/float). Semua waktu disimpan **UTC**
dan ditampilkan dalam **WIB** (API mengirim ISO-8601 dengan offset `+07:00`).
Data bisnis memakai **soft delete** (`deleted_at`).

```
users ─┬─< rentals (created_by, updated_by, blacklist_override_by)
       └─< rental_payments (received_by)

cars ──┬─< car_photos
       ├─< rentals >── customers
       ├─< maintenance_records
       ├─< expenses (car_id NULL = pengeluaran umum)
       └─< booking_requests

rentals ─┬─< rental_payments
         ├─< rental_fees
         ├─< rental_inspections (maks. 1 serah_terima + 1 kembali)
         ├── drivers (opsional, untuk "Dengan Driver")
         └── booking_requests (opsional, hasil konversi; unik)
```

## Tabel inti

| Tabel | Kolom penting | Catatan |
|---|---|---|
| `users` | `email` (unik), `phone`, `is_active`, `last_login_at` | Role/permission via Spatie (`roles`, `permissions`, `model_has_roles`, ...). Nonaktif = tidak bisa login & token lama ditolak. |
| `settings` | `key` (unik), `value` (JSON), `type`, `group`, `is_public` | Tarif/denda/buffer dibaca dari sini, bukan konstanta. Hanya owner yang bisa mengubah. |
| `holidays` | `date` (unik), `name` | Untuk tarif hari libur (opsional). |
| `document_sequences` | `prefix` + `year` (unik), `last_number` | Penomoran nota atomik `TRX-2026-0001` dengan `SELECT ... FOR UPDATE`, reset per tahun. |
| `cars` | `plate_number` (unik), `rate_self_drive`, `rate_with_driver` (total termasuk driver), `weekend_rate_*`, `default_deposit`, `odometer`, `status` (`tersedia/disewa/servis/nonaktif`), `tax_due_date`, `stnk_due_date`, `next_service_date/odometer`, `slug`, `is_published`, `features` | Index `status`, `(is_published, status)`. Plat & odometer tidak pernah keluar ke Public API. |
| `car_photos` | `car_id`, `path`, `is_cover`, `sort_order` | Foto marketing (disk publik). |
| `customers` | `phone` (+62), `nik_encrypted`, `nik_hash` (unik), `nik_last4`, `sim_number`, `sim_expires_at`, `address`, `emergency_contact_*`, `ktp_photo_path`, `sim_photo_path`, `risk_status`, `risk_note` | NIK: ciphertext (encrypted cast) + HMAC untuk pencarian/keunikan + 4 digit untuk mask `•••• •••• •••• 0009`. Foto dokumen di disk **privat**. |
| `drivers` | `phone`, `license_number`, `license_expires_at`, `daily_fee`, `is_active` | Penugasan lewat `rentals.driver_id`. |
| `rentals` | `code` (unik), `customer_id`, `car_id`, `driver_id`, `service_type`, `status` (`booking/aktif/selesai/batal`), `start_at`, `planned_end_at`, `picked_up_at`, `returned_at`, snapshot harga (`daily_rate`, `billed_days`, `rental_amount`, `late_fee`, `late_extra_days`, `extra_fees_total`, `discount`, `total_amount`), agregat (`paid_amount`, `deposit_required`, `deposit_held`), `blacklist_override_*` | **Terlambat tidak disimpan**: dihitung dari `status = aktif AND now > planned_end_at`. Index `(car_id, status, start_at, planned_end_at)` dan `(driver_id, ...)` untuk cek bentrok jadwal. FK ke customer/car `restrict` (riwayat tidak boleh hilang). |
| `rental_payments` | `type`, `method` (`tunai/transfer/qris`), `amount` (selalu positif), `paid_at`, `reference_number`, `proof_path` (privat) | Lihat tabel jenis pembayaran di bawah. Index `paid_at` untuk laporan per tanggal bayar. |
| `rental_inspections` | `type` (`serah_terima/kembali`), `odometer`, `fuel_level` (0-100%), `checklist` (JSON), `photos` (JSON, privat) | Unik per `(rental_id, type)`. |
| `rental_fees` | `type` (`bbm/kebersihan/kelebihan_km/kerusakan/lainnya`), `amount`, `reason` (wajib) | Biaya tambahan; jumlahnya disalin ke `rentals.extra_fees_total`. |
| `maintenance_records` | `car_id`, `rental_id` (opsional), `type`, `performed_at`, `odometer`, `cost`, `next_due_date`, `next_due_odometer` | Riwayat servis, pajak, STNK; sumber pengingat dashboard dan laporan biaya servis. |
| `expenses` | `car_id` (opsional), `category`, `title`, `spent_at` (tanggal WIB), `amount`, `method`, `vendor`, `proof_path` (disk privat), `created_by` | Pengeluaran operasional; sumber laporan Laba per mobil. Soft delete, tercatat di activity log. |
| `booking_requests` | `name`, `phone`, `car_id`, `start_at`, `end_at`, `service_type`, `status` (`baru/dihubungi/dikonfirmasi/ditolak`), `ip_address` | Dari form landing. Konversi ke sewa: `rentals.booking_request_id`. |
| `activity_log` | `log_name`, `subject`, `causer`, `properties` | Spatie activitylog: perubahan denda, diskon, harga sewa, status risiko, pengaturan, pembayaran. NIK tidak pernah dicatat. |

## Jenis pembayaran (`rental_payments.type`)

| Type | Efek ke "pembayaran diterima" (tagihan) | Efek ke saldo deposit |
|---|---|---|
| `dp`, `pelunasan` | + | 0 |
| `refund` | − | 0 |
| `deposit` | 0 | + |
| `deposit_kembali` | 0 | − (dikembalikan ke pelanggan) |
| `deposit_potong` | + (menutup denda/biaya) | − |

Deposit adalah **dana titipan**: tidak ikut menentukan status "Lunas". Saat Kembali, sisa tagihan
(mis. denda) bisa dipotong dari deposit (`deposit_potong`), sisanya dikembalikan (`deposit_kembali`).
Bila deposit tidak cukup, kekurangan tetap menjadi piutang.

## Pengaturan default (`settings`)

| Key | Default | Arti |
|---|---|---|
| `booking_buffer_minutes` | 120 | Jeda minimal antar sewa pada mobil/driver yang sama |
| `grace_period_minutes` | 120 | Masa toleransi untuk pembulatan durasi & keterlambatan |
| `late_fee_per_hour` | 50000 | Denda per jam (jam berjalan dihitung penuh, sejak jam kembali rencana) |
| `late_fee_cap_days` | 1 | Denda per blok 24 jam maksimal = tarif harian × nilai ini |
| `auto_flag_late_customer` | true | Pelanggan terlambat otomatis naik ke "Perhatian" |
| `weekend_rate_enabled` | false | Aktifkan tarif akhir pekan/libur |
| `weekend_days` | [6, 7] | Sabtu & Minggu (ISO-8601) |
| `default_deposit` | 0 | Deposit default bila mobil tidak punya nilai sendiri |
| `business_*`, `invoice_footer` | n/a | Identitas usaha untuk nota/email |

Contoh rumus denda (tarif Rp 550.000, rencana kembali 10.00, toleransi 2 jam):

| Kembali | Denda |
|---|---|
| 11.30 | Rp 0 (dalam toleransi) |
| 13.10 | 4 jam × Rp 50.000 = Rp 200.000 |
| 22.00 | 12 jam = Rp 600.000 → dibatasi Rp 550.000 |
| besok 13.00 | +1 hari sewa (Rp 550.000) + 3 jam denda (Rp 150.000) |
