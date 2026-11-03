<?php

namespace App\Http\Requests\Car;

use App\Models\Car;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCarPhotosRequest extends FormRequest
{
    public const MAX_PHOTOS = 12;

    public function authorize(): bool
    {
        return $this->user()->can('managePhotos', $this->route('car'));
    }

    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:8'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=320,min_height=200'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            /** @var Car $car */
            $car = $this->route('car');
            $total = $car->photos()->count() + count((array) $this->file('photos', []));
            if ($total > self::MAX_PHOTOS) {
                $validator->errors()->add('photos', 'Maksimal '.self::MAX_PHOTOS.' foto per mobil.');
            }
        }];
    }

    public function attributes(): array
    {
        return ['photos' => 'foto', 'photos.*' => 'foto'];
    }
}
