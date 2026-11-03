<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\SaveDriverRequest;
use App\Http\Resources\DriverResource;
use App\Models\Driver;
use App\Services\DriverService;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DriverController extends Controller
{
    public function __construct(private readonly DriverService $drivers) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Driver::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $drivers = Driver::query()
            ->when($filters['search'] ?? null, fn (Builder $q, $s) => $q->where(
                fn ($q) => $q->where('name', 'like', Search::like($s))->orWhere('phone', 'like', Search::like(preg_replace('/\D/', '', $s) ?: $s)),
            ))
            ->when(array_key_exists('is_active', $filters), fn (Builder $q) => $q->where('is_active', (bool) $filters['is_active']))
            ->withCount('rentals')
            ->orderByDesc('is_active')->orderBy('name')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return DriverResource::collection($drivers);
    }

    public function store(SaveDriverRequest $request): JsonResponse
    {
        return (new DriverResource($this->drivers->create($request->validated())))
            ->additional(['message' => 'Driver berhasil ditambahkan.'])
            ->response()->setStatusCode(201);
    }

    public function show(Driver $driver): DriverResource
    {
        Gate::authorize('view', $driver);

        return new DriverResource($driver->loadCount('rentals'));
    }

    public function update(SaveDriverRequest $request, Driver $driver): DriverResource
    {
        return (new DriverResource($this->drivers->update($driver, $request->validated())))
            ->additional(['message' => 'Data driver berhasil diperbarui.']);
    }

    public function destroy(Driver $driver): JsonResponse
    {
        Gate::authorize('delete', $driver);
        $this->drivers->delete($driver);

        return response()->json(['message' => 'Driver berhasil dihapus.']);
    }
}
