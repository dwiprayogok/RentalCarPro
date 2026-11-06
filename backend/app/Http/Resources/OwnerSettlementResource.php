<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\OwnerSettlement;
use App\Services\Owner\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OwnerSettlement */
class OwnerSettlementResource extends JsonResource
{
    use FormatsDates;

    public function toArray(Request $request): array
    {
        $user = $request->user();
        $service = app(SettlementService::class);
        $detail = $request->route('settlement') !== null || $request->isMethod('POST');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'owner' => $this->whenLoaded('owner', fn () => ['id' => $this->owner->id, 'name' => $this->owner->name, 'phone' => $this->owner->phone]),
            'period_from' => $this->dateOnly($this->period_from),
            'period_to' => $this->dateOnly($this->period_to),
            'share_basis' => $this->share_basis->value,
            'share_basis_label' => $this->share_basis->label(),
            'share_percent' => (float) $this->share_percent,
            'income' => $this->income,
            'maintenance' => $this->maintenance,
            'expenses' => $this->expenses,
            'net' => $this->income - $this->maintenance - $this->expenses,
            'base_amount' => $this->base_amount,
            'owner_amount' => $this->owner_amount,
            'business_amount' => $this->business_amount,
            'lines' => $this->when($detail, fn () => $this->lines),
            'notes' => $this->notes,
            'status' => $this->isPaid() ? 'dibayar' : 'belum_dibayar',
            'status_label' => $this->isPaid() ? 'Sudah dibayar' : 'Belum dibayar',
            'paid_at' => $this->wib($this->paid_at),
            'paid_method' => $this->paid_method?->value,
            'paid_method_label' => $this->paid_method?->label(),
            'paid_reference' => $this->paid_reference,
            'paid_by' => $this->whenLoaded('payer', fn () => $this->payer?->name),
            'proof_url' => $this->when($detail, fn () => $service->proofUrl($this->resource)),
            'whatsapp_url' => $this->when($detail && $this->relationLoaded('owner'), fn () => $service->whatsappUrl($this->resource)),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->wib($this->created_at),
            // Gate::before meloloskan owner untuk semua policy, jadi aturan "sudah dibayar" dicek di sini juga.
            'can' => [
                'pay' => ! $this->isPaid() && (bool) $user?->can('pay', $this->resource),
                'delete' => ! $this->isPaid() && (bool) $user?->can('delete', $this->resource),
            ],
        ];
    }
}
