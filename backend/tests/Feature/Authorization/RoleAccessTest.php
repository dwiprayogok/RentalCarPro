<?php

namespace Tests\Feature\Authorization;

use App\Models\Setting;
use App\Services\SettingService;
use App\Support\Permissions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    public function test_roles_have_expected_permissions(): void
    {
        $owner = Role::findByName('owner');
        $admin = Role::findByName('admin');
        $staff = Role::findByName('staff');

        $this->assertCount(count(Permissions::all()), $owner->permissions);

        foreach ([Permissions::SETTING_UPDATE, Permissions::USER_MANAGE, Permissions::RENTAL_OVERRIDE_BLACKLIST] as $p) {
            $this->assertFalse($admin->hasPermissionTo($p), "admin tidak boleh punya $p");
        }
        foreach ([Permissions::RENTAL_CREATE, Permissions::PAYMENT_REFUND, Permissions::CAR_DELETE, Permissions::REPORT_VIEW] as $p) {
            $this->assertTrue($admin->hasPermissionTo($p), "admin harus punya $p");
        }

        foreach ([Permissions::RENTAL_CREATE, Permissions::RENTAL_HANDOVER, Permissions::RENTAL_RETURN, Permissions::CUSTOMER_CREATE] as $p) {
            $this->assertTrue($staff->hasPermissionTo($p), "staff harus punya $p");
        }

        // Staff: tidak bisa menghapus data, ubah tarif/denda, atau lihat laporan keuangan penuh.
        $staffPerms = $staff->permissions->pluck('name');
        $this->assertEmpty($staffPerms->filter(fn ($p) => str_ends_with($p, '.delete'))->all());
        foreach ([Permissions::SETTING_UPDATE, Permissions::REPORT_VIEW, Permissions::PAYMENT_REFUND, Permissions::RENTAL_DISCOUNT] as $p) {
            $this->assertFalse($staff->hasPermissionTo($p), "staff tidak boleh punya $p");
        }
    }

    public function test_me_returns_roles_and_permissions_per_role(): void
    {
        $staff = $this->userWithRole('staff');

        $response = $this->actingAs($staff)->getJson('/api/v1/auth/me')->assertOk();

        $this->assertSame(['staff'], $response->json('data.roles'));
        $this->assertContains('rental.return', $response->json('data.permissions'));
        $this->assertNotContains('setting.update', $response->json('data.permissions'));
    }

    public function test_all_roles_can_view_settings(): void
    {
        foreach (['owner', 'admin', 'staff'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->getJson('/api/v1/settings')
                ->assertOk()
                ->assertJsonFragment(['key' => 'late_fee_per_hour', 'value' => 50000]);
        }
    }

    public function test_only_owner_can_update_settings(): void
    {
        $payload = ['settings' => ['late_fee_per_hour' => 75000, 'grace_period_minutes' => 60]];

        foreach (['admin', 'staff'] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->putJson('/api/v1/settings', $payload)
                ->assertStatus(403)
                ->assertJsonPath('message', 'Anda tidak memiliki akses untuk tindakan ini.');
        }

        $this->actingAs($this->userWithRole('owner'))
            ->putJson('/api/v1/settings', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Pengaturan berhasil disimpan.')
            ->assertJsonFragment(['key' => 'late_fee_per_hour', 'value' => 75000]);

        $this->assertSame(75000, app(SettingService::class)->int('late_fee_per_hour'));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'setting', 'subject_type' => Setting::class]);
    }

    public function test_settings_update_validates_values(): void
    {
        $this->actingAs($this->userWithRole('owner'))
            ->putJson('/api/v1/settings', ['settings' => ['late_fee_per_hour' => -5]])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['settings.late_fee_per_hour' => ['Harus berupa bilangan bulat tidak negatif.']]);

        $this->actingAs($this->userWithRole('owner'))
            ->putJson('/api/v1/settings', ['settings' => ['tidak_ada' => 1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings']);
    }

    public function test_only_owner_can_manage_users(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $actor = $this->userWithRole($role);
            $this->actingAs($actor)->getJson('/api/v1/users')->assertStatus(403);
            $this->actingAs($actor)->postJson('/api/v1/users', [])->assertStatus(403);
        }

        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->getJson('/api/v1/users')->assertOk()->assertJsonStructure(['data', 'meta']);

        $this->actingAs($owner)->postJson('/api/v1/users', [
            'name' => 'Staf Baru',
            'email' => 'Staf.Baru@Contoh.test',
            'password' => 'rahasia123',
            'role' => 'staff',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'staf.baru@contoh.test')
            ->assertJsonPath('data.roles', ['staff']);
    }

    public function test_token_user_gets_same_role_permissions_as_session(): void
    {
        $admin = $this->userWithRole('admin');
        $token = $admin->createToken('flutter')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/settings')->assertOk();
        $this->withToken($token)->putJson('/api/v1/settings', ['settings' => ['late_fee_per_hour' => 1]])->assertStatus(403);
    }
}
