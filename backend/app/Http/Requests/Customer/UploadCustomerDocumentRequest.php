<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadCustomerDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageDocuments', $this->route('customer'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['ktp', 'sim'])],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return ['type' => 'jenis dokumen', 'file' => 'berkas'];
    }
}
