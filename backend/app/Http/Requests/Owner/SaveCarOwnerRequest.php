<?php

namespace App\Http\Requests\Owner;

use App\Enums\ShareBasis;
use App\Http\Requests\Concerns\NormalizesInput;
use App\Models\CarOwner;
use App\Rules\IndonesianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCarOwnerRequest extends FormRequest
{
    use NormalizesInput;

    public function authorize(): bool
    {
        $owner = $this->route('owner');

        return $owner instanceof CarOwner ? $this->user()->can('update', $owner) : $this->user()->can('create', CarOwner::class);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhones(['phone']);
        $this->normalizeBooleans(['is_active']);
        if ($this->filled('bank_account_number')) {
            $this->merge(['bank_account_number' => preg_replace('/[\s\-.]/', '', (string) $this->input('bank_account_number'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', new IndonesianPhone],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:60', 'required_with:bank_account_number'],
            'bank_account_number' => ['nullable', 'regex:/^\d{5,30}$/', 'required_with:bank_name'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'share_basis' => ['required', Rule::enum(ShareBasis::class)],
            'share_percent' => ['required', 'numeric', 'gt:0', 'max:100', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama', 'phone' => 'nomor HP', 'address' => 'alamat', 'bank_name' => 'nama bank',
            'bank_account_number' => 'nomor rekening', 'bank_account_name' => 'nama pemilik rekening',
            'share_basis' => 'dasar bagi hasil', 'share_percent' => 'persentase bagian pemilik', 'notes' => 'catatan',
        ];
    }

    public function messages(): array
    {
        return [
            'bank_account_number.regex' => 'Nomor rekening hanya berisi angka (5–30 digit).',
            'share_percent.gt' => 'Persentase bagian pemilik harus lebih dari 0%.',
            'share_percent.max' => 'Persentase bagian pemilik maksimal 100%.',
        ];
    }
}
