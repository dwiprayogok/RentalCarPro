<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CarResource;
use App\Http\Resources\RentalResource;
use App\Services\DashboardService;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        abort_unless($request->user()->can(Permissions::DASHBOARD_VIEW), 403);

        $d = $dashboard->data();
        foreach (['priority_returns', 'recent_rentals', 'pickups_today'] as $k) {
            $d[$k] = RentalResource::collection($d[$k]);
        }
        $d['featured_cars'] = $d['featured_cars']->map(fn ($car) => (new CarResource($car))->toArray($request) + ['recent_rentals' => $car->recent_rentals_count]);

        return response()->json(['data' => $d]);
    }
}
