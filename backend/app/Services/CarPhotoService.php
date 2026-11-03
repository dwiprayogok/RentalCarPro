<?php

namespace App\Services;

use App\Models\Car;
use App\Models\CarPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Foto armada (untuk landing) disimpan di disk publik. */
class CarPhotoService
{
    public const DISK = 'public';

    /**
     * @param  list<UploadedFile>  $files
     * @return list<CarPhoto>
     */
    public function upload(Car $car, array $files): array
    {
        $stored = [];

        try {
            return DB::transaction(function () use ($car, $files, &$stored) {
                $hasCover = $car->photos()->where('is_cover', true)->exists();
                $order = (int) $car->photos()->max('sort_order');
                $photos = [];

                foreach ($files as $file) {
                    $path = $file->storeAs(
                        "cars/{$car->id}",
                        Str::uuid().'.'.$file->extension(),
                        self::DISK,
                    );
                    $stored[] = $path;

                    $photos[] = $car->photos()->create([
                        'disk' => self::DISK,
                        'path' => $path,
                        'is_cover' => ! $hasCover && $photos === [],
                        'sort_order' => ++$order,
                    ]);
                }

                return $photos;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($stored);
            throw $e;
        }
    }

    /** @param array{is_cover?: bool, caption?: ?string, sort_order?: int} $data */
    public function update(CarPhoto $photo, array $data): CarPhoto
    {
        DB::transaction(function () use ($photo, $data) {
            if (! empty($data['is_cover'])) {
                CarPhoto::query()->where('car_id', $photo->car_id)->update(['is_cover' => false]);
                $photo->is_cover = true;
            }
            if (array_key_exists('caption', $data)) {
                $photo->caption = $data['caption'];
            }
            if (array_key_exists('sort_order', $data)) {
                $photo->sort_order = $data['sort_order'];
            }
            $photo->save();
        });

        return $photo;
    }

    public function delete(CarPhoto $photo): void
    {
        DB::transaction(function () use ($photo) {
            $wasCover = $photo->is_cover;
            $carId = $photo->car_id;
            $photo->delete();

            if ($wasCover) {
                CarPhoto::query()->where('car_id', $carId)->orderBy('sort_order')->first()?->update(['is_cover' => true]);
            }
        });

        Storage::disk($photo->disk)->delete($photo->path);
    }
}
