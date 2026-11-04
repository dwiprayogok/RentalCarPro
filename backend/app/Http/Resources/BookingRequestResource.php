<?php

namespace App\Http\Resources;

use App\Enums\BookingRequestStatus;
use App\Http\Resources\Concerns\FormatsDates;
use App\Models\BookingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BookingRequest */
class BookingRequestResource extends JsonResource
{
    use FormatsDates;

    public function toArray(Request $request): array
    {
        $user = $request->user();
        $open = in_array($this->status, [BookingRequestStatus::Baru, BookingRequestStatus::Dihubungi], true);
        $customer = $this->matched_customer ?? null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'car' => $this->car ? ['id' => $this->car->id, 'name' => $this->car->display_name, 'plate_number' => $this->car->plate_number, 'status' => $this->car->status->value] : null,
            'start_at' => $this->wib($this->start_at),
            'end_at' => $this->wib($this->end_at),
            'service_type' => $this->service_type->value,
            'service_type_label' => $this->service_type->label(),
            'notes' => $this->notes,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'rejection_reason' => $this->rejection_reason,
            'handled_by' => $this->handledBy?->name,
            'handled_at' => $this->wib($this->handled_at),
            'rental' => $this->rental ? ['id' => $this->rental->id, 'code' => $this->rental->code] : null,
            'matched_customer' => $customer ? [
                'id' => $customer->id, 'name' => $customer->name, 'risk_status' => $customer->risk_status->value,
                'risk_status_label' => $customer->risk_status->label(), 'self_drive_ready' => $customer->selfDriveMissing() === [],
            ] : null,
            'created_at' => $this->wib($this->created_at),
            'can' => [
                'update' => $open && (bool) $user?->can('update', $this->resource),
                'convert' => $open && ! $this->rental && (bool) $user?->can('convert', $this->resource),
            ],
        ];
    }
}
