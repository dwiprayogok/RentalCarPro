<?php

namespace App\Http\Requests\Rental;

use App\Http\Requests\Concerns\NormalizesInput;
use App\Models\Rental;
use Illuminate\Foundation\Http\FormRequest;

class QuoteRentalRequest extends FormRequest
{
    use NormalizesInput, RentalScheduleRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Rental::class);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeBooleans(['start_now']);
    }

    public function rules(): array
    {
        return $this->scheduleRules(strict: false);
    }

    public function attributes(): array
    {
        return $this->scheduleAttributes();
    }
}
