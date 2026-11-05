<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\PaySettlementRequest;
use App\Http\Requests\Owner\StoreSettlementRequest;
use App\Http\Resources\OwnerSettlementResource;
use App\Models\CarOwner;
use App\Models\OwnerSettlement;
use App\Services\Owner\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Setoran bagi hasil ke pemilik mobil titipan. */
class OwnerSettlementController extends Controller
{
    public function __construct(private readonly SettlementService $settlements) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', OwnerSettlement::class);
        $f = $request->validate([
            'owner_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:belum_dibayar,dibayar'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = OwnerSettlement::query()
            ->when($f['owner_id'] ?? null, fn ($q, $id) => $q->where('owner_id', $id))
            ->when(($f['status'] ?? null) === 'belum_dibayar', fn ($q) => $q->whereNull('paid_at'))
            ->when(($f['status'] ?? null) === 'dibayar', fn ($q) => $q->whereNotNull('paid_at'));

        $unpaid = OwnerSettlement::query()->whereNull('paid_at')
            ->when($f['owner_id'] ?? null, fn ($q, $id) => $q->where('owner_id', $id))
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(owner_amount), 0) as total')->first();

        $page = $query->with(['owner', 'payer', 'creator'])
            ->orderByDesc('period_to')->orderByDesc('id')
            ->paginate($f['per_page'] ?? 20)->withQueryString();

        return OwnerSettlementResource::collection($page)->additional(['meta' => [
            'unpaid' => ['count' => (int) $unpaid->n, 'total' => (int) $unpaid->total],
        ]]);
    }

    public function store(StoreSettlementRequest $request): JsonResponse
    {
        $owner = CarOwner::findOrFail($request->integer('owner_id'));
        $s = $this->settlements->create($owner, $request->input('from'), $request->input('to'), $request->user(), $request->input('notes'));

        return (new OwnerSettlementResource($s->load(['owner', 'creator'])))
            ->additional(['message' => "Setoran {$s->code} untuk {$owner->name} berhasil dibuat."])
            ->response()->setStatusCode(201);
    }

    public function show(OwnerSettlement $settlement): OwnerSettlementResource
    {
        Gate::authorize('view', $settlement);

        return new OwnerSettlementResource($settlement->load(['owner', 'payer', 'creator']));
    }

    public function pay(PaySettlementRequest $request, OwnerSettlement $settlement): OwnerSettlementResource
    {
        $s = $this->settlements->pay($settlement, $request->validated(), $request->user(), $request->file('proof'));

        return (new OwnerSettlementResource($s->load(['owner', 'payer', 'creator'])))
            ->additional(['message' => "Setoran {$s->code} ditandai sudah dibayar."]);
    }

    public function destroy(OwnerSettlement $settlement): JsonResponse
    {
        Gate::authorize('delete', $settlement);
        $this->settlements->delete($settlement);

        return response()->json(['message' => "Setoran {$settlement->code} dibatalkan."]);
    }

    public function pdf(OwnerSettlement $settlement): Response
    {
        Gate::authorize('view', $settlement);

        return $this->settlements->pdf($settlement);
    }

    public function printUrl(OwnerSettlement $settlement): JsonResponse
    {
        Gate::authorize('view', $settlement);

        return response()->json(['data' => ['url' => $this->settlements->printUrl($settlement)]]);
    }

    /** Slip PDF lewat signed URL (dibagikan ke pemilik 7 hari, cetak 2 menit). */
    public function shared(OwnerSettlement $settlement): Response
    {
        $res = $this->settlements->pdf($settlement);
        $res->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $res->headers->set('X-Robots-Tag', 'noindex');

        return $res;
    }

    public function proof(OwnerSettlement $settlement): StreamedResponse
    {
        abort_unless($settlement->paid_proof_path, 404);

        return Storage::disk(SettlementService::DISK)->response($settlement->paid_proof_path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
