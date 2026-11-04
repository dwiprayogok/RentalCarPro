<?php

namespace App\Support;

use App\Rules\IndonesianPhone;

/**
 * Definisi & nilai default pengaturan sistem. Seeder membuat baris yang belum ada
 * tanpa menimpa nilai yang sudah diubah owner.
 */
final class SettingDefinitions
{
    /**
     * @return list<array{group: string, key: string, value: mixed, type: string, label: string, description: string, is_public?: bool}>
     */
    public static function all(): array
    {
        return [
            // Jadwal
            ['group' => 'jadwal', 'key' => 'booking_buffer_minutes', 'value' => 120, 'type' => 'int',
                'label' => 'Buffer antar sewa (menit)',
                'description' => 'Jeda minimal antara jam kembali satu sewa dan jam mulai sewa berikutnya pada mobil/driver yang sama.'],

            // Durasi & tarif
            ['group' => 'tarif', 'key' => 'grace_period_minutes', 'value' => 120, 'type' => 'int',
                'label' => 'Masa toleransi (menit)',
                'description' => 'Kelebihan waktu yang tidak dihitung, baik untuk pembulatan durasi maupun keterlambatan.'],
            ['group' => 'tarif', 'key' => 'weekend_rate_enabled', 'value' => false, 'type' => 'bool',
                'label' => 'Aktifkan tarif akhir pekan/libur',
                'description' => 'Jika aktif, hari yang jatuh di akhir pekan atau tanggal libur memakai tarif khusus mobil (bila diisi).'],
            ['group' => 'tarif', 'key' => 'weekend_days', 'value' => [6, 7], 'type' => 'json',
                'label' => 'Hari akhir pekan',
                'description' => 'ISO-8601: 1 = Senin ... 6 = Sabtu, 7 = Minggu.'],

            // Denda
            ['group' => 'denda', 'key' => 'late_fee_per_hour', 'value' => 50000, 'type' => 'int',
                'label' => 'Denda keterlambatan per jam (Rp)',
                'description' => 'Dihitung per jam yang sudah berjalan sejak jam kembali rencana, setelah masa toleransi terlewati.'],
            ['group' => 'denda', 'key' => 'late_fee_cap_days', 'value' => 1, 'type' => 'int',
                'label' => 'Batas denda per 24 jam (x tarif harian)',
                'description' => 'Denda dalam satu blok 24 jam maksimal sebesar tarif harian dikali nilai ini. Lewat 24 jam dihitung sebagai hari sewa tambahan.'],
            ['group' => 'denda', 'key' => 'auto_flag_late_customer', 'value' => true, 'type' => 'bool',
                'label' => 'Tandai pelanggan terlambat sebagai "Perhatian"',
                'description' => 'Pelanggan berstatus Lancar otomatis naik ke Perhatian bila mengembalikan mobil terlambat (melewati toleransi).'],

            // Pengingat dashboard
            ['group' => 'pengingat', 'key' => 'reminder_days', 'value' => 30, 'type' => 'int',
                'label' => 'Pengingat jatuh tempo (hari)',
                'description' => 'Pajak, STNK, dan servis yang jatuh tempo dalam sekian hari tampil di dashboard.'],
            ['group' => 'pengingat', 'key' => 'reminder_km', 'value' => 1000, 'type' => 'int',
                'label' => 'Pengingat servis (km)',
                'description' => 'Servis berdasarkan odometer tampil bila sisa jaraknya di bawah nilai ini.'],

            // Deposit
            ['group' => 'deposit', 'key' => 'default_deposit', 'value' => 0, 'type' => 'int',
                'label' => 'Deposit default (Rp)',
                'description' => 'Dipakai bila mobil tidak memiliki nilai deposit sendiri.'],

            // Usaha
            ['group' => 'usaha', 'key' => 'business_name', 'value' => 'RentalPro', 'type' => 'string',
                'label' => 'Nama usaha', 'description' => 'Tampil di nota dan email.', 'is_public' => true],
            ['group' => 'usaha', 'key' => 'business_tagline', 'value' => 'Manajemen Rental Mobil', 'type' => 'string',
                'label' => 'Tagline', 'description' => 'Tampil di bawah nama usaha pada nota.', 'is_public' => true],
            ['group' => 'usaha', 'key' => 'business_address', 'value' => '', 'type' => 'string',
                'label' => 'Alamat usaha', 'description' => '', 'is_public' => true],
            ['group' => 'usaha', 'key' => 'business_whatsapp', 'value' => '', 'type' => 'string',
                'label' => 'Nomor WhatsApp usaha', 'description' => 'Format +62.', 'is_public' => true],
            ['group' => 'usaha', 'key' => 'invoice_terms', 'value' => "Kendaraan wajib dikembalikan sesuai jadwal; keterlambatan dikenakan denda per jam sesuai ketentuan.\nKerusakan, kehilangan, dan pelanggaran lalu lintas selama masa sewa menjadi tanggung jawab penyewa.\nDeposit dikembalikan setelah kendaraan diperiksa, dikurangi tagihan yang belum dibayar.", 'type' => 'string',
                'label' => 'Syarat singkat di nota', 'description' => 'Satu syarat per baris. Tampil di nota & PDF.'],
            ['group' => 'usaha', 'key' => 'invoice_footer', 'value' => 'Terima kasih. Selamat melanjutkan perjalanan.', 'type' => 'string',
                'label' => 'Catatan kaki nota', 'description' => ''],
        ];
    }

    /**
     * Aturan validasi per key (dipakai SettingService::update). Batas dibuat longgar namun
     * mencegah salah ketik yang merusak perhitungan (mis. denda negatif, toleransi 3 hari).
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $rupiah = ['integer', 'min:0', 'max:100000000'];

        return [
            'booking_buffer_minutes' => ['integer', 'between:0,1440'],
            'grace_period_minutes' => ['integer', 'between:0,720'],
            'weekend_rate_enabled' => ['boolean'],
            'weekend_days' => ['array', 'max:7'],
            'weekend_days.*' => ['integer', 'between:1,7', 'distinct'],
            'late_fee_per_hour' => $rupiah,
            'late_fee_cap_days' => ['integer', 'between:1,7'],
            'auto_flag_late_customer' => ['boolean'],
            'reminder_days' => ['integer', 'between:1,365'],
            'reminder_km' => ['integer', 'between:0,20000'],
            'default_deposit' => $rupiah,
            'business_name' => ['string', 'min:2', 'max:80'],
            'business_tagline' => ['nullable', 'string', 'max:120'],
            'business_address' => ['nullable', 'string', 'max:300'],
            'business_whatsapp' => ['nullable', 'string', new IndonesianPhone],
            'invoice_terms' => ['nullable', 'string', 'max:1500'],
            'invoice_footer' => ['nullable', 'string', 'max:200'],
        ];
    }
}
