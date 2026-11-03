<?php

namespace App\Http\Requests\Rental;

use Illuminate\Foundation\Http\FormRequest;

class HandoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('handover', $this->route('rental'));
    }

    public function rules(): array
    {
        return [
            'odometer' => ['required', 'integer', 'min:0', 'max:5000000'],
            'fuel_level' => ['required', 'integer', 'between:0,100'],
            'checklist' => ['nullable', 'array', 'max:30'],
            'checklist.*.item' => ['required', 'string', 'max:60'],
            'checklist.*.ok' => ['required', 'boolean'],
            'checklist.*.note' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['odometer' => 'odometer', 'fuel_level' => 'level BBM', 'checklist' => 'checklist kondisi', 'notes' => 'catatan'];
    }
}
