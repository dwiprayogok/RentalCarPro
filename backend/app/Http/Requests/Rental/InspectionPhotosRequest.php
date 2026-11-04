<?php

namespace App\Http\Requests\Rental;

use Illuminate\Foundation\Http\FormRequest;

class InspectionPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->route('type') === 'kembali' ? 'return' : 'handover';

        return $this->user()->can($ability, $this->route('rental'));
    }

    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:12'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return ['photos' => 'foto', 'photos.*' => 'foto'];
    }
}
