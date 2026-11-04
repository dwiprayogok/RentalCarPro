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
            ['group' => 'usaha', 'key' => 'business_address', 'value' => 'Jl. Pajajaran No. 10, Bogor', 'type' => 'string',
                'label' => 'Alamat usaha', 'description' => '', 'is_public' => true],
            ['group' => 'usaha', 'key' => 'business_whatsapp', 'value' => '+6281200000000', 'type' => 'string',
                'label' => 'Nomor WhatsApp usaha', 'description' => 'Format +62.', 'is_public' => true],
            ['group' => 'usaha', 'key' => 'invoice_terms', 'value' => "Kendaraan wajib dikembalikan sesuai jadwal; keterlambatan dikenakan denda per jam sesuai ketentuan.\nKerusakan, kehilangan, dan pelanggaran lalu lintas selama masa sewa menjadi tanggung jawab penyewa.\nDeposit dikembalikan setelah kendaraan diperiksa, dikurangi tagihan yang belum dibayar.", 'type' => 'string',
                'label' => 'Syarat singkat di nota', 'description' => 'Satu syarat per baris. Tampil di nota & PDF.'],
            // Landing page (konten publik; diubah lewat Pengaturan Landing, butuh landing.manage)
            ['group' => 'landing', 'key' => 'landing_hero_title', 'value' => 'Sewa mobil bersih, nyaman, dan tepat waktu', 'type' => 'string',
                'label' => 'Judul hero', 'description' => 'Judul besar di bagian atas landing page.', 'is_public' => true],
            ['group' => 'landing', 'key' => 'landing_hero_subtitle', 'value' => 'Lepas kunci atau dengan driver. Cek ketersediaan, lihat tarif transparan, lalu pesan lewat WhatsApp dalam hitungan menit.', 'type' => 'string',
                'label' => 'Subjudul hero', 'description' => '', 'is_public' => true],
            ['group' => 'landing', 'key' => 'landing_city', 'value' => 'Bogor', 'type' => 'string',
                'label' => 'Kota layanan', 'description' => 'Dipakai di judul halaman & SEO.', 'is_public' => true],
            ['group' => 'landing', 'key' => 'landing_operating_hours', 'value' => 'Setiap hari, 07.00–21.00 WIB', 'type' => 'string',
                'label' => 'Jam operasional', 'description' => '', 'is_public' => true],
            ['group' => 'landing', 'key' => 'landing_maps_url', 'value' => '', 'type' => 'string',
                'label' => 'Tautan Google Maps', 'description' => 'URL lokasi kantor/garasi (https://maps.google.com/...).', 'is_public' => true],
            ['group' => 'landing', 'key' => 'landing_how_to', 'type' => 'json', 'is_public' => true,
                'label' => 'Cara sewa', 'description' => 'Langkah singkat, satu per baris.',
                'value' => ['Pilih mobil & tanggal, lalu cek ketersediaan.', 'Kirim permintaan sewa atau hubungi kami via WhatsApp.', 'Kirim foto KTP & SIM untuk verifikasi.', 'Bayar DP, ambil mobil atau tunggu diantar.']],
            ['group' => 'landing', 'key' => 'landing_terms', 'type' => 'json', 'is_public' => true,
                'label' => 'Syarat sewa', 'description' => 'Satu syarat per baris.',
                'value' => ['KTP asli & SIM A aktif (lepas kunci).', 'Deposit/jaminan sesuai jenis mobil, dikembalikan saat mobil kembali.', 'Durasi dihitung per 24 jam dengan toleransi 2 jam.', 'Keterlambatan dikenakan denda per jam.', 'Pemakaian di luar kota wajib diinformasikan saat pemesanan.']],
            ['group' => 'landing', 'key' => 'landing_faq', 'type' => 'json', 'is_public' => true,
                'label' => 'FAQ', 'description' => 'Daftar tanya-jawab.',
                'value' => [
                    ['q' => 'Apakah bisa sewa dengan driver?', 'a' => 'Bisa. Tarif dengan driver sudah termasuk jasa driver per hari.'],
                    ['q' => 'Bagaimana jika terlambat mengembalikan?', 'a' => 'Ada toleransi 2 jam. Setelahnya dikenakan denda per jam, maksimal setara tarif 1 hari per 24 jam.'],
                    ['q' => 'Apakah deposit dikembalikan?', 'a' => 'Ya. Deposit adalah dana titipan dan dikembalikan saat mobil kembali, dikurangi tagihan yang belum dibayar (jika ada).'],
                    ['q' => 'Metode pembayaran apa saja?', 'a' => 'Tunai, transfer bank, dan QRIS.'],
                ]],
            ['group' => 'landing', 'key' => 'landing_testimonials', 'type' => 'json', 'is_public' => true,
                'label' => 'Testimoni', 'description' => 'Nama, kota, ulasan, dan rating 1–5.',
                'value' => [
                    ['name' => 'Rizky A.', 'city' => 'Bogor', 'text' => 'Mobil bersih dan wangi, serah terima cepat. Recommended!', 'rating' => 5],
                    ['name' => 'Maya S.', 'city' => 'Depok', 'text' => 'Driver ramah dan tepat waktu. Tarifnya jelas dari awal.', 'rating' => 5],
                    ['name' => 'Hendra P.', 'city' => 'Jakarta', 'text' => 'Proses booking lewat WhatsApp gampang, tidak ribet.', 'rating' => 4],
                ]],
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
            'landing_hero_title' => ['string', 'min:5', 'max:120'],
            'landing_hero_subtitle' => ['nullable', 'string', 'max:300'],
            'landing_city' => ['nullable', 'string', 'max:60'],
            'landing_operating_hours' => ['nullable', 'string', 'max:120'],
            'landing_maps_url' => ['nullable', 'url:https', 'max:500'],
            'landing_how_to' => ['array', 'max:8'],
            'landing_how_to.*' => ['string', 'min:3', 'max:200'],
            'landing_terms' => ['array', 'max:15'],
            'landing_terms.*' => ['string', 'min:3', 'max:250'],
            'landing_faq' => ['array', 'max:15'],
            'landing_faq.*.q' => ['required', 'string', 'min:5', 'max:200'],
            'landing_faq.*.a' => ['required', 'string', 'min:3', 'max:600'],
            'landing_testimonials' => ['array', 'max:12'],
            'landing_testimonials.*.name' => ['required', 'string', 'max:60'],
            'landing_testimonials.*.city' => ['nullable', 'string', 'max:60'],
            'landing_testimonials.*.text' => ['required', 'string', 'min:5', 'max:400'],
            'landing_testimonials.*.rating' => ['required', 'integer', 'between:1,5'],
        ];
    }
}
