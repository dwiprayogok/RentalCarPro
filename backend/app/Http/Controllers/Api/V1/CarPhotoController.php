<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Car\StoreCarPhotosRequest;
use App\Http\Requests\Car\UpdateCarPhotoRequest;
use App\Http\Resources\CarPhotoResource;
use App\Models\Car;
use App\Models\CarPhoto;
use App\Services\CarPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CarPhotoController extends Controller
{
    public function __construct(private readonly CarPhotoService $photos) {}

    public function store(StoreCarPhotosRequest $request, Car $car): JsonResponse
    {
        $this->photos->upload($car, $request->file('photos'));

        return CarPhotoResource::collection($car->photos()->get())
            ->additional(['message' => 'Foto berhasil diunggah.'])
            ->response()->setStatusCode(201);
    }

    public function update(UpdateCarPhotoRequest $request, Car $car, CarPhoto $photo): AnonymousResourceCollection
    {
        $this->photos->update($photo, $request->validated());

        return CarPhotoResource::collection($car->photos()->get())
            ->additional(['message' => 'Foto diperbarui.']);
    }

    public function destroy(Car $car, CarPhoto $photo): AnonymousResourceCollection
    {
        Gate::authorize('managePhotos', $car);
        $this->photos->delete($photo);

        return CarPhotoResource::collection($car->photos()->get())
            ->additional(['message' => 'Foto dihapus.']);
    }
}
