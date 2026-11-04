<?php

namespace App\Services;

use App\Enums\CarStatus;
use App\Enums\RentalStatus;
use App\Models\Car;
use App\Models\Rental;
use App\Services\Rental\RentalBilling;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data dashboard. Angka uang dari ReportService (agregasi SQL); denda berjalan dari RentalBilling.
 */
class DashboardService
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly RentalBilling $billing,
        private readonly SettingService $settings,
    ) {}

    /** @return array<string, mixed> */
    public function data(): array
    {
        $tz = config('app.display_timezone');
        $now = Carbon::now($tz);
        $with = ['customer', 'car', 'driver'];

        $monthStart = $now->copy()->startOfMonth()->utc();
        $paidMonth = $this->reports->incomeBetween($monthStart, now());
        // Pembanding: periode yang sama di bulan lalu (tgl 1 s/d tanggal & jam yang sama).
        $prevStart = $now->copy()->subMonthNoOverflow()->startOfMonth()->utc();
        $prevEnd = $now->copy()->subMonthNoOverflow()->utc();
        $paidPrev = $this->reports->incomeBetween($prevStart, $prevEnd);

        $overdue = Rental::query()->overdue()->with($with)->orderBy('planned_end_at')->get();
        $runningLate = (int) $overdue->sum(fn (Rental $r) => $this->billing->runningLateFee($r)->total());
        $endOfToday = $now->copy()->endOfDay()->utc();

        $priority = Rental::query()->where('status', RentalStatus::Aktif->value)
            ->where('planned_end_at', '<=', $now->copy()->addDays(2)->endOfDay()->utc())
            ->with($with)->orderBy('planned_end_at')->limit(6)->get();
        $dueToday = Rental::query()->where('status', RentalStatus::Aktif->value)->whereBetween('planned_end_at', [now(), $endOfToday])->count();

        $carCounts = Car::query()->toBase()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $receivable = (int) Rental::query()->where('status', '!=', RentalStatus::Batal->value)
            ->whereColumn('paid_amount', '<', 'total_amount')->toBase()
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as s')->value('s');

        $reminders = $this->reminders();

        return [
            'generated_at' => $now->toIso8601String(),
            'banner' => $this->banner($overdue, $runningLate, $dueToday, $reminders),
            'kpi' => [
                'payments_month' => [
                    'amount' => $paidMonth,
                    'previous' => $paidPrev,
                    'change_pct' => $paidPrev > 0 ? round(($paidMonth - $paidPrev) / $paidPrev * 100, 1) : null,
                    'month_label' => $now->locale('id')->translatedFormat('F Y'),
                ],
                'active' => [
                    'count' => Rental::query()->where('status', RentalStatus::Aktif->value)->count(),
                    'booking' => Rental::query()->where('status', RentalStatus::Booking->value)->count(),
                ],
                'cars' => [
                    'available' => (int) ($carCounts[CarStatus::Tersedia->value] ?? 0),
                    'rented' => (int) ($carCounts[CarStatus::Disewa->value] ?? 0),
                    'service' => (int) ($carCounts[CarStatus::Servis->value] ?? 0),
                    'total' => (int) $carCounts->except(CarStatus::Nonaktif->value)->sum(),
                ],
                'need_return' => ['count' => $overdue->count() + $dueToday, 'overdue' => $overdue->count(), 'today' => $dueToday],
            ],
            'receivable' => $receivable,
            'running_late_total' => $runningLate,
            'cash_flow' => $this->reports->dailyIncome(7),
            'priority_returns' => $priority,
            'featured_cars' => $this->featuredCars(),
            'recent_rentals' => Rental::query()->with($with)->latest('created_at')->latest('id')->limit(5)->get(),
            'pickups_today' => Rental::query()->where('status', RentalStatus::Booking->value)
                ->whereBetween('start_at', [$now->copy()->startOfDay()->utc(), $endOfToday])->with($with)->orderBy('start_at')->get(),
            'reminders' => $reminders,
            'new_booking_requests' => DB::table('booking_requests')->whereNull('deleted_at')->where('status', 'baru')->count(),
        ];
    }

    /** Banner dinamis: prioritas keterlambatan > kembali hari ini > pengingat > kondisi normal. */
    private function banner(Collection $overdue, int $runningLate, int $dueToday, array $reminders): array
    {
        $rp = fn (int $v) => 'Rp '.number_format($v, 0, ',', '.');
        $urgent = collect($reminders)->where('overdue', true)->count();

        if ($overdue->isNotEmpty()) {
            $first = $overdue->first();

            return [
                'tone' => 'danger',
                'title' => $overdue->count().' sewa terlambat dikembalikan',
                'message' => "Paling lama: {$first->code} ({$first->car->display_name}, {$first->customer->name}). "
                    .($runningLate > 0 ? "Estimasi denda berjalan {$rp($runningLate)}." : 'Masih dalam masa toleransi.'),
                'action' => ['label' => 'Lihat yang terlambat', 'to' => '/transaksi?view=terlambat'],
            ];
        }
        if ($dueToday > 0) {
            return [
                'tone' => 'warning',
                'title' => "{$dueToday} mobil dijadwalkan kembali hari ini",
                'message' => 'Siapkan pemeriksaan kondisi dan penyelesaian pembayaran saat mobil kembali.',
                'action' => ['label' => 'Lihat jadwal', 'to' => '/transaksi?view=aktif'],
            ];
        }
        if ($urgent > 0) {
            return [
                'tone' => 'warning',
                'title' => "{$urgent} pengingat perawatan sudah lewat jatuh tempo",
                'message' => 'Periksa pajak, STNK, atau jadwal servis armada di bawah.',
                'action' => ['label' => 'Lihat armada', 'to' => '/armada'],
            ];
        }

        return [
            'tone' => 'success',
            'title' => 'Tidak ada keterlambatan hari ini',
            'message' => 'Semua sewa aktif masih sesuai jadwal.',
            'action' => null,
        ];
    }

    /**
     * Pengingat pajak/STNK (tanggal) dan servis (tanggal atau km) yang mendekati / lewat jatuh tempo.
     *
     * @return list<array<string, mixed>>
     */
    public function reminders(): array
    {
        $tz = config('app.display_timezone');
        $today = Carbon::now($tz)->startOfDay();
        $limit = $today->copy()->addDays($this->settings->int('reminder_days', 30))->toDateString();
        $km = $this->settings->int('reminder_km', 1000);

        $cars = Car::query()
            ->where('status', '!=', CarStatus::Nonaktif->value)
            ->where(fn ($q) => $q->where('tax_due_date', '<=', $limit)
                ->orWhere('stnk_due_date', '<=', $limit)
                ->orWhere('next_service_date', '<=', $limit)
                ->orWhereRaw('next_service_odometer - odometer <= ?', [$km]))
            ->get();

        $out = [];
        foreach ($cars as $car) {
            foreach (['tax_due_date' => 'pajak', 'stnk_due_date' => 'stnk', 'next_service_date' => 'servis'] as $col => $type) {
                if ($car->{$col} && $car->{$col}->toDateString() <= $limit) {
                    $days = (int) $today->diffInDays(Carbon::parse($car->{$col}->toDateString(), $tz), false);
                    $out[] = $this->reminder($car, $type, $days, null, $car->{$col}->toDateString());
                }
            }
            if ($car->next_service_odometer !== null && $car->next_service_odometer - $car->odometer <= $km
                && ! collect($out)->contains(fn ($r) => $r['car_id'] === $car->id && $r['type'] === 'servis')) {
                $out[] = $this->reminder($car, 'servis', null, $car->next_service_odometer - $car->odometer, null);
            }
        }

        usort($out, fn ($a, $b) => [$a['overdue'] ? 0 : 1, $a['days_left'] ?? 9999] <=> [$b['overdue'] ? 0 : 1, $b['days_left'] ?? 9999]);

        return $out;
    }

    private function reminder(Car $car, string $type, ?int $days, ?int $kmLeft, ?string $date): array
    {
        $labels = ['pajak' => 'Pajak tahunan', 'stnk' => 'Perpanjang STNK', 'servis' => 'Servis berkala'];
        $overdue = ($days !== null && $days < 0) || ($kmLeft !== null && $kmLeft < 0);

        return [
            'car_id' => $car->id,
            'car' => $car->display_name,
            'plate_number' => $car->plate_number,
            'type' => $type,
            'label' => $labels[$type],
            'due_date' => $date,
            'days_left' => $days,
            'km_left' => $kmLeft,
            'overdue' => $overdue,
        ];
    }

    /** Armada pilihan: tampil di landing & paling sering disewa 90 hari terakhir. */
    private function featuredCars(): Collection
    {
        return Car::query()
            ->with('photos')
            ->withCount(['rentals as recent_rentals_count' => fn ($q) => $q->where('start_at', '>=', now()->subDays(90))
                ->where('status', '!=', RentalStatus::Batal->value)])
            ->where('status', '!=', CarStatus::Nonaktif->value)
            ->orderByDesc('is_published')
            ->orderByDesc('recent_rentals_count')
            ->orderBy('rate_self_drive')
            ->limit(3)->get();
    }
}
