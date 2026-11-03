<?php

namespace Database\Seeders;

use App\Enums\CarStatus;
use App\Enums\RiskStatus;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Driver;
use Illuminate\Database\Seeder;

/**
 * Data FIKTIF yang mirip prototipe (armada, pelanggan, driver).
 * Transaksi sewa contoh akan ditambahkan di phase transaksi.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $cars = [
            [
                'brand' => 'BYD', 'model' => 'Atto 3', 'plate_number' => 'F 1576 FGH', 'transmission' => 'matic',
                'year' => 2025, 'color' => 'Hitam', 'seats' => 5, 'fuel_type' => 'Listrik',
                'rate_self_drive' => 550000, 'rate_with_driver' => 750000, 'default_deposit' => 1000000,
                'odometer' => 0, 'status' => CarStatus::Tersedia, 'is_published' => true,
                'features' => ['AC', 'Kamera mundur', 'Apple CarPlay'],
                'description' => 'SUV listrik yang senyap dan nyaman untuk perjalanan dalam kota.',
            ],
            [
                'brand' => 'Toyota', 'model' => 'Innova', 'plate_number' => 'F 1234 RTY', 'transmission' => 'matic',
                'year' => 2019, 'color' => 'Hitam', 'seats' => 7, 'fuel_type' => 'Bensin',
                'rate_self_drive' => 700000, 'rate_with_driver' => 900000, 'default_deposit' => 1000000,
                'odometer' => 55000, 'status' => CarStatus::Tersedia, 'is_published' => true,
                'features' => ['AC double blower', '7 kursi', 'Bagasi luas'],
                'description' => 'MPV keluarga 7 kursi, cocok untuk mudik dan wisata.',
            ],
            [
                'brand' => 'Daihatsu', 'model' => 'Xenia', 'plate_number' => 'F 1888 KLM', 'transmission' => 'manual',
                'year' => 2021, 'color' => 'Silver', 'seats' => 7, 'fuel_type' => 'Bensin',
                'rate_self_drive' => 350000, 'rate_with_driver' => 550000, 'default_deposit' => 500000,
                'odometer' => 32000, 'status' => CarStatus::Servis, 'is_published' => false,
                'features' => ['AC', '7 kursi'],
                'description' => 'MPV hemat untuk kebutuhan harian.',
            ],
        ];

        foreach ($cars as $car) {
            Car::query()->withTrashed()->updateOrCreate(['plate_number' => $car['plate_number']], $car);
        }

        $customers = [
            ['name' => 'Dwi Prayogo Kurniadi', 'phone' => '+6281385270311', 'nik' => '3201010101900009',
                'sim_number' => '900101234567', 'sim_type' => 'A', 'sim_expires_at' => now()->addYears(3)->toDateString(),
                'address' => 'Jl. Pajajaran No. 10, Bogor', 'emergency_contact_name' => 'Sari', 'emergency_contact_phone' => '+6281300000011',
                'emergency_contact_relation' => 'Istri', 'risk_status' => RiskStatus::Lancar],
            ['name' => 'Yuni Wahyuni', 'phone' => '+6281354785421', 'nik' => '3201010101920001',
                'risk_status' => RiskStatus::Lancar],
            ['name' => 'Prima Sardi', 'phone' => '+6281385270322', 'nik' => '3201010101880002',
                'risk_status' => RiskStatus::Blacklist, 'risk_note' => 'Data fiktif: dua kali terlambat > 3 hari tanpa kabar.'],
        ];

        foreach ($customers as $data) {
            $existing = Customer::query()->withTrashed()->where('nik_hash', Customer::hashNik($data['nik']))->first();
            $existing ? $existing->update($data) : Customer::create($data);
        }

        Driver::query()->updateOrCreate(
            ['phone' => '+6281299990001'],
            ['name' => 'Budi Santoso', 'license_number' => 'B1-778899', 'license_expires_at' => now()->addYears(2)->toDateString(),
                'daily_fee' => 150000, 'is_active' => true],
        );
    }
}
