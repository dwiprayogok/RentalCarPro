<?php

namespace App\Http\Requests\Driver;

use App\Http\Requests\Concerns\NormalizesInput;
use App\Models\Driver;
use App\Rules\IndonesianPhone;
use Illuminate\Foundation\Http\FormRequest;

class SaveDriverRequest extends FormRequest
{
    use NormalizesInput;

    public function authorize(): bool
    {
        $driver = $this->route('driver');

        return $driver instanceof Driver
            ? $this->user()->can('update', $driver)
            : $this->user()->can('create', Driver::class);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhones(['phone']);
        $this->normalizeBooleans(['is_active']);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', new IndonesianPhone],
            'license_number' => ['nullable', 'string', 'max:30'],
            'license_expires_at' => ['nullable', 'date'],
            'daily_fee' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama driver',
            'phone' => 'nomor HP',
            'license_number' => 'nomor SIM',
            'license_expires_at' => 'masa berlaku SIM',
            'daily_fee' => 'honor harian',
            'is_active' => 'status aktif',
            'notes' => 'catatan',
        ];
    }
}
