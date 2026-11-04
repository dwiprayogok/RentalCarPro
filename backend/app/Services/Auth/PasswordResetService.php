<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    public function sendResetLink(string $email): void
    {
        $email = mb_strtolower(trim($email));

        // Pengguna nonaktif tidak dikirimi tautan; balasan API tetap sama.
        $user = User::query()->where('email', $email)->first();
        if (! $user || ! $user->is_active) {
            return;
        }

        // Status RESET_THROTTLED / INVALID_USER sengaja tidak dibocorkan ke klien.
        Password::broker()->sendResetLink(['email' => $email]);
    }

    /**
     * @param  array{token: string, email: string, password: string}  $data
     */
    public function reset(array $data): void
    {
        $status = Password::broker()->reset(
            [
                'email' => mb_strtolower(trim($data['email'])),
                'password' => $data['password'],
                'password_confirmation' => $data['password'],
                'token' => $data['token'],
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password, // cast "hashed"
                    'remember_token' => Str::random(60),
                ])->save();

                // Cabut semua token mobile agar perangkat lama harus login ulang.
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('passwords.token')]);
        }
    }
}
