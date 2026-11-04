<?php

namespace Tests\Feature\Auth;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    private const SENT = 'Jika email terdaftar, tautan untuk mengatur ulang kata sandi telah dikirim.';

    public function test_forgot_password_sends_indonesian_email_with_frontend_link(): void
    {
        Notification::fake();
        $user = $this->userWithRole('staff', ['email' => 'staf@contoh.test', 'name' => 'Rina']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'staf@contoh.test'])
            ->assertOk()
            ->assertJsonPath('message', self::SENT);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $n) use ($user) {
            $mail = $n->toMail($user);
            $url = $n->resetUrl($user);

            return $mail->subject === 'Atur Ulang Kata Sandi '.config('app.name')
                && $mail->greeting === 'Halo, Rina!'
                && $mail->actionText === 'Atur Ulang Kata Sandi'
                && str_starts_with($url, 'http://localhost:5173/reset-password?token=')
                && str_contains($url, 'email=staf%40contoh.test');
        });
    }

    public function test_unknown_email_gets_same_response_and_no_email(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'tidak-ada@contoh.test'])
            ->assertOk()
            ->assertJsonPath('message', self::SENT);

        Notification::assertNothingSent();
    }

    public function test_inactive_user_does_not_receive_reset_email(): void
    {
        Notification::fake();
        $this->userWithRole('staff', ['email' => 'off@contoh.test', 'is_active' => false]);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'off@contoh.test'])
            ->assertOk()
            ->assertJsonPath('message', self::SENT);

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_valid_token_and_old_tokens_are_revoked(): void
    {
        $user = $this->userWithRole('staff', ['email' => 'staf@contoh.test']);
        $user->createToken('hp-lama');
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'staf@contoh.test',
            'password' => 'KataBaru2026',
            'password_confirmation' => 'KataBaru2026',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Kata sandi Anda berhasil diubah. Silakan masuk dengan kata sandi baru.');

        $this->assertTrue(Hash::check('KataBaru2026', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());

        // Token reset hanya bisa dipakai sekali.
        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'staf@contoh.test',
            'password' => 'KataLain2026',
            'password_confirmation' => 'KataLain2026',
        ])->assertStatus(422);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->userWithRole('staff', ['email' => 'staf@contoh.test']);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'token-palsu',
            'email' => 'staf@contoh.test',
            'password' => 'KataBaru2026',
            'password_confirmation' => 'KataBaru2026',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Tautan atur ulang kata sandi tidak valid atau sudah kedaluwarsa.');
    }

    public function test_weak_or_unconfirmed_password_is_rejected(): void
    {
        $user = $this->userWithRole('staff', ['email' => 'staf@contoh.test']);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'staf@contoh.test',
            'password' => 'pendek',
            'password_confirmation' => 'beda',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $errors = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'staf@contoh.test',
            'password' => 'hanyahuruf',
            'password_confirmation' => 'hanyahuruf',
        ])->json('errors.password');

        $this->assertContains('kata sandi harus mengandung minimal satu angka.', $errors);
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        Notification::fake();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'x@contoh.test'])->assertOk();
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'x@contoh.test'])->assertStatus(429);
    }
}
