<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CarStatus;
use App\Enums\RentalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\RentalResource;
use App\Models\Car;
use App\Models\Rental;
use App\Services\Rental\RentalBilling;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Ringkasan operasional hari ini (WIB). Estimasi denda berjalan dihitung server.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, RentalBilling $billing): JsonResponse
    {
        abort_unless($request->user()->can(Permissions::DASHBOARD_VIEW), 403);

        $tz = config('app.display_timezone');
        $dayStart = Carbon::now($tz)->startOfDay()->utc();
        $dayEnd = Carbon::now($tz)->endOfDay()->utc();
        $with = ['customer', 'car', 'driver'];

        $overdue = Rental::query()->overdue()->with($with)->orderBy('planned_end_at')->get();
        $returnsToday = Rental::query()->where('status', RentalStatus::Aktif->value)
            ->whereBetween('planned_end_at', [now(), $dayEnd])->with($with)->orderBy('planned_end_at')->get();
        $pickupsToday = Rental::query()->where('status', RentalStatus::Booking->value)
            ->whereBetween('start_at', [$dayStart, $dayEnd])->with($with)->orderBy('start_at')->get();

        $open = Rental::query()->whereIn('status', [RentalStatus::Booking->value, RentalStatus::Aktif->value, RentalStatus::Selesai->value])
            ->whereColumn('paid_amount', '<', 'total_amount');

        $runningLateTotal = $overdue->sum(fn (Rental $r) => $billing->runningLateFee($r)->total());

        return response()->json(['data' => [
            'counts' => [
                'aktif' => Rental::query()->where('status', RentalStatus::Aktif->value)->count(),
                'terlambat' => $overdue->count(),
                'kembali_hari_ini' => $returnsToday->count(),
                'ambil_hari_ini' => $pickupsToday->count(),
                'belum_lunas' => (clone $open)->count(),
                'mobil_tersedia' => Car::query()->where('status', CarStatus::Tersedia->value)->count(),
                'mobil_total' => Car::query()->where('status', '!=', CarStatus::Nonaktif->value)->count(),
            ],
            'receivable' => (int) (clone $open)->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as s')->value('s'),
            'running_late_total' => $runningLateTotal,
            'overdue' => RentalResource::collection($overdue->take(10)),
            'returns_today' => RentalResource::collection($returnsToday->take(10)),
            'pickups_today' => RentalResource::collection($pickupsToday->take(10)),
        ]]);
    }
}
