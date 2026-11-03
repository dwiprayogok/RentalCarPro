<?php

namespace Tests\Feature\MasterData;

use App\Models\Car;
use App\Models\Driver;
use App\Models\MaintenanceRecord;
use Tests\TestCase;

class DriverAndMaintenanceApiTest extends TestCase
{
    public function test_driver_crud_and_policy(): void
    {
        $staff = $this->userWithRole('staff');
        $admin = $this->userWithRole('admin');

        $payload = ['name' => 'Budi Santoso', 'phone' => '0812 9999 0001', 'daily_fee' => 150000];

        $this->actingAs($staff)->postJson('/api/v1/drivers', $payload)->assertForbidden();

        $id = $this->actingAs($admin)->postJson('/api/v1/drivers', $payload)
            ->assertCreated()
            ->assertJsonPath('data.phone', '+6281299990001')
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $this->actingAs($admin)->postJson('/api/v1/drivers', ['name' => 'X', 'phone' => '123'])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'phone']);

        $this->actingAs($staff)->getJson('/api/v1/drivers?search=budi')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin)->putJson("/api/v1/drivers/{$id}", $payload + ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->actingAs($staff)->getJson('/api/v1/drivers?is_active=1')->assertJsonCount(0, 'data');
        $this->actingAs($staff)->deleteJson("/api/v1/drivers/{$id}")->assertForbidden();
        $this->actingAs($admin)->deleteJson("/api/v1/drivers/{$id}")->assertOk();
        $this->assertSoftDeleted('drivers', ['id' => $id]);
    }

    public function test_maintenance_updates_car_due_dates_and_odometer(): void
    {
        $admin = $this->userWithRole('admin');
        $car = Car::factory()->create(['odometer' => 50000]);

        $this->actingAs($admin)->postJson('/api/v1/maintenance', [
            'car_id' => $car->id,
            'type' => 'servis_rutin',
            'title' => 'Servis 55.000 km',
            'performed_at' => now()->toDateString(),
            'odometer' => 55000,
            'cost' => 850000,
            'next_due_date' => now()->addMonths(6)->toDateString(),
            'next_due_odometer' => 65000,
        ])->assertCreated()->assertJsonPath('data.type_label', 'Servis rutin')->assertJsonPath('data.car.plate_number', $car->plate_number);

        $this->actingAs($admin)->postJson('/api/v1/maintenance', [
            'car_id' => $car->id,
            'type' => 'pajak',
            'title' => 'Pajak tahunan',
            'performed_at' => now()->toDateString(),
            'cost' => 3200000,
            'next_due_date' => now()->addYear()->toDateString(),
        ])->assertCreated();

        $car->refresh();
        $this->assertSame(55000, $car->odometer);
        $this->assertSame(65000, $car->next_service_odometer);
        $this->assertSame(now()->addMonths(6)->toDateString(), $car->next_service_date->toDateString());
        $this->assertSame(now()->addYear()->toDateString(), $car->tax_due_date->toDateString());

        $this->actingAs($admin)->getJson("/api/v1/maintenance?car_id={$car->id}")
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total_cost', 4050000);
    }

    public function test_maintenance_validation(): void
    {
        $admin = $this->userWithRole('admin');
        $car = Car::factory()->create();

        $this->actingAs($admin)->postJson('/api/v1/maintenance', [
            'car_id' => $car->id,
            'type' => 'cuci',
            'title' => '',
            'performed_at' => now()->addDay()->toDateString(),
            'odometer' => 10000,
            'cost' => -1,
            'next_due_odometer' => 9000,
        ])->assertStatus(422)->assertJsonValidationErrors(['type', 'title', 'performed_at', 'cost', 'next_due_odometer']);

        $car->delete();
        $this->actingAs($admin)->postJson('/api/v1/maintenance', [
            'car_id' => $car->id, 'type' => 'ban', 'title' => 'Ganti ban', 'performed_at' => now()->toDateString(), 'cost' => 1,
        ])->assertStatus(422)->assertJsonPath('errors.car_id.0', 'mobil yang dipilih tidak ditemukan.');
    }

    public function test_staff_can_only_view_maintenance(): void
    {
        $staff = $this->userWithRole('staff');
        $record = MaintenanceRecord::factory()->create();

        $this->actingAs($staff)->getJson('/api/v1/maintenance')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($staff)->getJson("/api/v1/maintenance/{$record->id}")->assertOk();
        $this->actingAs($staff)->postJson('/api/v1/maintenance', [])->assertForbidden();
        $this->actingAs($staff)->deleteJson("/api/v1/maintenance/{$record->id}")->assertForbidden();

        $this->actingAs($this->userWithRole('admin'))->deleteJson("/api/v1/maintenance/{$record->id}")->assertOk();
    }

    public function test_driver_model_factory_works(): void
    {
        $this->assertNotNull(Driver::factory()->create()->id);
    }
}
