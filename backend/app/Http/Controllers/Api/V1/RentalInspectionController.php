<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\InspectionPhotosRequest;
use App\Http\Resources\RentalResource;
use App\Models\Rental;
use App\Services\Rental\InspectionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RentalInspectionController extends Controller
{
    public function __construct(private readonly InspectionService $inspections) {}

    public function storePhotos(InspectionPhotosRequest $request, Rental $rental, string $type): RentalResource
    {
        $inspection = $rental->inspections()->where('type', $type)->firstOrFail();
        $this->inspections->addPhotos($inspection, $request->file('photos'));

        return (new RentalResource($rental->fresh()->load(['customer', 'car', 'driver', 'payments.receivedBy', 'inspections'])))
            ->additional(['message' => 'Foto inspeksi diunggah.']);
    }

    public function destroyPhoto(Rental $rental, string $type, int $index): RentalResource
    {
        Gate::authorize('handover', $rental);
        $inspection = $rental->inspections()->where('type', $type)->firstOrFail();
        $this->inspections->deletePhoto($inspection, $index);

        return (new RentalResource($rental->fresh()->load(['customer', 'car', 'driver', 'payments.receivedBy', 'inspections'])))
            ->additional(['message' => 'Foto dihapus.']);
    }

    /** Foto inspeksi (disk privat). Hanya lewat signed URL. */
    public function photo(Rental $rental, string $type, int $index): StreamedResponse
    {
        $inspection = $rental->inspections()->where('type', $type)->firstOrFail();
        $path = ($inspection->photos ?? [])[$index] ?? null;
        abort_unless($path && Storage::disk(InspectionService::DISK)->exists($path), 404);

        return Storage::disk(InspectionService::DISK)->response($path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
