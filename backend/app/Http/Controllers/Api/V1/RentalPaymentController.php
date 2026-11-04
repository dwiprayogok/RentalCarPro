<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\StorePaymentRequest;
use App\Http\Resources\RentalResource;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Services\Rental\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RentalPaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function store(StorePaymentRequest $request, Rental $rental): JsonResponse
    {
        $payment = $this->payments->record($rental, $request->safe()->except('proof'), $request->user(), $request->file('proof'));

        return (new RentalResource($rental->fresh()->load(['customer', 'car', 'driver', 'payments.receivedBy', 'inspections'])))
            ->additional(['message' => $payment->type->label().' sebesar Rp '.number_format($payment->amount, 0, ',', '.').' tercatat.'])
            ->response()->setStatusCode(201);
    }

    public function destroy(Rental $rental, RentalPayment $payment): RentalResource
    {
        Gate::authorize('deletePayment', $rental);
        $this->payments->delete($payment);

        return (new RentalResource($rental->fresh()->load(['customer', 'car', 'driver', 'payments.receivedBy', 'inspections'])))
            ->additional(['message' => 'Pembayaran dihapus.']);
    }

    /** Bukti bayar (disk privat). Hanya lewat signed URL. */
    public function proof(Rental $rental, RentalPayment $payment): StreamedResponse
    {
        abort_unless($payment->rental_id === $rental->id && $payment->proof_path, 404);

        return Storage::disk(PaymentService::DISK)->response($payment->proof_path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
