<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CarStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Car\SaveCarRequest;
use App\Http\Resources\CarResource;
use App\Models\Car;
use App\Services\CarService;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CarController extends Controller
{
    public function __construct(private readonly CarService $cars) {}

    /**
     * Filter: search (merk/tipe/plat), status, transmission, is_published.
     * Urutan: sort=newest|name|rate_asc|rate_desc. meta.counts = jumlah per status (untuk chip filter).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Car::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(CarStatus::class)],
            'transmission' => ['nullable', 'in:matic,manual'],
            'is_published' => ['nullable', 'boolean'],
            'owner' => ['nullable', 'regex:/^(\d+|sendiri|titipan)$/'],
            'sort' => ['nullable', 'in:newest,name,rate_asc,rate_desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $base = Car::query()
            ->when($filters['search'] ?? null, function (Builder $q, string $s) {
                $like = Search::like($s);
                $plate = Search::like(preg_replace('/\s+/', '', $s));
                $q->where(fn (Builder $q) => $q->where('brand', 'like', $like)
                    ->orWhere('model', 'like', $like)
                    ->orWhereRaw("CONCAT(brand, ' ', model) like ?", [$like])
                    ->orWhere('plate_number', 'like', $like)
                    ->orWhereRaw("REPLACE(plate_number, ' ', '') like ?", [$plate]));
            })
            ->when($filters['transmission'] ?? null, fn (Builder $q, $t) => $q->where('transmission', $t))
            ->when($filters['owner'] ?? null, fn (Builder $q, $o) => match ($o) {
                'sendiri' => $q->whereNull('owner_id'),
                'titipan' => $q->whereNotNull('owner_id'),
                default => $q->where('owner_id', (int) $o),
            })
            ->when(array_key_exists('is_published', $filters), fn (Builder $q) => $q->where('is_published', (bool) $filters['is_published']));

        $counts = (clone $base)->toBase()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $cars = (clone $base)
            ->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->where('status', $s))
            ->with(['photos', 'owner'])
            ->tap(fn (Builder $q) => match ($filters['sort'] ?? 'newest') {
                'name' => $q->orderBy('brand')->orderBy('model'),
                'rate_asc' => $q->orderBy('rate_self_drive'),
                'rate_desc' => $q->orderByDesc('rate_self_drive'),
                default => $q->orderByDesc('created_at')->orderByDesc('id'),
            })
            ->paginate($filters['per_page'] ?? 12)
            ->withQueryString();

        return CarResource::collection($cars)->additional(['meta' => [
            'counts' => ['semua' => (int) $counts->sum()] + collect(CarStatus::values())
                ->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
        ]]);
    }

    public function store(SaveCarRequest $request): JsonResponse
    {
        $car = $this->cars->create($request->validated(), $request->user());

        return (new CarResource($car->load(['photos', 'owner'])))
            ->additional(['message' => 'Armada berhasil ditambahkan.'])
            ->response()->setStatusCode(201);
    }

    public function show(Car $car): CarResource
    {
        Gate::authorize('view', $car);

        return new CarResource($car->load(['photos', 'owner'])->loadCount('rentals'));
    }

    public function update(SaveCarRequest $request, Car $car): CarResource
    {
        $this->cars->update($car, $request->validated());

        return (new CarResource($car->fresh()->load(['photos', 'owner'])))
            ->additional(['message' => 'Data armada berhasil diperbarui.']);
    }

    public function destroy(Car $car): JsonResponse
    {
        Gate::authorize('delete', $car);
        $this->cars->delete($car);

        return response()->json(['message' => 'Armada berhasil dihapus.']);
    }
}
