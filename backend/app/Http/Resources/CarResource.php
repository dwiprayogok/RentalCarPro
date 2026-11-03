<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Car */
class CarResource extends JsonResource
{
    use FormatsDates;

    public function toArray(Request $request): array
    {
        $user = $request->user();
        $cover = $this->relationLoaded('photos')
            ? ($this->photos->firstWhere('is_cover', true) ?? $this->photos->first())
            : null;

        return [
            'id' => $this->id,
            'name' => $this->display_name,
            'brand' => $this->brand,
            'model' => $this->model,
            'plate_number' => $this->plate_number,
            'transmission' => $this->transmission->value,
            'transmission_label' => $this->transmission->label(),
            'year' => $this->year,
            'color' => $this->color,
            'seats' => $this->seats,
            'fuel_type' => $this->fuel_type,
            'rate_self_drive' => $this->rate_self_drive,
            'rate_with_driver' => $this->rate_with_driver,
            'weekend_rate_self_drive' => $this->weekend_rate_self_drive,
            'weekend_rate_with_driver' => $this->weekend_rate_with_driver,
            'default_deposit' => $this->default_deposit,
            'odometer' => $this->odometer,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'tax_due_date' => $this->dateOnly($this->tax_due_date),
            'stnk_due_date' => $this->dateOnly($this->stnk_due_date),
            'next_service_date' => $this->dateOnly($this->next_service_date),
            'next_service_odometer' => $this->next_service_odometer,
            'service_interval_km' => $this->service_interval_km,
            'slug' => $this->slug,
            'is_published' => $this->is_published,
            'description' => $this->description,
            'features' => $this->features ?? [],
            'notes' => $this->notes,
            'cover_photo_url' => $cover ? Storage::disk($cover->disk)->url($cover->path) : null,
            'photos_count' => $this->whenCounted('photos', fn () => $this->photos_count, fn () => $this->whenLoaded('photos', fn () => $this->photos->count())),
            'photos' => CarPhotoResource::collection($this->whenLoaded('photos')),
            'rentals_count' => $this->whenCounted('rentals'),
            'created_at' => $this->wib($this->created_at),
            'updated_at' => $this->wib($this->updated_at),
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
                'publish' => (bool) $user?->can('publish', $this->resource),
            ],
        ];
    }
}
