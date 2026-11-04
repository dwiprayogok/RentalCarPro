<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\ReturnRentalRequest;
use App\Http\Resources\RentalResource;
use App\Models\Rental;
use App\Services\Rental\ReturnService;
use Illuminate\Http\JsonResponse;

class RentalReturnController extends Controller
{
    public function __construct(private readonly ReturnService $returns) {}

    /** Ringkasan akhir (denda, biaya, deposit, sisa) tanpa menyimpan apa pun. */
    public function preview(ReturnRentalRequest $request, Rental $rental): JsonResponse
    {
        return response()->json(['data' => $this->returns->preview($rental->load(['customer', 'car', 'handoverInspection']), $request->validated())]);
    }

    public function store(ReturnRentalRequest $request, Rental $rental): RentalResource
    {
        $rental = $this->returns->complete($rental->load(['customer', 'car', 'handoverInspection']), $request->validated(), $request->user());

        return (new RentalResource($rental->load(['customer', 'car', 'driver', 'payments.receivedBy', 'inspections', 'fees', 'createdBy'])))
            ->additional(['message' => "Mobil kembali. Sewa {$rental->code} selesai."]);
    }
}
