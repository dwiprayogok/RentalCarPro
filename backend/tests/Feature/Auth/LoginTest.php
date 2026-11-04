<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class LoginTest extends TestCase
{
    private function makeUser(array $attrs = []): User
    {
        return $this->userWithRole('admin', array_merge([
            'email' => 'admin@contoh.test',
            'password' => 'rahasia123',
        ], $attrs));
    }

    public function test_admin_web_can_login_with_session_and_fetch_me(): void
    {
        $user = $this->makeUser();

        $this->fromSpa()->postJson('/api/v1/auth/login', [
            'email' => 'ADMIN@contoh.test ',
            'password' => 'rahasia123',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Berhasil masuk.')
            ->assertJsonPath('data.email', 'admin@contoh.test')
            ->assertJsonPath('data.roles', ['admin']);

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->fromSpa()->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonFragment(['rental.create']);
    }

    public function test_wrong_password_returns_indonesian_error(): void
    {
        $this->makeUser();

        $this->fromSpa()->postJson('/api/v1/auth/login', [
            'email' => 'admin@contoh.test',
            'password' => 'salah-banget1',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Data yang dikirim tidak valid.')
            ->assertJsonPath('errors.email.0', 'Email atau kata sandi salah.');

        $this->assertGuest('web');
    }

    public function test_validation_messages_are_in_indonesian(): void
    {
        $this->fromSpa()->postJson('/api/v1/auth/login', ['email' => 'bukan-email'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'email harus berupa alamat email yang valid.')
            ->assertJsonPath('errors.password.0', 'kata sandi wajib diisi.');
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->makeUser(['is_active' => false]);

        $this->fromSpa()->postJson('/api/v1/auth/login', [
            'email' => 'admin@contoh.test',
            'password' => 'rahasia123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Akun Anda dinonaktifkan. Hubungi pemilik usaha.');
    }

    public function test_login_is_rate_limited(): void
    {
        $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->fromSpa()->postJson('/api/v1/auth/login', [
                'email' => 'admin@contoh.test',
                'password' => 'salah'.$i.'abc',
            ])->assertStatus(422);
        }

        $response = $this->fromSpa()->postJson('/api/v1/auth/login', [
            'email' => 'admin@contoh.test',
            'password' => 'rahasia123',
        ]);

        $response->assertStatus(429)->assertHeader('Retry-After');
        $this->assertStringStartsWith('Terlalu banyak percobaan.', $response->json('message'));
    }

    public function test_mobile_can_login_with_token_and_logout_revokes_it(): void
    {
        $user = $this->makeUser();

        $token = $this->postJson('/api/v1/auth/token', [
            'email' => 'admin@contoh.test',
            'password' => 'rahasia123',
            'device_name' => 'Pixel 8',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->json('token');

        $this->assertSame(1, $user->tokens()->count());

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@contoh.test');

        $this->withToken($token)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Anda telah keluar.');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_spa_logout_ends_session(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user, 'web');
        $this->fromSpa()->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertGuest('web');
    }

    public function test_guest_gets_indonesian_401(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Anda belum masuk atau sesi telah berakhir. Silakan masuk kembali.');
    }

    public function test_deactivated_user_loses_access_with_existing_token(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('hp')->plainTextToken;

        $user->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Akun Anda dinonaktifkan. Hubungi pemilik usaha.');
    }
}
