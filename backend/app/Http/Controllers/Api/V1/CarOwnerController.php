<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\SaveCarOwnerRequest;
use App\Http\Resources\CarOwnerResource;
use App\Models\CarOwner;
use App\Models\OwnerSettlement;
use App\Services\Owner\SettlementService;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Pemilik mobil titipan. */
class CarOwnerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', CarOwner::class);
        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $owners = CarOwner::query()
            ->when($f['search'] ?? null, fn (Builder $q, $s) => $q->where(fn ($q) => $q->where('name', 'like', Search::like($s))
                ->orWhere('phone', 'like', Search::like(preg_replace('/\D/', '', $s) ?: $s))))
            ->when(($f['status'] ?? null) === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when(($f['status'] ?? null) === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->withCount('cars')
            ->withSum(['settlements as unpaid_total' => fn ($q) => $q->whereNull('paid_at')], 'owner_amount')
            ->withCount(['settlements as unpaid_count' => fn ($q) => $q->whereNull('paid_at')])
            ->withMax('settlements as last_period_to', 'period_to')
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate($f['per_page'] ?? 20)->withQueryString();

        return CarOwnerResource::collection($owners);
    }

    public function store(SaveCarOwnerRequest $request): JsonResponse
    {
        $owner = CarOwner::create($request->validated() + ['created_by' => $request->user()->id]);

        return (new CarOwnerResource($owner->loadCount('cars')))
            ->additional(['message' => "Pemilik {$owner->name} berhasil ditambahkan."])
            ->response()->setStatusCode(201);
    }

    public function show(CarOwner $owner): CarOwnerResource
    {
        Gate::authorize('view', $owner);

        return new CarOwnerResource($owner->load(['cars' => fn ($q) => $q->orderBy('brand')->orderBy('model')])->loadCount('cars'));
    }

    public function update(SaveCarOwnerRequest $request, CarOwner $owner): CarOwnerResource
    {
        $owner->update($request->validated());

        return (new CarOwnerResource($owner->fresh()->loadCount('cars')))
            ->additional(['message' => "Data pemilik {$owner->name} berhasil diperbarui."]);
    }

    public function destroy(CarOwner $owner): JsonResponse
    {
        Gate::authorize('delete', $owner);
        if ($owner->cars()->exists()) {
            throw ValidationException::withMessages(['owner' => 'Pemilik masih punya mobil titipan. Ubah kepemilikan mobilnya dulu, atau nonaktifkan pemilik ini.']);
        }
        if ($owner->settlements()->exists()) {
            throw ValidationException::withMessages(['owner' => 'Pemilik sudah punya riwayat setoran. Nonaktifkan saja agar riwayat tetap tersimpan.']);
        }
        $owner->delete();

        return response()->json(['message' => "Pemilik {$owner->name} berhasil dihapus."]);
    }

    /** Pratinjau setoran (tidak disimpan). */
    public function preview(Request $request, CarOwner $owner, SettlementService $settlements): JsonResponse
    {
        Gate::authorize('create', OwnerSettlement::class);
        $f = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [], ['from' => 'tanggal awal', 'to' => 'tanggal akhir']);

        return response()->json(['data' => $settlements->calculate($owner, $f['from'], $f['to'])]);
    }
}
