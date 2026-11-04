<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Car;
use App\Models\CarPhoto;
use App\Models\Holiday;
use App\Models\Rental;
use App\Models\Setting;
use App\Models\User;
use App\Services\PublicCatalog;
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
        $this->registerPublicCacheInvalidation();
    }

    /** Data yang tampil di landing berubah → buang cache publik & revalidate landing. */
    private function registerPublicCacheInvalidation(): void
    {
        $bump = fn (string $reason) => fn () => app(PublicCatalog::class)->invalidate($reason);

        Car::saved($bump('car'));
        Car::deleted($bump('car'));
        Car::restored($bump('car'));
        Holiday::saved($bump('holiday'));
        Holiday::deleted($bump('holiday'));
        CarPhoto::saved($bump('photo'));
        CarPhoto::deleted($bump('photo'));
        Setting::saved(function (Setting $s) {
            if ($s->is_public) {
                app(PublicCatalog::class)->invalidate('setting');
            }
        });
        // Jadwal sewa memengaruhi kalender ketersediaan publik.
        Rental::saved(function (Rental $r) {
            if ($r->wasRecentlyCreated || $r->wasChanged(['status', 'start_at', 'planned_end_at', 'returned_at', 'car_id'])) {
                app(PublicCatalog::class)->invalidate('rental');
            }
        });
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

        // Public API (landing): per IP.
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(config('rentalpro.public_rate_limit', 120))->by('pub|'.$request->ip()));

        // Form permintaan sewa: per IP per 10 menit & per nomor HP per jam (anti-spam).
        RateLimiter::for('public-booking', fn (Request $request) => [
            Limit::perMinutes(10, config('rentalpro.booking_rate_ip', 5))->by('book-ip|'.$request->ip()),
            Limit::perHour(config('rentalpro.booking_rate_phone', 3))->by('book-phone|'.preg_replace('/\D/', '', (string) $request->input('phone'))),
        ]);
    }
}
