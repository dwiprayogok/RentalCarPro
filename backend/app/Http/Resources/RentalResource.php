<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\Rental;
use App\Models\RentalInspection;
use App\Models\RentalPayment;
use App\Services\Rental\InspectionService;
use App\Services\Rental\PaymentService;
use App\Services\Rental\RentalBilling;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail sewa. Status tampilan "Terlambat" dihitung (aktif + lewat jam kembali rencana).
 * Tagihan, terbayar, sisa, dan estimasi denda berjalan dihitung di server.
 *
 * @mixin Rental
 */
class RentalResource extends JsonResource
{
    use FormatsDates;

    public function toArray(Request $request): array
    {
        $user = $request->user();
        $overdue = $this->isOverdue();
        $billing = app(RentalBilling::class)->summary($this->resource);
        $detail = $this->relationLoaded('payments');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status->value,
            'display_status' => $overdue ? 'terlambat' : $this->status->value,
            'display_status_label' => $overdue ? 'Terlambat' : $this->status->label(),
            'is_overdue' => $overdue,
            'service_type' => $this->service_type->value,
            'service_type_label' => $this->service_type->label(),

            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'initials' => $this->customer->initials(),
                'phone' => $this->customer->phone,
                'nik_masked' => $this->customer->nik_masked,
                'risk_status' => $this->customer->risk_status->value,
                'risk_status_label' => $this->customer->risk_status->label(),
            ]),
            'car' => $this->whenLoaded('car', fn () => [
                'id' => $this->car->id,
                'name' => $this->car->display_name,
                'plate_number' => $this->car->plate_number,
                'year' => $this->car->year,
                'color' => $this->car->color,
                'transmission_label' => $this->car->transmission->label(),
                'odometer' => $this->car->odometer,
            ]),
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
                'phone' => $this->driver->phone,
            ] : null),

            'start_at' => $this->wib($this->start_at),
            'planned_end_at' => $this->wib($this->planned_end_at),
            'picked_up_at' => $this->wib($this->picked_up_at),
            'returned_at' => $this->wib($this->returned_at),

            'daily_rate' => $this->daily_rate,
            'billed_days' => $this->billed_days,
            'rental_amount' => $this->rental_amount,
            'price_breakdown' => $this->when($detail, $this->price_breakdown),
            'late_fee' => $this->late_fee,
            'late_extra_days' => $this->late_extra_days,
            'fees' => $this->when($this->relationLoaded('fees'), fn () => $this->fees->map(fn ($f) => [
                'id' => $f->id, 'type' => $f->type->value, 'type_label' => $f->type->label(), 'amount' => $f->amount, 'reason' => $f->reason,
            ])),
            'extra_fees_total' => $this->extra_fees_total,
            'discount' => $this->discount,
            'discount_reason' => $this->discount_reason,
            'billing' => $billing,

            'notes' => $this->notes,
            'cancel_reason' => $this->cancel_reason,
            'cancelled_at' => $this->wib($this->cancelled_at),
            'blacklist_override' => $this->blacklist_override_by ? [
                'reason' => $this->blacklist_override_reason,
                'by' => $this->whenLoaded('blacklistOverrideBy', fn () => $this->blacklistOverrideBy?->name),
            ] : null,

            'payments' => $this->when($detail, fn () => $this->payments->map(fn (RentalPayment $p) => [
                'id' => $p->id,
                'type' => $p->type->value,
                'type_label' => $p->type->label(),
                'method' => $p->method?->value,
                'method_label' => $p->method?->label(),
                'amount' => $p->amount,
                'paid_at' => $this->wib($p->paid_at),
                'reference_number' => $p->reference_number,
                'notes' => $p->notes,
                'proof_url' => app(PaymentService::class)->proofUrl($p),
                'received_by' => $p->receivedBy?->name,
            ])),
            'inspections' => $this->when($this->relationLoaded('inspections'), fn () => $this->inspections->mapWithKeys(fn (RentalInspection $i) => [
                $i->type->value => [
                    'odometer' => $i->odometer,
                    'fuel_level' => $i->fuel_level,
                    'checklist' => $i->checklist ?? [],
                    'notes' => $i->notes,
                    'photo_urls' => app(InspectionService::class)->photoUrls($i),
                    'inspected_at' => $this->wib($i->inspected_at),
                ],
            ])),

            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'created_at' => $this->wib($this->created_at),
            'can' => [
                'handover' => $this->status->value === 'booking' && (bool) $user?->can('handover', $this->resource),
                'return' => $this->status->value === 'aktif' && (bool) $user?->can('return', $this->resource),
                'cancel' => $this->status->value === 'booking' && (bool) $user?->can('cancel', $this->resource),
                'add_payment' => $this->status->value !== 'batal' && (bool) $user?->can('addPayment', $this->resource),
                'delete_payment' => ! in_array($this->status->value, ['selesai', 'batal'], true) && (bool) $user?->can('deletePayment', $this->resource),
            ],
        ];
    }
}
