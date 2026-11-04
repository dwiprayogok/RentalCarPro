<?php

namespace Tests\Feature\Rental;

use App\Enums\ServiceType;
use App\Models\Car;
use App\Models\Holiday;
use App\Services\Rental\PricingService;
use App\Services\SettingService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PricingTest extends TestCase
{
    private function pricing(): PricingService
    {
        return app(PricingService::class);
    }

    public static function durations(): array
    {
        // menit → hari (toleransi default 120 menit)
        return [
            '1 jam = 1 hari minimum' => [60, 1],
            'tepat 24 jam' => [1440, 1],
            '26 jam (dalam toleransi)' => [1560, 1],
            '26 jam 1 menit' => [1561, 2],
            'tepat 48 jam' => [2880, 2],
            '50 jam' => [3000, 2],
            '50 jam 1 menit' => [3001, 3],
            '72 jam' => [4320, 3],
        ];
    }

    #[DataProvider('durations')]
    public function test_duration_is_rounded_per_24_hours_after_grace(int $minutes, int $days): void
    {
        $start = Carbon::parse('2026-10-21 08:00', 'UTC');
        $this->assertSame($days, $this->pricing()->billedDays($start, $start->copy()->addMinutes($minutes)));
    }

    public function test_grace_period_comes_from_settings(): void
    {
        app(SettingService::class)->update(['grace_period_minutes' => 0]);
        $start = Carbon::parse('2026-10-21 08:00', 'UTC');

        $this->assertSame(2, $this->pricing()->billedDays($start, $start->copy()->addMinutes(1441)));
    }

    public function test_quote_uses_service_rate(): void
    {
        $car = Car::factory()->create(['rate_self_drive' => 550000, 'rate_with_driver' => 750000]);
        $start = Carbon::parse('2026-10-21 08:00', 'UTC');

        $self = $this->pricing()->quote($car, ServiceType::LepasKunci, $start, $start->copy()->addDays(2));
        $this->assertSame(2, $self->billedDays);
        $this->assertSame(1100000, $self->rentalAmount);

        $driver = $this->pricing()->quote($car, ServiceType::DenganDriver, $start, $start->copy()->addDays(2));
        $this->assertSame(1500000, $driver->rentalAmount);
    }

    public function test_weekend_and_holiday_rates_apply_per_day_when_enabled(): void
    {
        $car = Car::factory()->create(['rate_self_drive' => 500000, 'weekend_rate_self_drive' => 650000]);
        // Jumat 23 Okt 2026 10.00 WIB, 4 hari: Jum, Sab, Min, Sen(libur fiktif)
        $start = Carbon::parse('2026-10-23 10:00', 'Asia/Jakarta')->utc();
        $end = $start->copy()->addDays(4);

        $off = $this->pricing()->quote($car, ServiceType::LepasKunci, $start, $end);
        $this->assertSame(2000000, $off->rentalAmount, 'Nonaktif: semua tarif biasa');

        app(SettingService::class)->update(['weekend_rate_enabled' => true]);
        Holiday::create(['date' => '2026-10-26', 'name' => 'Libur fiktif']);

        $on = $this->pricing()->quote($car, ServiceType::LepasKunci, $start, $end);
        $this->assertSame(500000 + 650000 * 3, $on->rentalAmount);
        $this->assertSame([false, true, true, true], array_column($on->lines, 'special'));
        $this->assertSame('2026-10-23', $on->lines[0]['date']);
    }

    public static function lateCases(): array
    {
        // Tarif Rp 550.000, rencana kembali 10.00 WIB, toleransi 2 jam, denda Rp 50.000/jam
        return [
            'tidak terlambat' => ['2026-10-22 09:59', 0, 0, 0],
            '11.30: dalam toleransi' => ['2026-10-22 11:30', 0, 0, 0],
            '13.10: 4 jam berjalan' => ['2026-10-22 13:10', 0, 4, 200000],
            '22.00: 12 jam dibatasi tarif harian' => ['2026-10-22 22:00', 0, 12, 550000],
            'besok 13.00: +1 hari + 3 jam' => ['2026-10-23 13:00', 1, 3, 150000],
        ];
    }

    #[DataProvider('lateCases')]
    public function test_late_fee_examples(string $returned, int $extraDays, int $hours, int $hourlyFee): void
    {
        $planned = Carbon::parse('2026-10-22 10:00', 'Asia/Jakarta');
        $fee = $this->pricing()->lateFee($planned, Carbon::parse($returned, 'Asia/Jakarta'), 550000);

        $this->assertSame($extraDays, $fee->extraDays);
        $this->assertSame($hours, $fee->chargedHours);
        $this->assertSame($hourlyFee, $fee->hourlyFee);
        $this->assertSame($extraDays * 550000 + $hourlyFee, $fee->total());
    }
}
