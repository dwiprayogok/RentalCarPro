<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Driver */
class DriverResource extends JsonResource
{
    use FormatsDates;

    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'license_number' => $this->license_number,
            'license_expires_at' => $this->dateOnly($this->license_expires_at),
            'license_expired' => $this->license_expires_at?->isPast() ?? false,
            'daily_fee' => $this->daily_fee,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'rentals_count' => $this->whenCounted('rentals'),
            'created_at' => $this->wib($this->created_at),
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
            ],
        ];
    }
}
