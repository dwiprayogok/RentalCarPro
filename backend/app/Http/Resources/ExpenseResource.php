<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\Expense;
use App\Services\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Expense */
class ExpenseResource extends JsonResource
{
    use FormatsDates;

    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'car_id' => $this->car_id,
            'car' => $this->car_id ? $this->whenLoaded('car', fn () => [
                'id' => $this->car->id,
                'name' => $this->car->display_name,
                'plate_number' => $this->car->plate_number,
            ]) : null,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'title' => $this->title,
            'spent_at' => $this->dateOnly($this->spent_at),
            'amount' => $this->amount,
            'method' => $this->method?->value,
            'method_label' => $this->method?->label(),
            'vendor' => $this->vendor,
            'notes' => $this->notes,
            'has_proof' => (bool) $this->proof_path,
            'proof_url' => app(ExpenseService::class)->proofUrl($this->resource),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->wib($this->created_at),
            'can' => [
                'update' => (bool) $user?->can('update', $this->resource),
                'delete' => (bool) $user?->can('delete', $this->resource),
            ],
        ];
    }
}
