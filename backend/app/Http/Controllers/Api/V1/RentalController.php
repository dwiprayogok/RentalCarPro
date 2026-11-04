<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\CancelRentalRequest;
use App\Http\Requests\Rental\HandoverRequest;
use App\Http\Requests\Rental\QuoteRentalRequest;
use App\Http\Requests\Rental\StoreRentalRequest;
use App\Http\Resources\RentalResource;
use App\Models\Rental;
use App\Queries\RentalIndexQuery;
use App\Services\Rental\RentalBilling;
use App\Services\Rental\RentalQuoteService;
use App\Services\Rental\RentalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RentalController extends Controller
{
    public function __construct(private readonly RentalService $rentals) {}

    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'view' => ['nullable', Rule::in(RentalIndexQuery::VIEWS)],
            'car_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }

    /** Daftar transaksi. view=semua|booking|aktif|terlambat|selesai|batal|belum_lunas. meta.counts per view. */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Rental::class);
        $filters = $this->filters($request);

        $base = RentalIndexQuery::base($filters);
        $rentals = RentalIndexQuery::applyView(clone $base, $filters['view'] ?? 'semua')
            ->with(['customer', 'car', 'driver'])
            ->orderByRaw("CASE WHEN status = 'aktif' THEN 0 WHEN status = 'booking' THEN 1 ELSE 2 END")
            ->orderByDesc('start_at')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return RentalResource::collection($rentals)->additional(['meta' => ['counts' => RentalIndexQuery::counts($base)]]);
    }

    /** Ekspor CSV (filter sama dengan daftar). Pemisah ";" agar langsung terbaca Excel berbahasa Indonesia. */
    public function export(Request $request, RentalBilling $billing): StreamedResponse
    {
        Gate::authorize('export', Rental::class);
        $filters = $this->filters($request);
        $view = $filters['view'] ?? 'semua';
        $query = RentalIndexQuery::applyView(RentalIndexQuery::base($filters), $view)->with(['customer', 'car', 'driver'])->orderBy('start_at');
        $tz = config('app.display_timezone');
        $fmt = fn ($d) => $d ? Carbon::instance($d)->setTimezone($tz)->format('Y-m-d H:i') : '';

        $filename = 'transaksi-'.$view.'-'.now($tz)->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query, $billing, $fmt) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 untuk Excel
            fputcsv($out, [
                'No. Nota', 'Status', 'Pelanggan', 'No. HP', 'Mobil', 'Plat', 'Layanan', 'Driver',
                'Mulai (WIB)', 'Kembali Rencana (WIB)', 'Kembali Aktual (WIB)', 'Hari', 'Tarif/Hari',
                'Sewa', 'Diskon', 'Denda', 'Biaya Tambahan', 'Total Tagihan', 'Terbayar', 'Sisa',
                'Estimasi Denda Berjalan', 'Status Bayar', 'Deposit Diminta', 'Deposit Ditahan',
            ], ';');

            $query->chunk(500, function ($rows) use ($out, $billing, $fmt) {
                foreach ($rows as $r) {
                    $b = $billing->summary($r);
                    fputcsv($out, [
                        $r->code, $r->isOverdue() ? 'Terlambat' : $r->status->label(), $r->customer->name, $r->customer->phone,
                        $r->car->display_name, $r->car->plate_number, $r->service_type->label(), $r->driver?->name ?? '',
                        $fmt($r->start_at), $fmt($r->planned_end_at), $fmt($r->returned_at), $r->billed_days, $r->daily_rate,
                        $r->rental_amount, $r->discount, $r->late_fee, $r->extra_fees_total, $r->total_amount, $r->paid_amount,
                        $b['balance'], $b['running_late_fee'], $b['payment_status_label'], $r->deposit_required, $r->deposit_held,
                    ], ';');
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function quote(QuoteRentalRequest $request, RentalQuoteService $quotes): JsonResponse
    {
        return response()->json(['data' => $quotes->quote($request->validated(), $request->user())]);
    }

    public function store(StoreRentalRequest $request): JsonResponse
    {
        $rental = $this->rentals->create($request->validated(), $request->user());

        return (new RentalResource($this->loadDetail($rental)))
            ->additional(['message' => "Sewa {$rental->code} berhasil dibuat."])
            ->response()->setStatusCode(201);
    }

    public function show(Rental $rental): RentalResource
    {
        Gate::authorize('view', $rental);

        return new RentalResource($this->loadDetail($rental));
    }

    public function handover(HandoverRequest $request, Rental $rental): RentalResource
    {
        $rental = $this->rentals->handover($rental, $request->validated(), $request->user());

        return (new RentalResource($this->loadDetail($rental)))
            ->additional(['message' => 'Serah terima tercatat. Sewa kini Aktif.']);
    }

    public function cancel(CancelRentalRequest $request, Rental $rental): RentalResource
    {
        $rental = $this->rentals->cancel($rental, $request->validated('reason'), $request->user());

        return (new RentalResource($this->loadDetail($rental)))
            ->additional(['message' => "Sewa {$rental->code} dibatalkan."]);
    }

    private function loadDetail(Rental $rental): Rental
    {
        return $rental->load(['customer', 'car', 'driver', 'payments.receivedBy', 'inspections', 'fees', 'createdBy', 'blacklistOverrideBy']);
    }
}
