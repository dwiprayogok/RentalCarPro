<?php

namespace App\Http\Resources\Public;

use App\Enums\CarStatus;
use App\Models\Car;
use App\Models\CarPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Data mobil untuk PUBLIK. DAFTAR PUTIH: hanya field di bawah yang boleh keluar.
 * Sengaja TIDAK ada: id internal, plat nomor, odometer, catatan internal, jatuh tempo pajak/servis,
 * pembuat data, atau apa pun terkait pelanggan/transaksi.
 *
 * @mixin Car
 */
class PublicCarResource extends JsonResource
{
    public const FIELDS = [
        'slug', 'name', 'brand', 'model', 'year', 'transmission', 'seats', 'fuel_type', 'color',
        'rate_self_drive', 'rate_with_driver', 'weekend_rate_self_drive', 'weekend_rate_with_driver',
        'deposit', 'features', 'description', 'cover_photo_url', 'photos', 'availability',
    ];

    public function toArray(Request $request): array
    {
        $photos = $this->relationLoaded('photos') ? $this->photos : collect();
        $cover = $photos->firstWhere('is_cover', true) ?? $photos->first();

        return [
            'slug' => $this->slug,
            'name' => $this->display_name,
            'brand' => $this->brand,
            'model' => $this->model,
            'year' => $this->year,
            'transmission' => $this->transmission->label(),
            'seats' => $this->seats,
            'fuel_type' => $this->fuel_type,
            'color' => $this->color,
            'rate_self_drive' => $this->rate_self_drive,
            'rate_with_driver' => $this->rate_with_driver,
            'weekend_rate_self_drive' => $this->weekend_rate_self_drive,
            'weekend_rate_with_driver' => $this->weekend_rate_with_driver,
            'deposit' => $this->default_deposit,
            'features' => array_values($this->features ?? []),
            'description' => $this->description,
            'cover_photo_url' => $cover ? Storage::disk($cover->disk)->url($cover->path) : null,
            'photos' => $photos->map(fn (CarPhoto $p) => ['url' => Storage::disk($p->disk)->url($p->path), 'caption' => $p->caption])->values(),
            // Status umum saja, tanpa detail sewa.
            'availability' => match ($this->status) {
                CarStatus::Tersedia => ['status' => 'tersedia', 'label' => 'Tersedia'],
                CarStatus::Disewa => ['status' => 'disewa', 'label' => 'Sedang disewa'],
                default => ['status' => 'perawatan', 'label' => 'Dalam perawatan'],
            },
        ];
    }
}
