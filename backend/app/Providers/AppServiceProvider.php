<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingService::class);
    }

    public function boot(): void
    {
        // Tangkap lazy loading & atribut tak dikenal saat development.
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Owner selalu lolos semua Gate/Policy (termasuk permission baru yang belum di-seed).
        Gate::before(fn (User $user) => $user->hasRole(UserRole::Owner->value) ? true : null);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // 5 percobaan login per menit per kombinasi email + IP, dan 20 per menit per IP.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by('login-ip|'.$request->ip()),
        ]);

        // Lupa/reset kata sandi: 3 per menit per email + IP, 10 per jam per IP.
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(3)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perHour(10)->by('pwd-ip|'.$request->ip()),
        ]);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
