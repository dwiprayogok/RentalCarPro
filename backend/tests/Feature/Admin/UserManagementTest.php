<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->userWithRole('owner', ['email' => 'owner@contoh.test']);
    }

    public function test_create_update_and_assign_role_is_logged(): void
    {
        $id = $this->actingAs($this->owner)->postJson('/api/v1/users', [
            'name' => 'Sari Kasir', 'email' => 'SARI@contoh.test', 'phone' => '0812-1111-2222', 'password' => 'rahasia123', 'role' => 'staff',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'sari@contoh.test')
            ->assertJsonPath('data.phone', '+6281211112222')
            ->assertJsonPath('data.role_label', 'Staf')
            ->json('data.id');

        $this->actingAs($this->owner)->putJson("/api/v1/users/{$id}", ['name' => 'Sari Admin', 'email' => 'sari@contoh.test', 'role' => 'admin'])
            ->assertOk()->assertJsonPath('data.role', 'admin')->assertJsonPath('data.name', 'Sari Admin');

        $this->assertTrue(User::find($id)->hasRole('admin'));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'user', 'event' => 'role_changed', 'subject_id' => $id, 'causer_id' => $this->owner->id]);

        $this->actingAs($this->owner)->postJson('/api/v1/users', ['name' => 'X', 'email' => 'owner@contoh.test', 'password' => 'abc', 'role' => 'superadmin'])
            ->assertStatus(422)->assertJsonValidationErrors(['email', 'password', 'role']);
    }

    public function test_owner_safeguards(): void
    {
        $this->actingAs($this->owner)->putJson("/api/v1/users/{$this->owner->id}", ['name' => 'Owner', 'email' => 'owner@contoh.test', 'role' => 'staff'])
            ->assertStatus(422)->assertJsonPath('errors.role.0', 'Anda tidak bisa mengubah role akun Anda sendiri.');
        $this->actingAs($this->owner)->patchJson("/api/v1/users/{$this->owner->id}/status", ['is_active' => false])
            ->assertStatus(422)->assertJsonPath('errors.is_active.0', 'Anda tidak bisa menonaktifkan akun Anda sendiri.');

        // Dua owner: salah satu boleh dinonaktifkan karena masih ada owner aktif lain.
        $second = $this->userWithRole('owner');
        $this->actingAs($second)->patchJson("/api/v1/users/{$this->owner->id}/status", ['is_active' => false])->assertOk();

        // Kini $second satu-satunya owner aktif: tidak boleh diturunkan oleh siapa pun.
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Harus ada minimal satu pemilik (owner) yang aktif.');
        app(UserService::class)->update($second, ['name' => $second->name, 'email' => $second->email, 'role' => 'admin'], $this->owner);
    }

    public function test_deactivate_revokes_tokens_and_sessions_then_reactivate(): void
    {
        $staff = $this->userWithRole('staff', ['email' => 'staf@contoh.test', 'password' => 'rahasia123']);
        $token = $staff->createToken('hp')->plainTextToken;
        DB::table('sessions')->insert(['id' => 'sesi-uji', 'user_id' => $staff->id, 'payload' => 'x', 'last_activity' => time()]);

        $this->actingAs($this->owner)->patchJson("/api/v1/users/{$staff->id}/status", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->assertSame(0, $staff->tokens()->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $staff->id)->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->postJson('/api/v1/auth/token', ['email' => 'staf@contoh.test', 'password' => 'rahasia123', 'device_name' => 'x'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'Akun Anda dinonaktifkan. Hubungi pemilik usaha.');

        $this->actingAs($this->owner)->patchJson("/api/v1/users/{$staff->id}/status", ['is_active' => true])->assertOk();
        $this->assertDatabaseHas('activity_log', ['log_name' => 'user', 'subject_id' => $staff->id, 'event' => 'updated']);
    }

    public function test_force_password_reset(): void
    {
        Notification::fake();
        $staff = $this->userWithRole('staff', ['password' => 'rahasia123']);
        $staff->createToken('hp');

        $this->actingAs($this->owner)->postJson("/api/v1/users/{$staff->id}/force-password-reset")
            ->assertOk()->assertJsonPath('message', "Kata sandi {$staff->name} direset. Tautan atur ulang dikirim ke {$staff->email}.");

        $this->assertFalse(Hash::check('rahasia123', $staff->fresh()->password), 'Kata sandi lama tidak berlaku');
        $this->assertSame(0, $staff->tokens()->count());
        Notification::assertSentTo($staff, ResetPasswordNotification::class);
        $this->assertDatabaseHas('activity_log', ['event' => 'password_reset_forced', 'subject_id' => $staff->id]);

        $staff->forceFill(['is_active' => false])->save();
        $this->actingAs($this->owner)->postJson("/api/v1/users/{$staff->id}/force-password-reset")->assertStatus(422);
    }

    public function test_roles_matrix_and_user_filters(): void
    {
        $this->userWithRole('staff', ['name' => 'Staf Satu']);
        $this->userWithRole('staff', ['name' => 'Staf Dua', 'is_active' => false]);

        $roles = $this->actingAs($this->owner)->getJson('/api/v1/roles')->assertOk()->json('data.roles');
        $staff = collect($roles)->firstWhere('name', 'staff');
        $this->assertSame(2, $staff['users_count']);
        $this->assertContains('rental.return', $staff['permissions']);
        $this->assertNotContains('setting.update', $staff['permissions']);

        $this->actingAs($this->owner)->getJson('/api/v1/users?role=staff&status=nonaktif')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Staf Dua');
        $this->actingAs($this->owner)->getJson('/api/v1/users?search=satu')->assertJsonCount(1, 'data');
    }
}
