<?php

namespace App\Http\Requests\Car;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCarPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('managePhotos', $this->route('car'));
    }

    public function rules(): array
    {
        return [
            'is_cover' => ['sometimes', 'accepted'],
            'caption' => ['sometimes', 'nullable', 'string', 'max:120'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
