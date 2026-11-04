<?php

namespace App\Http\Requests\Customer;

use App\Enums\RiskStatus;
use App\Http\Requests\Concerns\NormalizesInput;
use App\Models\Customer;
use App\Rules\IndonesianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /customers dan PUT /customers/{customer}.
 *
 * - NIK wajib saat membuat. Saat mengubah, NIK boleh dikosongkan (= tidak diubah),
 *   karena frontend tidak pernah menerima NIK lengkap.
 * - for_self_drive = true: SIM (nomor + masa berlaku), alamat, dan kontak darurat wajib.
 * - Status risiko perhatian/blacklist wajib disertai catatan; mengubahnya butuh customer.change_risk.
 */
class SaveCustomerRequest extends FormRequest
{
    use NormalizesInput;

    public function customer(): ?Customer
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer ? $customer : null;
    }

    public function authorize(): bool
    {
        return $this->customer()
            ? $this->user()->can('update', $this->customer())
            : $this->user()->can('create', Customer::class);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhones(['phone', 'emergency_contact_phone']);
        $this->normalizeBooleans(['for_self_drive']);

        $merge = [];
        if ($this->has('nik')) {
            $nik = preg_replace('/[\s.\-]/', '', (string) $this->input('nik'));
            $merge['nik'] = $nik === '' ? null : $nik;
        }
        if ($this->filled('sim_number')) {
            $merge['sim_number'] = strtoupper(preg_replace('/[\s.\-]/', '', (string) $this->input('sim_number')));
        }
        if ($this->filled('name')) {
            $merge['name'] = preg_replace('/\s+/', ' ', trim((string) $this->input('name')));
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        $selfDrive = 'required_if_accepted:for_self_drive';

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', new IndonesianPhone],
            'nik' => [$this->customer() ? 'nullable' : 'required', 'string', 'digits:16'],

            'for_self_drive' => ['sometimes', 'boolean'],
            'sim_number' => ['nullable', $selfDrive, 'string', 'regex:/^[0-9A-Z]{10,20}$/'],
            'sim_type' => ['nullable', 'string', Rule::in(['A', 'A Umum', 'B1', 'B1 Umum', 'B2', 'B2 Umum', 'C', 'D', 'Internasional'])],
            'sim_expires_at' => ['nullable', $selfDrive, 'date', 'after:today'],
            'address' => ['nullable', $selfDrive, 'string', 'max:500'],
            'emergency_contact_name' => ['nullable', $selfDrive, 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', $selfDrive, 'string', new IndonesianPhone, 'different:phone'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:40'],

            'risk_status' => ['sometimes', Rule::enum(RiskStatus::class)],
            'risk_note' => [
                'nullable', 'string', 'max:1000',
                Rule::requiredIf(fn () => RiskStatus::tryFrom((string) $this->input('risk_status'))?->requiresNote() ?? false),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $customer = $this->customer();

            // NIK unik (dicek lewat hash karena kolomnya terenkripsi).
            if ($this->filled('nik') && ! $validator->errors()->has('nik')) {
                $existing = Customer::withTrashed()
                    ->where('nik_hash', Customer::hashNik($this->input('nik')))
                    ->when($customer, fn ($q) => $q->whereKeyNot($customer->id))
                    ->first();

                if ($existing) {
                    $validator->errors()->add('nik', $existing->trashed()
                        ? 'NIK ini milik pelanggan yang sudah dihapus. Hubungi pemilik untuk memulihkannya.'
                        : "NIK ini sudah terdaftar atas nama {$existing->name}.");
                }
            }

            // Perubahan status risiko hanya untuk yang berwenang.
            $current = $customer?->risk_status ?? RiskStatus::Lancar;
            $requested = RiskStatus::tryFrom((string) $this->input('risk_status', $current->value));
            if ($requested && $requested !== $current && ! $this->user()->can('changeRisk', Customer::class)) {
                $validator->errors()->add('risk_status', 'Anda tidak berwenang mengubah status risiko pelanggan.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'phone' => 'nomor HP / WhatsApp',
            'nik' => 'nomor KTP',
            'sim_number' => 'nomor SIM',
            'sim_type' => 'jenis SIM',
            'sim_expires_at' => 'masa berlaku SIM',
            'address' => 'alamat domisili',
            'emergency_contact_name' => 'nama kontak darurat',
            'emergency_contact_phone' => 'nomor kontak darurat',
            'emergency_contact_relation' => 'hubungan kontak darurat',
            'risk_status' => 'status risiko',
            'risk_note' => 'catatan risiko',
            'notes' => 'catatan',
            'for_self_drive' => 'layanan lepas kunci',
        ];
    }

    public function messages(): array
    {
        $selfDrive = ':attribute wajib diisi untuk pelanggan layanan Lepas Kunci.';

        return [
            'nik.digits' => 'Nomor KTP harus 16 digit angka.',
            'sim_number.regex' => 'Nomor SIM harus 10–20 karakter angka/huruf.',
            'sim_expires_at.after' => 'SIM sudah kedaluwarsa. Masa berlaku harus setelah hari ini.',
            'emergency_contact_phone.different' => 'Nomor kontak darurat harus berbeda dari nomor pelanggan.',
            'risk_note.required' => 'Catatan risiko wajib diisi untuk status Perhatian atau Blacklist.',
            'sim_number.required_if_accepted' => $selfDrive,
            'sim_expires_at.required_if_accepted' => $selfDrive,
            'address.required_if_accepted' => $selfDrive,
            'emergency_contact_name.required_if_accepted' => $selfDrive,
            'emergency_contact_phone.required_if_accepted' => $selfDrive,
        ];
    }
}
