<?php

namespace App\Http\Requests\Rental;

use App\Enums\PaymentMethod;
use App\Http\Requests\Concerns\NormalizesInput;
use App\Models\Rental;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Sewa Baru. Waktu dikirim ISO-8601 dengan offset (mis. 2026-10-21T15:41:00+07:00).
 * start_now = true: sewa langsung Aktif (serah terima sekarang), data handover wajib.
 */
class StoreRentalRequest extends FormRequest
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
        return $this->scheduleRules(strict: true) + [
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'blacklist_override_reason' => ['nullable', 'string', 'min:10', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'handover' => ['required_if:start_now,true', 'nullable', 'array'],
            'handover.odometer' => ['required_with:handover', 'integer', 'min:0', 'max:5000000'],
            'handover.fuel_level' => ['required_with:handover', 'integer', 'between:0,100'],
            'handover.checklist' => ['nullable', 'array', 'max:30'],
            'handover.checklist.*.item' => ['required', 'string', 'max:60'],
            'handover.checklist.*.ok' => ['required', 'boolean'],
            'handover.checklist.*.note' => ['nullable', 'string', 'max:200'],
            'handover.notes' => ['nullable', 'string', 'max:2000'],

            'payments' => ['nullable', 'array', 'max:3'],
            'payments.*.type' => ['required', Rule::in(['dp', 'pelunasan', 'deposit'])],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:100'],
            'payments.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $startNow = $this->boolean('start_now');
            $start = $startNow ? now() : Carbon::parse($this->input('start_at'));
            $end = Carbon::parse($this->input('planned_end_at'));

            if ($end->lte($start)) {
                $v->errors()->add('planned_end_at', 'Jam kembali harus setelah waktu mulai sewa.');
            }
            if (! $startNow && $start->lt(now()->subMinutes(30))) {
                $v->errors()->add('start_at', 'Waktu mulai booking sudah lewat. Pilih "Mulai sekarang" bila mobil diserahkan saat ini.');
            }
            if ($start->diffInDays($end) > 90) {
                $v->errors()->add('planned_end_at', 'Durasi sewa maksimal 90 hari.');
            }
            if ((int) $this->input('discount', 0) > 0 && blank($this->input('discount_reason'))) {
                $v->errors()->add('discount_reason', 'Alasan diskon wajib diisi.');
            }
        }];
    }

    public function attributes(): array
    {
        return $this->scheduleAttributes();
    }

    public function messages(): array
    {
        return [
            'handover.required_if' => 'Data serah terima wajib diisi bila sewa dimulai sekarang.',
            'start_at.required_unless' => 'Waktu mulai wajib diisi, atau pilih "Mulai sekarang".',
            'blacklist_override_reason.min' => 'Alasan pengecualian minimal 10 karakter.',
        ];
    }
}
