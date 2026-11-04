<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('booking_request'));
    }

    public function rules(): array
    {
        return [
            // "dikonfirmasi" hanya lewat "Jadikan Sewa".
            'status' => ['required', Rule::in(['baru', 'dihubungi', 'ditolak'])],
            'rejection_reason' => ['nullable', 'required_if:status,ditolak', 'string', 'min:5', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['status' => 'status', 'rejection_reason' => 'alasan penolakan'];
    }
}
