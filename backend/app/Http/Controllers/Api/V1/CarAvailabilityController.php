<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Rental;
use App\Services\Rental\AvailabilityService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Kalender ketersediaan per mobil: daftar blok sewa (booking/aktif) di rentang tanggal WIB.
 * Rentang maksimal 93 hari.
 */
class CarAvailabilityController extends Controller
{
    public function __invoke(Request $request, Car $car, AvailabilityService $availability): JsonResponse
    {
        Gate::authorize('view', $car);
        $tz = config('app.display_timezone');

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($data['from'], $tz)->startOfDay();
        $to = Carbon::parse($data['to'], $tz)->endOfDay();
        abort_if($from->diffInDays($to) > 93, 422, 'Rentang kalender maksimal 93 hari.');

        $showCustomer = $request->user()->can(Permissions::RENTAL_VIEW);
        $buffer = $availability->bufferMinutes();

        $blocks = $availability->carConflicts($car->id, $from->copy()->utc()->addMinutes($buffer), $to->copy()->utc()->subMinutes($buffer))
            ->map(function (Rental $r) use ($availability, $tz, $showCustomer, $buffer) {
                $end = $availability->effectiveEnd($r);

                return [
                    'rental_id' => $r->id,
                    'code' => $r->code,
                    'status' => $r->status->value,
                    'is_overdue' => $r->isOverdue(),
                    'customer_name' => $showCustomer ? $r->customer?->name : null,
                    'start_at' => $r->start_at->copy()->setTimezone($tz)->toIso8601String(),
                    'end_at' => $end->copy()->setTimezone($tz)->toIso8601String(),
                    'blocked_until' => $end->copy()->addMinutes($buffer)->setTimezone($tz)->toIso8601String(),
                ];
            })->values();

        return response()->json([
            'data' => $blocks,
            'meta' => [
                'car_id' => $car->id,
                'car_status' => $car->status->value,
                'buffer_minutes' => $buffer,
                'from' => $data['from'],
                'to' => $data['to'],
            ],
        ]);
    }
}
