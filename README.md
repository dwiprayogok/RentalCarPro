# RentalPro

Aplikasi manajemen rental mobil untuk pasar Indonesia.

| Folder | Isi | Status |
|---|---|---|
| `backend/` | Laravel 13 (PHP 8.3+), REST API `/api/v1`, MySQL 8 | Phase 10: + import Excel/CSV (armada, pelanggan, driver) |
| `admin/` | Admin Web: React + TypeScript + Vite + Tailwind | Phase 10: + Import Data (template, pratinjau per baris) |
| `landing/` | Landing page publik (Next.js) | Ditunda |
| `docs/` | Dokumentasi skema & keputusan | [`docs/database.md`](docs/database.md) |

API yang sama dipakai Admin Web (cookie Sanctum) dan aplikasi mobile Flutter (token Bearer).

---

## Setup lokal

Prasyarat: PHP 8.3+ (ekstensi `pdo_mysql`, `mbstring`, `openssl`), Composer 2, Node 20+,
dan MySQL 8 (lewat Docker atau terinstal langsung).

### 1. Jalankan MySQL 8 + Mailpit (Docker)

```bash
docker compose up -d
```

Ini membuat database `rentalpro` dan `rentalpro_test` (user `rentalpro` / `secret`).
Mailpit menampung email yang dikirim saat development: http://localhost:8025.

> Tanpa Docker: buat database `rentalpro` dan `rentalpro_test` di MySQL 8 Anda,
> lalu sesuaikan `DB_*` di `backend/.env`.

### 2. Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve            # http://localhost:8000
```

Di terminal lain, jalankan worker antrean (email reset kata sandi dikirim lewat antrean):

```bash
cd backend
php artisan queue:work
```

Supaya foto armada (disk publik) bisa diakses browser, buat symlink sekali:

```bash
cd backend
php artisan storage:link
```

### 3. Admin Web

```bash
cd admin
cp .env.example .env         # VITE_API_URL=http://localhost:8000
npm install
npm run dev                  # http://localhost:5173
```

Buka http://localhost:5173 (pakai `localhost`, bukan `127.0.0.1`, agar cookie sesi cocok dengan `SESSION_DOMAIN`).

### Akun demo (hanya dibuat di environment non-produksi)

| Role | Email | Kata sandi |
|---|---|---|
| owner | `owner@rentalpro.test` | `password123` |
| admin | `admin@rentalpro.test` | `password123` |
| staff | `staff@rentalpro.test` | `password123` |

---

## Setup Gmail SMTP (reset kata sandi)

Gmail tidak mengizinkan login SMTP memakai kata sandi biasa. Anda perlu **App Password**.

1. Buka https://myaccount.google.com/security dengan akun Gmail yang akan dipakai sebagai pengirim.
2. Aktifkan **Verifikasi 2 Langkah** (2-Step Verification). App Password hanya tersedia jika ini aktif.
3. Buka https://myaccount.google.com/apppasswords (atau cari "App passwords" di kolom pencarian akun Google).
4. Isi nama aplikasi, mis. `RentalPro`, lalu klik **Create**.
5. Salin 16 karakter yang muncul (contoh `abcd efgh ijkl mnop`). Kode ini hanya ditampilkan sekali.
6. Isi `backend/.env`:

   ```dotenv
   MAIL_MAILER=smtp
   MAIL_SCHEME=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=akunanda@gmail.com
   MAIL_PASSWORD="abcd efgh ijkl mnop"
   MAIL_FROM_ADDRESS=akunanda@gmail.com
   MAIL_FROM_NAME="RentalPro"
   FRONTEND_URL=http://localhost:5173
   ```

7. Terapkan dan uji:

   ```bash
   php artisan config:clear
   php artisan queue:work
   ```

   Lalu kirim permintaan lupa kata sandi:

   ```bash
   curl -X POST http://localhost:8000/api/v1/auth/forgot-password \
     -H "Accept: application/json" -H "Content-Type: application/json" \
     -d '{"email":"owner@rentalpro.test"}'
   ```

   Ganti email owner demo dengan email asli Anda terlebih dahulu agar email benar-benar diterima.

Catatan:
- `MAIL_FROM_ADDRESS` harus sama dengan `MAIL_USERNAME` (atau alias "Send mail as" yang sudah diverifikasi di Gmail). Jika berbeda, Gmail akan menimpanya.
- Port 465 juga bisa dipakai: `MAIL_PORT=465` dan `MAIL_SCHEME=smtps`.
- Batas kirim Gmail pribadi sekitar 500 email/hari. Untuk produksi bervolume besar, pertimbangkan Google Workspace atau layanan email transaksional.
- Jika App Password dicabut atau kata sandi Google diganti, buat App Password baru.
- **Jangan pernah** commit `.env`. Hanya `.env.example` yang masuk repo.

Tautan di email mengarah ke `FRONTEND_URL/reset-password?token=...&email=...` (halaman di Admin Web),
yang lalu memanggil `POST /api/v1/auth/reset-password`. Tautan berlaku 60 menit.

---

## Endpoint

| Method | Path | Akses | Keterangan |
|---|---|---|---|
| GET | `/sanctum/csrf-cookie` | publik | Wajib dipanggil Admin Web sebelum login |
| POST | `/api/v1/auth/login` | publik, 5×/menit | Login Admin Web (cookie sesi) |
| POST | `/api/v1/auth/token` | publik, 5×/menit | Login mobile, mengembalikan token Bearer (`device_name` wajib) |
| POST | `/api/v1/auth/forgot-password` | publik, 3×/menit | Kirim email reset (balasan selalu sama) |
| POST | `/api/v1/auth/reset-password` | publik, 3×/menit | `token`, `email`, `password`, `password_confirmation` |
| GET | `/api/v1/auth/me` | login | Profil + `roles` + `permissions` |
| POST | `/api/v1/auth/logout` | login | Mengakhiri sesi / mencabut token |
| GET | `/api/v1/settings` | `setting.view` | Semua role (dibutuhkan form) |
| PUT | `/api/v1/settings` | `setting.update` | Owner saja. Body: `{"settings": {"late_fee_per_hour": 60000}}`; batas nilai per key di `SettingDefinitions::rules()` |
| GET/POST/DELETE | `/api/v1/holidays` | `setting.view` / `setting.update` | Tanggal libur untuk tarif akhir pekan/libur |
| GET | `/api/v1/roles` | `user.manage` | Role + daftar permission + jumlah pengguna |
| GET/POST/PUT | `/api/v1/users` | `user.manage` | Owner saja; tidak bisa mengubah role sendiri, minimal 1 owner aktif |
| PATCH | `/api/v1/users/{id}/status` | `user.manage` | Aktif/nonaktif; nonaktif mencabut semua sesi & token |
| POST | `/api/v1/users/{id}/force-password-reset` | `user.manage` | Sandi lama tidak berlaku, sesi dicabut, email reset dikirim |
| GET | `/api/v1/activity-log` | `activity.view` | Filter `module`, `event`, `causer_id`, `from`, `to`, `search` |
| GET/POST/PUT/DELETE | `/api/v1/cars` | `car.*` | Filter `search`, `status`, `transmission`, `is_published`, `sort`; `meta.counts` per status. Status `disewa` tidak bisa diset manual |
| POST/PATCH/DELETE | `/api/v1/cars/{car}/photos[/{photo}]` | `car.update` | Upload `photos[]` (JPG/PNG/WebP ≤ 5 MB, maks. 12), set sampul, hapus |
| GET/POST/PUT/DELETE | `/api/v1/customers` | `customer.*` | Cari nama/HP/NIK 16 digit/4 digit terakhir; filter `risk_status`; `meta.counts` |
| GET | `/api/v1/customers/{id}/rentals` | `customer.view` + `rental.view` | Riwayat sewa |
| POST/DELETE | `/api/v1/customers/{id}/documents[/{ktp\|sim}]` | `customer.update` + `customer.view_documents` | Foto KTP/SIM ke disk **privat** |
| GET | `/api/v1/customers/{id}/documents/{type}/file` | signed URL | Berlaku 10 menit, diberikan di `documents.*.url` |
| GET/POST/PUT/DELETE | `/api/v1/drivers` | `driver.*` | Filter `search`, `is_active` |
| GET | `/api/v1/imports/{armada,pelanggan,driver}/template` | `data.import` + izin tambah | Template `.xlsx` (sheet Data dengan dropdown + sheet Petunjuk) |
| POST | `/api/v1/imports/{type}/preview` | `data.import` + izin tambah | Unggah `.xlsx/.xls/.csv` (maks. 5 MB, 1.000 baris), hasil per baris: `baru` / `lewati` / `error` + token 30 menit |
| POST | `/api/v1/imports/{type}/commit` | `data.import` + izin tambah | Simpan baris valid (validasi ulang) dalam satu transaksi |
| GET/POST/PUT/DELETE | `/api/v1/car-owners` | `car_owner.*` | Pemilik mobil titipan + ketentuan bagi hasil (`share_basis` kotor/bersih, `share_percent`). Mobil dihubungkan lewat `cars.owner_id` |
| GET | `/api/v1/car-owners/{owner}/settlement-preview?from=&to=` | `settlement.create` | Pratinjau bagi hasil per mobil (tidak disimpan) |
| GET/POST | `/api/v1/settlements` | `settlement.view` / `.create` | Setoran per periode (snapshot, nomor `SET-YYYY-NNNN`); `meta.unpaid` |
| POST | `/api/v1/settlements/{id}/pay` | `settlement.pay` | Catat pembayaran ke pemilik (multipart, bukti privat) |
| DELETE | `/api/v1/settlements/{id}` | `settlement.delete` | Batalkan setoran yang belum dibayar |
| GET/POST | `/api/v1/settlements/{id}/pdf`, `/print-url` | `settlement.view` | Slip PDF; link WhatsApp ke pemilik (PDF signed 7 hari) |
| GET/POST/PUT/DELETE | `/api/v1/expenses` | `expense.*` | Pengeluaran operasional (multipart, bukti privat). Filter `car_id` (angka atau `umum`), `category`, `from`, `to`, `search`; `meta.summary` per kategori. Ubah dengan bukti: `POST` + `_method=PUT` |
| GET/POST/PUT/DELETE | `/api/v1/maintenance` | `maintenance.*` | Filter `car_id`, `type`, `from`, `to`; `meta.total_cost`. Otomatis memperbarui odometer & jatuh tempo mobil |

| GET | `/api/v1/rentals` | `rental.view` | `view=semua\|booking\|aktif\|terlambat\|selesai\|batal\|belum_lunas`, `search`; `meta.counts` |
| GET | `/api/v1/rentals/export` | `rental.view` | CSV (`;`, UTF-8 BOM) dengan filter yang sama |
| POST | `/api/v1/rentals/quote` | `rental.create` | Ringkasan biaya + bentrok + peringatan, tanpa menyimpan |
| POST | `/api/v1/rentals` | `rental.create` | Sewa Baru: booking, atau `start_now: true` + `handover` (langsung Aktif); `payments[]` awal opsional |
| GET | `/api/v1/rentals/{id}` | `rental.view` | Detail + tagihan (`billing`), pembayaran, inspeksi |
| POST | `/api/v1/rentals/{id}/handover` | `rental.handover` | Serah terima booking → Aktif |
| POST | `/api/v1/rentals/{id}/cancel` | `rental.cancel` | Hanya untuk Booking, alasan wajib |
| POST/DELETE | `/api/v1/rentals/{id}/payments[/{payment}]` | `payment.create` / `payment.delete` | DP, pelunasan, deposit; bukti bayar privat |
| POST | `/api/v1/rentals/{id}/inspections/{type}/photos` | `rental.handover` | Foto kondisi (privat, signed URL) |
| GET | `/api/v1/cars/{id}/availability?from=&to=` | `car.view` | Kalender ketersediaan (WIB) |

| POST | `/api/v1/rentals/{id}/return/preview` | `rental.return` | Ringkasan akhir (denda, biaya, deposit, sisa) tanpa menyimpan |
| POST | `/api/v1/rentals/{id}/return` | `rental.return` | Kembali: inspeksi, biaya tambahan, denda, pembayaran, deposit, mobil, sewa selesai (satu transaksi) |
| GET | `/api/v1/rentals/{id}/invoice` | `rental.view` | Data nota + `whatsapp_url` + `pdf_share_url` (signed, 7 hari) |
| GET | `/api/v1/rentals/{id}/invoice/pdf` | `rental.view` | PDF nota (dompdf, A4) |
| POST | `/api/v1/rentals/{id}/invoice/print-url` | `rental.view` | Signed URL PDF 2 menit untuk tombol Cetak |
| GET | `/api/v1/dashboard` | `dashboard.view` | Banner dinamis, KPI, arus kas 7 hari, pengembalian prioritas, armada pilihan, transaksi terbaru, pengingat pajak/STNK/servis |
| GET | `/api/v1/reports/{payments,receivables,revenue-by-car,profit,utilization,maintenance}` | `report.view` | Filter `from`, `to` (WIB), `method`, `car_id`; agregasi di database |
| GET | `/api/v1/reports/{type}/export` | `report.view` | CSV (`;`, UTF-8 BOM) |
| GET/PATCH | `/api/v1/booking-requests[/{id}]` | `booking_request.*` | Status `baru/dihubungi/ditolak`; "Jadikan Sewa" = `POST /rentals` dengan `booking_request_id` |

Aturan validasi penting:
- Nomor HP diterima sebagai `08…`, `62…`, atau `+62…` dan disimpan sebagai `+62…`.
- NIK wajib 16 digit, unik, disimpan terenkripsi, dan API hanya mengirim bentuk ter-mask.
- `for_self_drive: true` mewajibkan SIM (nomor + masa berlaku yang belum lewat), alamat, dan kontak darurat.
- Status risiko Perhatian/Blacklist wajib disertai `risk_note`, dan hanya bisa diubah oleh owner/admin (`customer.change_risk`).
- Plat nomor dinormalisasi kapital, unik, dan harus berformat `F 1576 FGH`. Tarif dengan driver harus ≥ tarif lepas kunci.
- Mobil, pelanggan, atau driver yang masih punya sewa Booking/Aktif tidak bisa dihapus.

Aturan transaksi (Phase 3):
- **Durasi:** `hari = max(1, ceil((menit − toleransi) / 1440))`. Dengan toleransi 2 jam: 26 jam = 1 hari, 26 jam 1 menit = 2 hari.
- **Tarif:** tarif per hari sesuai layanan. Tarif akhir pekan/libur dipakai per hari bila `weekend_rate_enabled` aktif dan mobil punya tarif khusus. Rincian per hari disimpan di `price_breakdown`.
- **Anti double-booking:** baris mobil dan driver dikunci (`lockForUpdate`) di dalam transaksi sebelum cek bentrok, dengan buffer `booking_buffer_minutes`. Sewa aktif yang terlambat tetap memblokir sampai mobil kembali. Ini diuji dengan beberapa proses PHP paralel (`ConcurrentBookingTest`).
- **Nomor nota:** `TRX-YYYY-NNNN`, atomik (`INSERT … ON DUPLICATE KEY UPDATE`), reset per tahun WIB, dan ikut di-rollback bila penyimpanan gagal.
- **Pelanggan:** Blacklist ditolak; pengecualian hanya oleh owner dengan alasan, dan tercatat di activity log. Layanan Lepas Kunci butuh data SIM, alamat, dan kontak darurat yang lengkap.
- **Pembayaran:** total terbayar dan sisa dihitung di server dari tabel pembayaran. Deposit terpisah dari status Lunas. Sewa yang terlambat menampilkan estimasi denda berjalan dan label "Lunas (belum termasuk denda)".

Aturan Kembali (Phase 4):
- **Denda:** dihitung dari jam kembali rencana. Terlambat ≤ toleransi → Rp 0. Tiap 24 jam penuh = 1 hari sewa tambahan, sisanya per jam berjalan × `late_fee_per_hour`, dibatasi tarif harian × `late_fee_cap_days`.
- **Total akhir** = sewa − diskon + denda + biaya tambahan. Setiap biaya tambahan wajib disertai alasan.
- **Deposit:** saran potongan = min(deposit ditahan, sisa tagihan) dan bisa diubah admin. Sisa deposit selalu dikembalikan. Kalau masih ada sisa tagihan, sewa tetap Selesai sebagai piutang.
- **Mobil:** odometer diperbarui. Status kembali ke `tersedia`, atau `servis` bila ada biaya kerusakan (otomatis membuat catatan perawatan); admin bisa mengubahnya.
- **Pelanggan:** yang terlambat melewati toleransi otomatis naik dari Lancar ke Perhatian (setting `auto_flag_late_customer`). Semua langkah tercatat di activity log.

Laporan (Phase 5):
- **Pendapatan diterima** = DP + pelunasan + potong deposit − refund, per tanggal bayar (WIB). Deposit titipan tidak dihitung.
- **Agregasi di database:** `SUM`/`COUNT`/`GROUP BY` per hari, metode, dan mobil. Utilisasi menghitung irisan waktu sewa dengan periode di SQL. Index khusus laporan ada di migration `add_report_indexes`.
- **Pengujian:** test membandingkan setiap angka dengan perhitungan ulang dari baris mentah memakai data `ReportDemoSeeder` (60 hari, deterministik). Jumlah query diuji tetap sama saat data dilipatgandakan.
- **Kinerja:** di MySQL dengan ~160 ribu pembayaran, laporan pembayaran memakai range scan index `paid_at` dan selesai dalam ±1 ms.

Pengeluaran & laba (Phase 8):
- **Pengeluaran** punya kategori (BBM, cuci, parkir/tol, asuransi, cicilan, gaji, sewa tempat, listrik/air/internet, pemasaran, lainnya). Bisa untuk satu mobil, atau umum (`car_id` kosong). Biaya servis tetap dicatat di Perawatan.
- **Bukti/struk** disimpan di disk privat dan hanya bisa dibuka lewat signed URL 10 menit. Mengganti bukti menghapus berkas lama. Hapus pengeluaran = soft delete, dan bukti tetap disimpan untuk audit.
- **Laba per mobil** = pendapatan diterima − biaya perawatan − pengeluaran mobil itu, dihitung per tanggal (WIB). **Laba bersih usaha** juga dikurangi pengeluaran umum. Saat difilter per mobil, pengeluaran umum tidak ikut.
- **Hak akses:** staf boleh melihat dan mencatat pengeluaran. Mengubah/menghapus hanya pemilik/admin. Laporan laba butuh `report.view`. Semua perubahan tercatat di Log Aktivitas (modul Pengeluaran).

Mobil titipan & bagi hasil (Phase 9):
- **Pemilik** punya ketentuan sendiri: **% dari pendapatan kotor** (biaya servis & pengeluaran ditanggung rental) atau **% dari laba bersih** (pendapatan − perawatan − pengeluaran mobil itu). Definisi angkanya sama dengan laporan Laba.
- **Setoran** dibuat per periode, dan angkanya di-*snapshot* (`lines` per mobil). Data yang berubah belakangan tidak mengubah setoran. Periode satu pemilik tidak boleh tumpang tindih (baris pemilik dikunci saat membuat setoran).
- **Pembulatan:** bagian pemilik = round(dasar × persen). Bila dasar negatif (rugi), bagian pemilik Rp 0 dan kerugian tidak dibawa ke periode berikutnya.
- **Setoran yang sudah dibayar** tidak bisa dibayar ulang atau dibatalkan, termasuk oleh owner. Aturan ini ditegakkan di service, karena owner lolos semua policy lewat `Gate::before`.
- **Hak akses:** pemilik & admin. Staf tidak punya akses. Pembuatan, pembayaran, dan pembatalan tercatat di Log Aktivitas.

Import Excel (Phase 10):
- **Validasi sama dengan form biasa:** setiap baris dijalankan lewat FormRequest yang sama (`SaveCarRequest`, `SaveCustomerRequest`, `SaveDriverRequest`), termasuk normalisasi dan pesan Bahasa Indonesia. Penyimpanan lewat service yang sama, jadi NIK tetap terenkripsi dan slug serta log aktivitas tetap jalan.
- **Konversi sel:**
  - tanggal Excel atau `dd/mm/yyyy`;
  - nominal notasi Indonesia (`Rp 1.250.000`, `500.000` = lima ratus ribu);
  - pilihan berdasarkan label (`Matic`, `Ya/Tidak`);
  - HP tanpa angka 0 di depan.
  NIK yang terbaca sebagai angka ditolak, karena Excel hanya menyimpan 15 digit.
- **Data yang sudah ada dilewati, tidak ditimpa:** plat (armada), NIK/HP (pelanggan), HP (driver). Baris ganda dalam satu file ditandai.
- **Pratinjau** disimpan di cache 30 menit dengan token yang terikat ke pengguna dan jenis data. Saat commit, setiap baris divalidasi ulang. NIK lengkap tidak pernah dikirim di pratinjau.
- **Hak akses:** permission `data.import` (pemilik & admin) plus izin tambah jenis datanya. Setiap import tercatat di Log Aktivitas (modul Import data).

Format error konsisten dalam Bahasa Indonesia: `{ "message": "...", "errors": { "field": ["..."] } }`
dengan status 401, 403, 404, 419, 422, atau 429.

## Role & permission

Katalog lengkap ada di `backend/app/Support/Permissions.php` (sumber tunggal untuk seeder, Policy, dan frontend).

- **owner**: semua permission, termasuk `setting.update`, `user.manage`, `rental.override_blacklist`, dan `landing.manage`.
- **admin**: semua permission kecuali empat di atas.
- **staff**: hanya catat sewa, serah terima, kembali, pembayaran masuk, pelanggan, dan laporan operasional. Tidak punya permission `*.delete`, ubah tarif/denda, refund, diskon, atau laporan keuangan penuh.

Setiap endpoint dijaga Policy/permission dan diuji oleh `tests/Feature/Authorization/PermissionMatrixTest.php`. Test ini memeriksa seluruh endpoint terhadap owner, admin, staff, dan pengguna tanpa role: yang tidak berhak harus 403, yang berhak tidak boleh 403. Test ini juga gagal kalau ada endpoint baru yang belum masuk matriks.

Setelah mengubah katalog, sinkronkan dengan:

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
```

## Perintah penting

```bash
php artisan test                         # test di MySQL (database rentalpro_test), termasuk uji race condition
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test   # alternatif cepat tanpa MySQL
php artisan migrate:fresh --seed         # reset database lokal + data demo
php artisan route:list --path=api        # daftar endpoint
./vendor/bin/pint                        # format kode PHP
```

## Tampilan Admin Web

| Armada | Pelanggan |
|---|---|
| ![Armada](docs/screenshots/armada.png) | ![Pelanggan](docs/screenshots/pelanggan.png) |
| ![Detail armada](docs/screenshots/armada-detail.png) | ![Form pelanggan](docs/screenshots/pelanggan-form.png) |
| ![Transaksi](docs/screenshots/transaksi.png) | ![Sewa Baru](docs/screenshots/sewa-baru.png) |
| ![Kalender ketersediaan](docs/screenshots/kalender.png) | ![Serah terima](docs/screenshots/serah-terima.png) |
| ![Dashboard](docs/screenshots/dashboard.png) | ![Kembali](docs/screenshots/kembali.png) |
| ![Nota](docs/screenshots/nota.png) | ![Nota PDF](docs/screenshots/nota-pdf.png) |
| ![Laporan](docs/screenshots/laporan.png) | ![Permintaan Booking](docs/screenshots/permintaan-booking.png) |
| ![Pengaturan](docs/screenshots/pengaturan.png) | ![Pengguna & Role](docs/screenshots/pengguna.png) |
| ![Log Aktivitas](docs/screenshots/log-aktivitas.png) | ![Pengeluaran](docs/screenshots/pengeluaran.png) |
| ![Laba per mobil](docs/screenshots/laporan-laba.png) | ![Mobil Titipan](docs/screenshots/mobil-titipan.png) |
| ![Pratinjau setoran](docs/screenshots/setoran-pratinjau.png) | ![Detail setoran](docs/screenshots/setoran-detail.png) |
| ![Pratinjau import](docs/screenshots/import-pratinjau.png) | ![Hasil import](docs/screenshots/import-hasil.png) |

## Konvensi

- Waktu: disimpan UTC (`app.timezone = UTC`, sesi MySQL `+00:00`), ditampilkan WIB (`APP_DISPLAY_TIMEZONE`). JSON API memakai ISO-8601 dengan offset, mis. `2026-10-21T15:41:00+07:00`.
- Uang: integer rupiah.
- Logika bisnis ada di `app/Services` (bukan controller). Validasi ada di FormRequest, akses dicek di Policy.
