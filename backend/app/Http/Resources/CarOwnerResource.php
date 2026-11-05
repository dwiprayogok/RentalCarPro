<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\CarOwner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CarOwner */
class CarOwnerResource extends JsonResource
{
    use FormatsDates;

    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'bank_account_name' => $this->bank_account_name,
            'share_basis' => $this->share_basis->value,
            'share_basis_label' => $this->share_basis->label(),
            'share_percent' => (float) $this->share_percent,
            'share_label' => $this->shareLabel(),
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'cars_count' => $this->whenCounted('cars'),
            'unpaid_total' => $this->when($this->hasAttr('unpaid_total'), fn () => (int) $this->unpaid_total),
            'unpaid_count' => $this->when($this->hasAttr('unpaid_count'), fn () => (int) $this->unpaid_count),
            'last_period_to' => $this->when($this->hasAttr('last_period_to'), fn () => $this->last_period_to ? substr((string) $this->last_period_to, 0, 10) : null),
            'cars' => $this->whenLoaded('cars', fn () => $this->cars->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->display_name, 'plate_number' => $c->plate_number, 'status' => $c->status->value, 'status_label' => $c->status->label(),
            ])),
            'created_at' => $this->wib($this->created_at),
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
            ],
        ];
    }

    /** Kolom agregat (withSum/withMax) ada walau nilainya NULL. */
    private function hasAttr(string $key): bool
    {
        return array_key_exists($key, $this->resource->getAttributes());
    }
}
