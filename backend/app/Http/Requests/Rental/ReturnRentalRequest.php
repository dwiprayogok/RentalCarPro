<?php

namespace App\Http\Requests\Rental;

use App\Enums\FeeType;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Dipakai untuk pratinjau (preview) dan konfirmasi Kembali. */
class ReturnRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('return', $this->route('rental'));
    }

    private function isPreview(): bool
    {
        return $this->routeIs('v1.rentals.return.preview');
    }

    public function rules(): array
    {
        $req = $this->isPreview() ? 'nullable' : 'required';

        return [
            'returned_at' => ['nullable', 'date'],
            'odometer' => [$req, 'integer', 'min:0', 'max:5000000'],
            'fuel_level' => [$req, 'integer', 'between:0,100'],
            'checklist' => ['nullable', 'array', 'max:30'],
            'checklist.*.item' => ['required', 'string', 'max:60'],
            'checklist.*.ok' => ['required', 'boolean'],
            'checklist.*.note' => ['nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'fees' => ['nullable', 'array', 'max:20'],
            'fees.*.type' => ['required', Rule::enum(FeeType::class)],
            'fees.*.amount' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'fees.*.reason' => ['nullable', 'string', 'max:500'], // wajib per baris dicek di ReturnService

            'deposit_deduction' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'payment' => ['nullable', 'array'],
            'payment.amount' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'payment.method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'payment.reference_number' => ['nullable', 'string', 'max:100'],
            'refund_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'car_status' => ['nullable', Rule::in(['tersedia', 'servis'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'returned_at' => 'waktu kembali',
            'odometer' => 'odometer akhir',
            'fuel_level' => 'level BBM akhir',
            'fees.*.type' => 'jenis biaya',
            'fees.*.amount' => 'jumlah biaya',
            'fees.*.reason' => 'alasan biaya',
            'deposit_deduction' => 'potongan deposit',
            'payment.amount' => 'pembayaran akhir',
            'payment.method' => 'metode pembayaran',
            'refund_method' => 'metode pengembalian deposit',
            'car_status' => 'status mobil',
        ];
    }
}
