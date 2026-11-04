<?php

namespace Tests\Feature\Rental;

use App\Enums\RentalStatus;
use App\Models\Car;
use App\Models\Rental;
use App\Models\User;
use Tests\TestCase;

class RentalListTest extends TestCase
{
    use RentalTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeNow();

        $car = $this->car(['brand' => 'Toyota', 'model' => 'Innova', 'plate_number' => 'F 1234 RTY']);
        $other = $this->car(['brand' => 'BYD', 'model' => 'Atto 3', 'plate_number' => 'F 1576 FGH']);
        $dwi = $this->customer(['name' => 'Dwi Prayogo']);

        // Aktif & terlambat, lunas
        Rental::factory()->for($car)->for($dwi)->status(RentalStatus::Aktif)->create([
            'code' => 'TRX-2026-0001', 'start_at' => '2026-10-19 03:00:00', 'planned_end_at' => '2026-10-20 03:00:00', 'total_amount' => 550000, 'paid_amount' => 550000,
        ]);
        // Aktif tepat waktu, belum lunas
        Rental::factory()->for($other)->status(RentalStatus::Aktif)->create([
            'code' => 'TRX-2026-0002', 'start_at' => '2026-10-21 03:00:00', 'planned_end_at' => '2026-10-23 03:00:00', 'total_amount' => 1100000, 'paid_amount' => 300000,
        ]);
        // Selesai, lunas
        Rental::factory()->for($car)->status(RentalStatus::Selesai)->create([
            'code' => 'TRX-2026-0003', 'start_at' => '2026-10-01 03:00:00', 'planned_end_at' => '2026-10-02 03:00:00', 'total_amount' => 550000, 'paid_amount' => 550000,
        ]);
        // Batal, belum bayar (tidak dihitung belum lunas)
        Rental::factory()->for($other)->status(RentalStatus::Batal)->create([
            'code' => 'TRX-2026-0004', 'start_at' => '2026-10-25 03:00:00', 'planned_end_at' => '2026-10-26 03:00:00', 'total_amount' => 550000, 'paid_amount' => 0,
        ]);
    }

    public function test_views_and_counts(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->getJson('/api/v1/rentals')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.counts', ['semua' => 4, 'booking' => 0, 'aktif' => 2, 'terlambat' => 1, 'selesai' => 1, 'batal' => 1, 'belum_lunas' => 1])
            ->assertJsonPath('data.0.status', 'aktif');

        $this->actingAs($staff)->getJson('/api/v1/rentals?view=terlambat')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'TRX-2026-0001')
            ->assertJsonPath('data.0.display_status_label', 'Terlambat')
            ->assertJsonPath('data.0.billing.payment_status_label', 'Lunas (belum termasuk denda)');

        $this->actingAs($staff)->getJson('/api/v1/rentals?view=belum_lunas')->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'TRX-2026-0002');
        $this->actingAs($staff)->getJson('/api/v1/rentals?view=selesai')->assertJsonCount(1, 'data');
    }

    public function test_search_by_code_customer_and_plate(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->getJson('/api/v1/rentals?search=0003')->assertJsonCount(1, 'data');
        $this->actingAs($staff)->getJson('/api/v1/rentals?search=dwi')->assertJsonCount(1, 'data')->assertJsonPath('data.0.customer.name', 'Dwi Prayogo');
        $this->actingAs($staff)->getJson('/api/v1/rentals?search=f1576')->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.counts.semua', 2);
    }

    public function test_csv_export_respects_filters(): void
    {
        $res = $this->actingAs($this->userWithRole('staff'))->get('/api/v1/rentals/export?view=aktif');
        $res->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=transaksi-aktif-', $res->headers->get('Content-Disposition'));

        $csv = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_values(array_filter(explode("\n", trim(substr($csv, 3)))));
        $this->assertCount(3, $lines, 'header + 2 sewa aktif');
        $this->assertStringStartsWith('"No. Nota";Status;Pelanggan', $lines[0]);
        $this->assertStringContainsString('TRX-2026-0001;Terlambat', $csv);
        $this->assertStringContainsString('"Lunas (belum termasuk denda)"', $csv);
        $this->assertStringNotContainsString('TRX-2026-0003', $csv);
    }

    public function test_availability_calendar(): void
    {
        $car = Car::where('plate_number', 'F 1576 FGH')->first();

        $this->actingAs($this->userWithRole('staff'))->getJson("/api/v1/cars/{$car->id}/availability?from=2026-10-01&to=2026-10-31")
            ->assertOk()
            ->assertJsonCount(1, 'data') // yang batal tidak ikut
            ->assertJsonPath('data.0.code', 'TRX-2026-0002')
            ->assertJsonPath('data.0.start_at', '2026-10-21T10:00:00+07:00')
            ->assertJsonPath('data.0.end_at', '2026-10-23T10:00:00+07:00')
            ->assertJsonPath('data.0.blocked_until', '2026-10-23T12:00:00+07:00')
            ->assertJsonPath('meta.buffer_minutes', 120);

        $this->actingAs($this->userWithRole('staff'))->getJson("/api/v1/cars/{$car->id}/availability?from=2026-11-01&to=2026-11-30")
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_permissions(): void
    {
        $nobody = User::factory()->create();
        $this->actingAs($nobody)->getJson('/api/v1/rentals')->assertForbidden();
        $this->actingAs($nobody)->postJson('/api/v1/rentals', [])->assertForbidden();
        $this->actingAs($nobody)->get('/api/v1/rentals/export')->assertForbidden();
    }
}
