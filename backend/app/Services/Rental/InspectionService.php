<?php

namespace App\Services\Rental;

use App\Enums\InspectionType;
use App\Models\Rental;
use App\Models\RentalInspection;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Inspeksi serah terima / kembali. Foto disimpan di disk PRIVAT, dibuka lewat signed URL. */
class InspectionService
{
    public const DISK = 'private';

    public const MAX_PHOTOS = 12;

    /**
     * @param  array{odometer: int, fuel_level: int, checklist?: ?array, notes?: ?string}  $data
     */
    public function create(Rental $rental, InspectionType $type, array $data, User $by): RentalInspection
    {
        return $rental->inspections()->create([
            'type' => $type,
            'odometer' => (int) $data['odometer'],
            'fuel_level' => (int) $data['fuel_level'],
            'checklist' => $data['checklist'] ?? [],
            'notes' => $data['notes'] ?? null,
            'photos' => [],
            'inspected_at' => now(),
            'inspected_by' => $by->id,
        ]);
    }

    /** @param list<UploadedFile> $files */
    public function addPhotos(RentalInspection $inspection, array $files): RentalInspection
    {
        $photos = $inspection->photos ?? [];
        if (count($photos) + count($files) > self::MAX_PHOTOS) {
            throw ValidationException::withMessages(['photos' => 'Maksimal '.self::MAX_PHOTOS.' foto per inspeksi.']);
        }

        foreach ($files as $file) {
            $photos[] = $file->storeAs(
                "rentals/{$inspection->rental_id}/inspections/{$inspection->type->value}",
                Str::uuid().'.'.$file->extension(),
                self::DISK,
            );
        }

        $inspection->forceFill(['photos' => $photos])->save();

        return $inspection;
    }

    public function deletePhoto(RentalInspection $inspection, int $index): RentalInspection
    {
        $photos = $inspection->photos ?? [];
        abort_unless(isset($photos[$index]), 404);
        Storage::disk(self::DISK)->delete($photos[$index]);
        array_splice($photos, $index, 1);
        $inspection->forceFill(['photos' => array_values($photos)])->save();

        return $inspection;
    }

    /** @return list<string> */
    public function photoUrls(RentalInspection $inspection): array
    {
        return collect($inspection->photos ?? [])->keys()->map(fn (int $i) => URL::temporarySignedRoute(
            'v1.rentals.inspections.photo',
            now()->addMinutes(10),
            ['rental' => $inspection->rental_id, 'type' => $inspection->type->value, 'index' => $i],
        ))->all();
    }
}
