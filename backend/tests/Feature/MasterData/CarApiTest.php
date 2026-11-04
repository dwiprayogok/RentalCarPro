<?php

namespace Tests\Feature\MasterData;

use App\Enums\CarStatus;
use App\Enums\RentalStatus;
use App\Models\Car;
use App\Models\Rental;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CarApiTest extends TestCase
{
    private function payload(array $override = []): array
    {
        return array_merge([
            'brand' => 'BYD',
            'model' => 'Atto 3',
            'plate_number' => ' f  1576   fgh ',
            'transmission' => 'matic',
            'year' => 2025,
            'color' => 'Hitam',
            'seats' => 5,
            'rate_self_drive' => 550000,
            'rate_with_driver' => 750000,
            'odometer' => 0,
            'status' => 'tersedia',
            'features' => ['AC', '  ', 'Kamera mundur'],
        ], $override);
    }

    public function test_admin_can_create_car_with_normalized_plate_and_auto_slug(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson('/api/v1/cars', $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', 'Armada berhasil ditambahkan.')
            ->assertJsonPath('data.plate_number', 'F 1576 FGH')
            ->assertJsonPath('data.slug', 'byd-atto-3-2025')
            ->assertJsonPath('data.name', 'BYD Atto 3')
            ->assertJsonPath('data.features', ['AC', 'Kamera mundur'])
            ->assertJsonPath('data.status_label', 'Tersedia')
            ->assertJsonPath('data.can.update', true);
    }

    public function test_plate_must_be_unique_and_well_formed(): void
    {
        $admin = $this->userWithRole('admin');
        Car::factory()->create(['plate_number' => 'F 1576 FGH']);

        $this->actingAs($admin)->postJson('/api/v1/cars', $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('errors.plate_number.0', 'Plat nomor ini sudah terdaftar.');

        $this->actingAs($admin)->postJson('/api/v1/cars', $this->payload(['plate_number' => '1234-ABC']))
            ->assertStatus(422)
            ->assertJsonPath('errors.plate_number.0', 'Format plat nomor tidak valid. Contoh: F 1576 FGH.');
    }

    public function test_required_fields_and_rate_rules(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson('/api/v1/cars', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['brand', 'model', 'plate_number', 'transmission', 'year', 'rate_self_drive', 'rate_with_driver', 'odometer', 'status'])
            ->assertJsonPath('errors.brand.0', 'merk wajib diisi.');

        $this->actingAs($admin)->postJson('/api/v1/cars', $this->payload(['rate_with_driver' => 400000]))
            ->assertStatus(422)
            ->assertJsonPath('errors.rate_with_driver.0', 'Tarif dengan driver adalah total per hari termasuk driver, jadi tidak boleh lebih kecil dari tarif lepas kunci.');

        $this->actingAs($admin)->postJson('/api/v1/cars', $this->payload(['year' => 1980, 'rate_self_drive' => 550000.5]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['year', 'rate_self_drive']);
    }

    public function test_status_disewa_cannot_be_set_manually(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->postJson('/api/v1/cars', $this->payload(['status' => 'disewa']))
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'Status "Disewa" diatur otomatis oleh transaksi sewa.');

        $rented = Car::factory()->status(CarStatus::Disewa)->create();
        $this->actingAs($admin)->putJson("/api/v1/cars/{$rented->id}", $this->payload(['plate_number' => $rented->plate_number, 'status' => 'tersedia']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_odometer_cannot_go_backwards_except_owner(): void
    {
        $car = Car::factory()->create(['odometer' => 50000, 'plate_number' => 'F 1 AB']);
        $payload = $this->payload(['plate_number' => 'F 1 AB', 'odometer' => 40000]);

        $this->actingAs($this->userWithRole('admin'))->putJson("/api/v1/cars/{$car->id}", $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.odometer.0', 'Odometer tidak boleh lebih kecil dari nilai terakhir (50.000 km).');

        $this->actingAs($this->userWithRole('owner'))->putJson("/api/v1/cars/{$car->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.odometer', 40000);
    }

    public function test_staff_can_view_but_not_modify_cars(): void
    {
        $staff = $this->userWithRole('staff');
        $car = Car::factory()->create();

        $this->actingAs($staff)->getJson('/api/v1/cars')->assertOk();
        $this->actingAs($staff)->getJson("/api/v1/cars/{$car->id}")->assertOk()->assertJsonPath('data.can.update', false);
        $this->actingAs($staff)->postJson('/api/v1/cars', $this->payload())->assertForbidden();
        $this->actingAs($staff)->putJson("/api/v1/cars/{$car->id}", $this->payload())->assertForbidden();
        $this->actingAs($staff)->deleteJson("/api/v1/cars/{$car->id}")->assertForbidden();
    }

    public function test_publish_toggle_requires_permission(): void
    {
        // Admin punya car.publish; cabut untuk menguji jalur validasi.
        $admin = $this->userWithRole('admin');
        Role::findByName('admin', 'web')->revokePermissionTo('car.publish');
        $admin->refresh();

        $this->actingAs($admin)->postJson('/api/v1/cars', $this->payload(['is_published' => true]))
            ->assertStatus(422)
            ->assertJsonPath('errors.is_published.0', 'Anda tidak berwenang mengubah tampilan mobil di landing page.');

        $this->actingAs($admin)->postJson('/api/v1/cars', $this->payload(['is_published' => false]))->assertCreated();
    }

    public function test_index_filters_search_and_counts(): void
    {
        $staff = $this->userWithRole('staff');
        Car::factory()->create(['brand' => 'Toyota', 'model' => 'Innova', 'plate_number' => 'F 1234 RTY', 'status' => CarStatus::Tersedia]);
        Car::factory()->create(['brand' => 'BYD', 'model' => 'Atto 3', 'plate_number' => 'F 1576 FGH', 'status' => CarStatus::Disewa]);
        Car::factory()->create(['brand' => 'Daihatsu', 'model' => 'Xenia', 'plate_number' => 'B 9 XY', 'status' => CarStatus::Servis]);

        $this->actingAs($staff)->getJson('/api/v1/cars?status=disewa')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.plate_number', 'F 1576 FGH')
            ->assertJsonPath('meta.counts', ['semua' => 3, 'tersedia' => 1, 'disewa' => 1, 'servis' => 1, 'nonaktif' => 0])
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($staff)->getJson('/api/v1/cars?search=f1234')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Toyota Innova');
        $this->actingAs($staff)->getJson('/api/v1/cars?search=toyota%20innova')->assertJsonCount(1, 'data');
        $this->actingAs($staff)->getJson('/api/v1/cars?search=%25')->assertJsonCount(0, 'data');
        $this->actingAs($staff)->getJson('/api/v1/cars?status=terbang')->assertStatus(422);
    }

    public function test_car_with_open_rental_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole('admin');
        $car = Car::factory()->create();
        Rental::factory()->for($car)->status(RentalStatus::Booking)->create();

        $this->actingAs($admin)->deleteJson("/api/v1/cars/{$car->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['car']);

        $free = Car::factory()->create(['is_published' => true]);
        $this->actingAs($admin)->deleteJson("/api/v1/cars/{$free->id}")->assertOk();
        $this->assertSoftDeleted('cars', ['id' => $free->id, 'is_published' => false]);
    }

    public function test_photos_upload_to_public_disk_with_cover_management(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $car = Car::factory()->create();

        $response = $this->actingAs($admin)->post("/api/v1/cars/{$car->id}/photos", [
            'photos' => [
                UploadedFile::fake()->image('depan.jpg', 1200, 800),
                UploadedFile::fake()->image('samping.png', 1200, 800),
            ],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonCount(2, 'data')->assertJsonPath('data.0.is_cover', true);
        $photos = $car->photos()->get();
        Storage::disk('public')->assertExists($photos->pluck('path')->all());

        $second = $photos[1];
        $this->actingAs($admin)->patchJson("/api/v1/cars/{$car->id}/photos/{$second->id}", ['is_cover' => true])
            ->assertOk()->assertJsonPath('data.0.is_cover', false)->assertJsonPath('data.1.is_cover', true);

        $this->actingAs($admin)->deleteJson("/api/v1/cars/{$car->id}/photos/{$second->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_cover', true);
        Storage::disk('public')->assertMissing($second->path);

        $this->actingAs($admin)->getJson("/api/v1/cars/{$car->id}")
            ->assertJsonPath('data.cover_photo_url', Storage::disk('public')->url($photos[0]->path));
    }

    public function test_photo_validation_and_staff_forbidden(): void
    {
        Storage::fake('public');
        $car = Car::factory()->create();

        $this->actingAs($this->userWithRole('admin'))->post("/api/v1/cars/{$car->id}/photos", [
            'photos' => [UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors(['photos.0']);

        $this->actingAs($this->userWithRole('staff'))->post("/api/v1/cars/{$car->id}/photos", [
            'photos' => [UploadedFile::fake()->image('a.jpg', 800, 600)],
        ], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_photo_of_other_car_is_not_reachable(): void
    {
        $admin = $this->userWithRole('admin');
        $carA = Car::factory()->create();
        $carB = Car::factory()->create();
        $photo = $carB->photos()->create(['path' => 'cars/x.jpg']);

        $this->actingAs($admin)->deleteJson("/api/v1/cars/{$carA->id}/photos/{$photo->id}")->assertNotFound();
    }
}
