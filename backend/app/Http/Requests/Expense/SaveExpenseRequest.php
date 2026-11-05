<?php

namespace App\Http\Requests\Expense;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Dipakai untuk tambah (POST) & ubah (PUT, boleh multipart dengan _method=PUT). */
class SaveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            ? $this->user()->can('update', $expense)
            : $this->user()->can('create', Expense::class);
    }

    protected function prepareForValidation(): void
    {
        // Dari FormData: "" berarti pengeluaran umum.
        if ($this->input('car_id') === '' || $this->input('car_id') === 'null') {
            $this->merge(['car_id' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'car_id' => ['nullable', 'integer', Rule::exists('cars', 'id')],
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'spent_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:2000-01-01'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'vendor' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'remove_proof' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'car_id' => 'mobil',
            'category' => 'kategori',
            'title' => 'keterangan',
            'spent_at' => 'tanggal',
            'amount' => 'jumlah',
            'method' => 'metode bayar',
            'vendor' => 'toko/vendor',
            'notes' => 'catatan',
            'proof' => 'bukti',
        ];
    }

    public function messages(): array
    {
        return [
            'spent_at.before_or_equal' => 'Tanggal pengeluaran tidak boleh di masa depan.',
            'proof.mimes' => 'Bukti harus berupa foto (JPG, PNG, WEBP) atau PDF.',
            'proof.max' => 'Ukuran bukti maksimal 5 MB.',
        ];
    }
}
