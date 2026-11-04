<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RiskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\SaveCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\RentalSummaryResource;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Support\Phone;
use App\Support\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    /**
     * Pencarian: nama, nomor HP (08.../+62...), NIK lengkap 16 digit (via hash),
     * atau 4 digit terakhir NIK. Filter: risk_status. meta.counts per status risiko.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Customer::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'risk_status' => ['nullable', Rule::enum(RiskStatus::class)],
            'sort' => ['nullable', 'in:newest,name'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $base = Customer::query()->when($filters['search'] ?? null, function (Builder $q, string $s) {
            $digits = preg_replace('/\D/', '', $s);
            $q->where(function (Builder $q) use ($s, $digits) {
                $q->where('name', 'like', Search::like($s));

                if (strlen($digits) === 16) {
                    $q->orWhere('nik_hash', Customer::hashNik($digits));
                }
                if (strlen($digits) === 4) {
                    $q->orWhere('nik_last4', $digits);
                }
                if (strlen($digits) >= 4) {
                    $phone = Phone::normalize($s);
                    $q->orWhere('phone', 'like', Search::like($digits))
                        ->when($phone, fn ($q) => $q->orWhere('phone', 'like', Search::like($phone)));
                }
            });
        });

        $counts = (clone $base)->toBase()->selectRaw('risk_status, COUNT(*) as total')->groupBy('risk_status')->pluck('total', 'risk_status');

        $customers = (clone $base)
            ->when($filters['risk_status'] ?? null, fn (Builder $q, $r) => $q->where('risk_status', $r))
            ->withCount('rentals')
            ->tap(fn (Builder $q) => ($filters['sort'] ?? 'newest') === 'name'
                ? $q->orderBy('name')
                : $q->orderByDesc('created_at')->orderByDesc('id'))
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return CustomerResource::collection($customers)->additional(['meta' => [
            'counts' => ['semua' => (int) $counts->sum()] + collect(RiskStatus::values())
                ->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
        ]]);
    }

    public function store(SaveCustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->create($request->validated(), $request->user());

        return (new CustomerResource($customer->loadCount('rentals')))
            ->additional(['message' => 'Pelanggan berhasil ditambahkan.'])
            ->response()->setStatusCode(201);
    }

    public function show(Customer $customer): CustomerResource
    {
        Gate::authorize('view', $customer);

        return new CustomerResource($customer->loadCount('rentals'));
    }

    public function update(SaveCustomerRequest $request, Customer $customer): CustomerResource
    {
        $this->customers->update($customer, $request->validated(), $request->user());

        return (new CustomerResource($customer->fresh()->loadCount('rentals')))
            ->additional(['message' => 'Data pelanggan berhasil diperbarui.']);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        Gate::authorize('delete', $customer);
        $this->customers->delete($customer);

        return response()->json(['message' => 'Pelanggan berhasil dihapus.']);
    }

    /** Riwayat sewa pelanggan (terbaru dulu). */
    public function rentals(Request $request, Customer $customer): AnonymousResourceCollection
    {
        Gate::authorize('viewRentals', $customer);

        return RentalSummaryResource::collection(
            $customer->rentals()->with('car')->latest('start_at')
                ->paginate(max(1, min(50, $request->integer('per_page', 10))))
        );
    }
}
