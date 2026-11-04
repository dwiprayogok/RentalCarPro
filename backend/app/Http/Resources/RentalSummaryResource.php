<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\Rental;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ringkasan sewa untuk riwayat pelanggan/mobil.
 *
 * @mixin Rental
 */
class RentalSummaryResource extends JsonResource
{
    use FormatsDates;

    public function toArray(Request $request): array
    {
        $overdue = $this->isOverdue();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'car' => $this->whenLoaded('car', fn () => [
                'id' => $this->car->id,
                'name' => $this->car->display_name,
                'plate_number' => $this->car->plate_number,
            ]),
            'service_type' => $this->service_type->value,
            'service_type_label' => $this->service_type->label(),
            'status' => $this->status->value,
            'status_label' => $overdue ? 'Terlambat' : $this->status->label(),
            'is_overdue' => $overdue,
            'start_at' => $this->wib($this->start_at),
            'planned_end_at' => $this->wib($this->planned_end_at),
            'returned_at' => $this->wib($this->returned_at),
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'balance' => max(0, $this->total_amount - $this->paid_amount),
        ];
    }
}
