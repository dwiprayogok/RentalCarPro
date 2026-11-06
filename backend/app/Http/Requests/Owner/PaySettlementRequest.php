<?php

namespace App\Http\Requests\Owner;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaySettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pay', $this->route('settlement'));
    }

    public function rules(): array
    {
        return [
            'paid_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'before_or_equal:'.now(config('app.display_timezone'))->addMinutes(5)->format('Y-m-d\TH:i')],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return ['paid_at' => 'waktu bayar', 'method' => 'metode', 'reference' => 'nomor referensi', 'proof' => 'bukti transfer'];
    }

    public function messages(): array
    {
        return [
            'paid_at.before_or_equal' => 'Waktu bayar tidak boleh di masa depan.',
            'proof.mimes' => 'Bukti harus berupa foto (JPG, PNG, WEBP) atau PDF.',
            'proof.max' => 'Ukuran bukti maksimal 5 MB.',
        ];
    }
}
