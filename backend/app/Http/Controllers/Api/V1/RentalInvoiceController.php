<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Services\Rental\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RentalInvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    /** Data nota untuk modal + tautan WhatsApp. */
    public function show(Rental $rental): JsonResponse
    {
        Gate::authorize('view', $rental);

        return response()->json(['data' => $this->invoices->data($rental)]);
    }

    /** PDF nota (server-side) untuk pengguna yang login. */
    public function pdf(Rental $rental): Response
    {
        Gate::authorize('view', $rental);

        return $this->invoices->pdf($rental, download: request()->boolean('download'));
    }

    /** Signed URL berumur 2 menit untuk membuka PDF di tab baru dari Admin Web. */
    public function printUrl(Rental $rental): JsonResponse
    {
        Gate::authorize('view', $rental);

        return response()->json(['data' => ['url' => $this->invoices->printUrl($rental)]]);
    }

    /** PDF nota lewat tautan bertanda tangan (cetak: 2 menit; dibagikan ke pelanggan: 7 hari). */
    public function shared(Rental $rental): Response
    {
        $response = $this->invoices->pdf($rental);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
