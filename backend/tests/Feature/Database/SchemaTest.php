<?php

namespace Tests\Feature\Database;

use App\Models\Car;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    public function test_core_tables_exist(): void
    {
        foreach ([
            'users', 'cars', 'car_photos', 'customers', 'drivers', 'rentals', 'rental_payments',
            'rental_inspections', 'rental_fees', 'maintenance_records', 'booking_requests', 'settings',
            'holidays', 'document_sequences', 'roles', 'permissions', 'activity_log', 'personal_access_tokens',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel $table tidak ada");
        }

        foreach (['cars', 'customers', 'drivers', 'rentals', 'rental_payments', 'rental_fees', 'maintenance_records', 'booking_requests', 'users'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'deleted_at'), "Tabel $table belum soft delete");
        }
    }

    public function test_nik_is_encrypted_hashed_and_masked(): void
    {
        $customer = Customer::create(['name' => 'Uji', 'phone' => '+628123', 'nik' => '3201 0101 0190 0009']);

        $raw = DB::table('customers')->where('id', $customer->id)->first();

        $this->assertStringNotContainsString('3201010101900009', $raw->nik_encrypted);
        $this->assertSame('0009', $raw->nik_last4);
        $this->assertSame(Customer::hashNik('3201010101900009'), $raw->nik_hash);
        $this->assertSame('3201010101900009', $customer->fresh()->nik);
        $this->assertSame('•••• •••• •••• 0009', $customer->nik_masked);
        $this->assertArrayNotHasKey('nik_encrypted', $customer->fresh()->toArray());
    }

    public function test_dates_are_stored_utc_and_serialized_in_wib(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 08:41:00', 'UTC'));

        $this->seed([DemoUserSeeder::class, DemoDataSeeder::class]);
        $car = Car::query()->where('plate_number', 'F 1576 FGH')->firstOrFail();

        $this->assertSame('2026-10-21 08:41:00', DB::table('cars')->where('id', $car->id)->value('created_at'));
        $this->assertSame('2026-10-21T15:41:00+07:00', $car->toArray()['created_at']);
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', config('app.display_timezone'));
    }

    public function test_demo_seeders_are_idempotent(): void
    {
        $this->seed([DemoUserSeeder::class, DemoDataSeeder::class]);
        $this->seed([DemoUserSeeder::class, DemoDataSeeder::class]);

        $this->assertSame(3, Car::count());
        $this->assertSame(3, Customer::count());
        $this->assertSame(3, User::count());
        $this->assertSame('byd-atto-3-2025', Car::where('plate_number', 'F 1576 FGH')->value('slug'));
    }
}
