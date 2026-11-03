<?php

namespace App\Http\Requests\Rental;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addPayment', $this->route('rental'));
    }

    public function rules(): array
    {
        return [
            // Refund & pengembalian/potong deposit diproses di alur Kembali (phase berikutnya).
            'type' => ['required', Rule::in(['dp', 'pelunasan', 'deposit'])],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'reference_number' => [
                'nullable', 'string', 'max:100',
                Rule::requiredIf(fn () => in_array($this->input('method'), ['transfer', 'qris'], true) && ! $this->hasFile('proof')),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'jenis pembayaran',
            'method' => 'metode pembayaran',
            'amount' => 'jumlah',
            'paid_at' => 'tanggal bayar',
            'reference_number' => 'nomor referensi',
            'notes' => 'catatan',
            'proof' => 'bukti bayar',
        ];
    }

    public function messages(): array
    {
        return ['reference_number.required' => 'Isi nomor referensi atau unggah bukti bayar untuk pembayaran Transfer/QRIS.'];
    }
}
