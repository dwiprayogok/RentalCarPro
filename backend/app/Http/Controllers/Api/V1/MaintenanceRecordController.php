<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MaintenanceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\SaveMaintenanceRecordRequest;
use App\Http\Resources\MaintenanceRecordResource;
use App\Models\MaintenanceRecord;
use App\Services\MaintenanceService;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MaintenanceRecordController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenance) {}

    /** Filter: car_id, type, from, to (tanggal pengerjaan), search. meta.total_cost sesuai filter. */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', MaintenanceRecord::class);

        $filters = $request->validate([
            'car_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(MaintenanceType::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = MaintenanceRecord::query()
            ->when($filters['car_id'] ?? null, fn (Builder $q, $id) => $q->where('car_id', $id))
            ->when($filters['type'] ?? null, fn (Builder $q, $t) => $q->where('type', $t))
            ->when($filters['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('performed_at', '>=', $d))
            ->when($filters['to'] ?? null, fn (Builder $q, $d) => $q->whereDate('performed_at', '<=', $d))
            ->when($filters['search'] ?? null, fn (Builder $q, $s) => $q->where(
                fn ($q) => $q->where('title', 'like', Search::like($s))->orWhere('vendor', 'like', Search::like($s)),
            ));

        $totalCost = (int) (clone $query)->sum('cost');

        $records = $query->with('car')
            ->orderByDesc('performed_at')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return MaintenanceRecordResource::collection($records)->additional(['meta' => ['total_cost' => $totalCost]]);
    }

    public function store(SaveMaintenanceRecordRequest $request): JsonResponse
    {
        $record = $this->maintenance->create($request->validated(), $request->user());

        return (new MaintenanceRecordResource($record->load('car')))
            ->additional(['message' => 'Catatan perawatan berhasil ditambahkan.'])
            ->response()->setStatusCode(201);
    }

    public function show(MaintenanceRecord $maintenance): MaintenanceRecordResource
    {
        Gate::authorize('view', $maintenance);

        return new MaintenanceRecordResource($maintenance->load('car'));
    }

    public function update(SaveMaintenanceRecordRequest $request, MaintenanceRecord $maintenance): MaintenanceRecordResource
    {
        $this->maintenance->update($maintenance, $request->validated());

        return (new MaintenanceRecordResource($maintenance->fresh()->load('car')))
            ->additional(['message' => 'Catatan perawatan berhasil diperbarui.']);
    }

    public function destroy(MaintenanceRecord $maintenance): JsonResponse
    {
        Gate::authorize('delete', $maintenance);
        $this->maintenance->delete($maintenance);

        return response()->json(['message' => 'Catatan perawatan berhasil dihapus.']);
    }
}
