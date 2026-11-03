<?php

namespace Tests\Feature\MasterData;

use App\Enums\RentalStatus;
use App\Models\Customer;
use App\Models\Rental;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => '  Dwi   Prayogo Kurniadi ',
            'phone' => '0813-8527-0311',
            'nik' => '3201 0101 0190 0009',
        ], $override);
    }

    private function selfDrive(array $override = []): array
    {
        return $this->payload(array_merge([
            'for_self_drive' => true,
            'sim_number' => '9001-0123-4567',
            'sim_type' => 'A',
            'sim_expires_at' => now()->addYear()->toDateString(),
            'address' => 'Jl. Pajajaran No. 10, Bogor',
            'emergency_contact_name' => 'Sari',
            'emergency_contact_phone' => '6281300000011',
        ], $override));
    }

    public function test_staff_can_create_customer_with_normalized_phone_and_masked_nik(): void
    {
        $response = $this->actingAs($this->userWithRole('staff'))->postJson('/api/v1/customers', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Dwi Prayogo Kurniadi')
            ->assertJsonPath('data.phone', '+6281385270311')
            ->assertJsonPath('data.nik_masked', '•••• •••• •••• 0009')
            ->assertJsonPath('data.initials', 'DP')
            ->assertJsonPath('data.risk_status', 'lancar')
            ->assertJsonPath('data.self_drive_ready', false);

        // NIK lengkap tidak pernah keluar dari API.
        $this->assertStringNotContainsString('3201010101900009', $response->getContent());
        $this->assertArrayNotHasKey('nik', $response->json('data'));
        $this->assertSame('3201010101900009', Customer::first()->nik);
    }

    public function test_nik_must_be_16_digits_and_unique(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->postJson('/api/v1/customers', $this->payload(['nik' => '12345']))
            ->assertStatus(422)->assertJsonPath('errors.nik.0', 'Nomor KTP harus 16 digit angka.');
        $this->actingAs($staff)->postJson('/api/v1/customers', $this->payload(['nik' => '32010101019000AB']))
            ->assertStatus(422)->assertJsonPath('errors.nik.0', 'Nomor KTP harus 16 digit angka.');

        Customer::factory()->create(['name' => 'Yuni', 'nik' => '3201010101900009']);
        $this->actingAs($staff)->postJson('/api/v1/customers', $this->payload())
            ->assertStatus(422)->assertJsonPath('errors.nik.0', 'NIK ini sudah terdaftar atas nama Yuni.');
    }

    public function test_phone_must_be_indonesian_mobile(): void
    {
        $staff = $this->userWithRole('staff');

        foreach (['12345', '+6512345678', '021-555-1234'] as $bad) {
            $this->actingAs($staff)->postJson('/api/v1/customers', $this->payload(['phone' => $bad]))
                ->assertStatus(422)->assertJsonValidationErrors(['phone']);
        }

        foreach (['+62 812 3456 7890' => '+6281234567890', '6281234567890' => '+6281234567890', '81234567890' => '+6281234567890'] as $in => $out) {
            Customer::query()->forceDelete();
            $this->actingAs($staff)->postJson('/api/v1/customers', $this->payload(['phone' => $in]))
                ->assertCreated()->assertJsonPath('data.phone', $out);
        }
    }

    public function test_self_drive_requires_sim_address_and_emergency_contact(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->postJson('/api/v1/customers', $this->payload(['for_self_drive' => true]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sim_number', 'sim_expires_at', 'address', 'emergency_contact_name', 'emergency_contact_phone'])
            ->assertJsonPath('errors.address.0', 'alamat domisili wajib diisi untuk pelanggan layanan Lepas Kunci.');

        $this->actingAs($staff)->postJson('/api/v1/customers', $this->selfDrive(['sim_expires_at' => now()->subDay()->toDateString()]))
            ->assertStatus(422)->assertJsonPath('errors.sim_expires_at.0', 'SIM sudah kedaluwarsa. Masa berlaku harus setelah hari ini.');

        $this->actingAs($staff)->postJson('/api/v1/customers', $this->selfDrive(['emergency_contact_phone' => '081385270311']))
            ->assertStatus(422)->assertJsonPath('errors.emergency_contact_phone.0', 'Nomor kontak darurat harus berbeda dari nomor pelanggan.');

        $this->actingAs($staff)->postJson('/api/v1/customers', $this->selfDrive())
            ->assertCreated()
            ->assertJsonPath('data.self_drive_ready', true)
            ->assertJsonPath('data.self_drive_missing', [])
            ->assertJsonPath('data.sim_number', '900101234567')
            ->assertJsonPath('data.emergency_contact_phone', '+6281300000011');
    }

    public function test_risk_note_required_for_perhatian_and_blacklist(): void
    {
        $admin = $this->userWithRole('admin');

        foreach (['perhatian', 'blacklist'] as $status) {
            $this->actingAs($admin)->postJson('/api/v1/customers', $this->payload(['risk_status' => $status]))
                ->assertStatus(422)
                ->assertJsonPath('errors.risk_note.0', 'Catatan risiko wajib diisi untuk status Perhatian atau Blacklist.');
        }

        $this->actingAs($admin)->postJson('/api/v1/customers', $this->payload(['risk_status' => 'blacklist', 'risk_note' => 'Menunggak 3 kali.']))
            ->assertCreated()->assertJsonPath('data.risk_status_label', 'Blacklist');
    }

    public function test_staff_cannot_change_risk_status_but_admin_can_and_it_is_logged(): void
    {
        $customer = Customer::factory()->create();
        $update = $this->payload(['nik' => '', 'risk_status' => 'perhatian', 'risk_note' => 'Sering telat.']);

        $this->actingAs($this->userWithRole('staff'))->putJson("/api/v1/customers/{$customer->id}", $update)
            ->assertStatus(422)
            ->assertJsonPath('errors.risk_status.0', 'Anda tidak berwenang mengubah status risiko pelanggan.');

        // Staff tetap boleh mengedit data lain selama status risiko tidak berubah.
        $this->actingAs($this->userWithRole('staff'))->putJson("/api/v1/customers/{$customer->id}", $this->payload(['nik' => '']))
            ->assertOk();

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->putJson("/api/v1/customers/{$customer->id}", $update)
            ->assertOk()
            ->assertJsonPath('data.risk_status', 'perhatian');

        $customer->refresh();
        $this->assertSame($admin->id, $customer->risk_updated_by);
        $this->assertNotNull($customer->risk_updated_at);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'customer', 'subject_id' => $customer->id, 'causer_id' => $admin->id, 'event' => 'updated']);
    }

    public function test_empty_nik_on_update_keeps_existing(): void
    {
        $customer = Customer::factory()->create(['nik' => '3201010101900009']);

        $this->actingAs($this->userWithRole('admin'))->putJson("/api/v1/customers/{$customer->id}", $this->payload(['nik' => null]))
            ->assertOk()->assertJsonPath('data.nik_masked', '•••• •••• •••• 0009');

        $this->assertSame('3201010101900009', $customer->fresh()->nik);
    }

    public function test_search_by_name_phone_full_nik_and_last4_with_risk_counts(): void
    {
        $staff = $this->userWithRole('staff');
        Customer::factory()->create(['name' => 'Dwi Prayogo', 'phone' => '+6281385270311', 'nik' => '3201010101900009']);
        Customer::factory()->create(['name' => 'Yuni Wahyuni', 'phone' => '+6281354785421', 'nik' => '3201010101920001']);
        Customer::factory()->blacklisted()->create(['name' => 'Prima Sardi', 'nik' => '3201010101880002']);

        $this->actingAs($staff)->getJson('/api/v1/customers?search=yuni')->assertJsonCount(1, 'data');
        $this->actingAs($staff)->getJson('/api/v1/customers?search=081385270311')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Dwi Prayogo');
        $this->actingAs($staff)->getJson('/api/v1/customers?search=3201010101920001')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Yuni Wahyuni');
        $this->actingAs($staff)->getJson('/api/v1/customers?search=0002')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Prima Sardi');

        $this->actingAs($staff)->getJson('/api/v1/customers?risk_status=blacklist')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.counts', ['semua' => 3, 'lancar' => 2, 'perhatian' => 0, 'blacklist' => 1])
            ->assertJsonPath('data.0.rentals_count', 0);
    }

    public function test_documents_are_private_and_only_reachable_through_signed_url(): void
    {
        Storage::fake('private');
        $staff = $this->userWithRole('staff');
        $customer = Customer::factory()->create();

        $response = $this->actingAs($staff)->post("/api/v1/customers/{$customer->id}/documents", [
            'type' => 'ktp',
            'file' => UploadedFile::fake()->image('ktp.jpg', 1000, 630),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message', 'Foto KTP berhasil diunggah.')
            ->assertJsonPath('data.documents.ktp.uploaded', true)
            ->assertJsonPath('data.documents.sim.uploaded', false);

        $path = $customer->fresh()->ktp_photo_path;
        Storage::disk('private')->assertExists($path);
        $this->assertStringStartsWith("customers/{$customer->id}/ktp-", $path);
        $this->assertStringNotContainsString($path, $response->getContent());

        $url = $response->json('data.documents.ktp.url');
        $this->assertStringContainsString('signature=', $url);

        // Signed URL bisa dibuka tanpa sesi.
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        // Tanpa / dengan signature palsu: ditolak.
        $unsigned = strtok($url, '?');
        $this->getJson($unsigned)->assertForbidden()
            ->assertJsonPath('message', 'Tautan tidak valid atau sudah kedaluwarsa. Muat ulang halaman untuk mendapatkan tautan baru.');
        $this->getJson($url.'x')->assertForbidden();

        // Kedaluwarsa setelah 10 menit.
        $this->travel(11)->minutes();
        $this->getJson($url)->assertForbidden();
    }

    public function test_document_upload_validation_and_permissions(): void
    {
        Storage::fake('private');
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole('staff'))->post("/api/v1/customers/{$customer->id}/documents", [
            'type' => 'paspor',
            'file' => UploadedFile::fake()->create('x.exe', 10),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors(['type', 'file']);

        Role::findByName('staff', 'web')->revokePermissionTo('customer.view_documents');
        $limited = $this->userWithRole('staff');

        $this->actingAs($limited)->post("/api/v1/customers/{$customer->id}/documents", [
            'type' => 'ktp',
            'file' => UploadedFile::fake()->image('ktp.jpg'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        // Tanpa view_documents, URL dokumen tidak diberikan.
        $customer->forceFill(['ktp_photo_path' => 'customers/1/ktp.jpg'])->save();
        $this->actingAs($limited)->getJson("/api/v1/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.documents.ktp.uploaded', true)
            ->assertJsonPath('data.documents.ktp.url', null);
    }

    public function test_replacing_document_deletes_old_file(): void
    {
        Storage::fake('private');
        $admin = $this->userWithRole('admin');
        $customer = Customer::factory()->create();

        $upload = fn () => $this->actingAs($admin)->post("/api/v1/customers/{$customer->id}/documents", [
            'type' => 'sim', 'file' => UploadedFile::fake()->image('sim.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $upload();
        $first = $customer->fresh()->sim_photo_path;
        $upload();
        Storage::disk('private')->assertMissing($first);
        Storage::disk('private')->assertExists($customer->fresh()->sim_photo_path);

        $this->actingAs($admin)->deleteJson("/api/v1/customers/{$customer->id}/documents/sim")
            ->assertOk()->assertJsonPath('data.documents.sim.uploaded', false);
    }

    public function test_delete_permissions_and_open_rental_guard(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole('staff'))->deleteJson("/api/v1/customers/{$customer->id}")->assertForbidden();

        Rental::factory()->for($customer)->status(RentalStatus::Aktif)->create();
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->deleteJson("/api/v1/customers/{$customer->id}")->assertStatus(422)->assertJsonValidationErrors(['customer']);

        $other = Customer::factory()->create();
        $this->actingAs($admin)->deleteJson("/api/v1/customers/{$other->id}")->assertOk();
        $this->assertSoftDeleted($other);
    }

    public function test_rental_history_is_listed(): void
    {
        $customer = Customer::factory()->create();
        Rental::factory()->for($customer)->count(2)->create();

        $this->actingAs($this->userWithRole('staff'))->getJson("/api/v1/customers/{$customer->id}/rentals")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['code', 'car' => ['name', 'plate_number'], 'status_label', 'start_at', 'balance']]]);
    }
}
