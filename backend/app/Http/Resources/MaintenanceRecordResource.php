<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MaintenanceRecord */
class MaintenanceRecordResource extends JsonResource
{
    use FormatsDates;

    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'car_id' => $this->car_id,
            'car' => $this->whenLoaded('car', fn () => [
                'id' => $this->car->id,
                'name' => $this->car->display_name,
                'plate_number' => $this->car->plate_number,
            ]),
            'rental_id' => $this->rental_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'title' => $this->title,
            'performed_at' => $this->dateOnly($this->performed_at),
            'odometer' => $this->odometer,
            'cost' => $this->cost,
            'vendor' => $this->vendor,
            'next_due_date' => $this->dateOnly($this->next_due_date),
            'next_due_odometer' => $this->next_due_odometer,
            'notes' => $this->notes,
            'created_at' => $this->wib($this->created_at),
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
            ],
        ];
    }
}
