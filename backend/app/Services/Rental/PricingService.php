<?php

namespace App\Services\Rental;

use App\Enums\ServiceType;
use App\Models\Car;
use App\Models\Holiday;
use App\Services\SettingService;
use App\Support\Rental\LateFee;
use App\Support\Rental\PriceQuote;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Semua perhitungan uang untuk sewa. Murni (tanpa menulis DB) sehingga dipakai
 * baik untuk ringkasan real-time (quote) maupun saat menyimpan.
 *
 * Aturan:
 * - Durasi dibulatkan ke atas per 24 jam setelah dikurangi masa toleransi, minimal 1 hari.
 *   hari = max(1, ceil((menit - toleransi) / 1440)).
 * - Tarif per hari = tarif layanan (lepas kunci / dengan driver). Bila tarif akhir pekan aktif
 *   dan mobil punya tarif khusus, hari yang jatuh di akhir pekan / tanggal libur (WIB) memakai tarif khusus.
 * - Denda keterlambatan: dihitung dari jam kembali rencana. Bila terlambat <= toleransi: 0.
 *   Setiap 24 jam penuh = 1 hari sewa tambahan (tarif harian). Sisa jam dihitung per jam berjalan
 *   (dibulatkan ke atas) x denda per jam, maksimal tarif harian x late_fee_cap_days.
 */
class PricingService
{
    public function __construct(private readonly SettingService $settings) {}

    public function dailyRate(Car $car, ServiceType $service): int
    {
        return $service === ServiceType::DenganDriver ? $car->rate_with_driver : $car->rate_self_drive;
    }

    public function billedDays(CarbonInterface $start, CarbonInterface $end, ?int $graceMinutes = null): int
    {
        $grace = $graceMinutes ?? $this->settings->int('grace_period_minutes', 120);
        $minutes = max(0, (int) $start->diffInMinutes($end, false));

        return max(1, (int) ceil(max(0, $minutes - $grace) / 1440));
    }

    public function quote(Car $car, ServiceType $service, CarbonInterface $start, CarbonInterface $end): PriceQuote
    {
        $grace = $this->settings->int('grace_period_minutes', 120);
        $days = $this->billedDays($start, $end, $grace);
        $base = $this->dailyRate($car, $service);

        $special = $service === ServiceType::DenganDriver ? $car->weekend_rate_with_driver : $car->weekend_rate_self_drive;
        $useSpecial = $this->settings->bool('weekend_rate_enabled') && $special;
        $weekendDays = array_map('intval', (array) $this->settings->get('weekend_days', [6, 7]));
        $tz = config('app.display_timezone');

        $startLocal = Carbon::instance($start)->setTimezone($tz);
        $holidays = $useSpecial
            ? Holiday::query()
                ->whereBetween('date', [$startLocal->toDateString(), $startLocal->copy()->addDays($days)->toDateString()])
                ->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all()
            : [];

        $lines = [];
        $total = 0;
        for ($i = 0; $i < $days; $i++) {
            $day = $startLocal->copy()->addDays($i);
            $isSpecial = $useSpecial && (in_array($day->isoWeekday(), $weekendDays, true) || in_array($day->toDateString(), $holidays, true));
            $rate = $isSpecial ? (int) $special : $base;
            $lines[] = ['date' => $day->toDateString(), 'weekday' => $day->isoWeekday(), 'rate' => $rate, 'special' => $isSpecial];
            $total += $rate;
        }

        return new PriceQuote(
            billedDays: $days,
            durationMinutes: max(0, (int) $start->diffInMinutes($end, false)),
            graceMinutes: $grace,
            baseRate: $base,
            lines: $lines,
            rentalAmount: $total,
        );
    }

    public function lateFee(CarbonInterface $plannedEnd, CarbonInterface $actualEnd, int $dailyRate): LateFee
    {
        $late = (int) $plannedEnd->diffInMinutes($actualEnd, false);
        if ($late <= 0) {
            return LateFee::none();
        }

        $grace = $this->settings->int('grace_period_minutes', 120);
        if ($late <= $grace) {
            return new LateFee($late, true, 0, 0, 0, 0, false);
        }

        $perHour = $this->settings->int('late_fee_per_hour', 50000);
        $cap = $dailyRate * max(1, $this->settings->int('late_fee_cap_days', 1));

        $extraDays = intdiv($late, 1440);
        $remainder = $late - $extraDays * 1440;
        $hours = (int) ceil($remainder / 60);
        $hourly = $hours * $perHour;
        $capped = $hourly > $cap;

        return new LateFee(
            lateMinutes: $late,
            withinGrace: false,
            extraDays: $extraDays,
            extraDaysAmount: $extraDays * $dailyRate,
            chargedHours: $hours,
            hourlyFee: $capped ? $cap : $hourly,
            capped: $capped,
        );
    }
}
