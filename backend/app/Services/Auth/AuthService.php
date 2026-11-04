<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function loginWithSession(string $email, string $password, bool $remember, Request $request): User
    {
        $user = $this->verifyCredentials($email, $password);

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        $this->touchLastLogin($user);

        return $user;
    }

    /** @return array{0: User, 1: string} */
    public function issueToken(string $email, string $password, string $deviceName): array
    {
        $user = $this->verifyCredentials($email, $password);
        $token = $user->createToken($deviceName)->plainTextToken;
        $this->touchLastLogin($user);

        return [$user, $token];
    }

    public function logout(Request $request): void
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();

            return;
        }

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    private function verifyCredentials(string $email, string $password): User
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => __('auth.inactive')]);
        }

        return $user;
    }

    private function touchLastLogin(User $user): void
    {
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
    }
}
