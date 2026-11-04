<?php

namespace App\Http\Requests\Public;

use App\Enums\ServiceType;
use App\Http\Requests\Concerns\NormalizesInput;
use App\Rules\IndonesianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Form "Permintaan Sewa" dari landing page (tanpa login). */
class StoreBookingRequestRequest extends FormRequest
{
    use NormalizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhones(['phone']);
        if ($this->filled('name')) {
            $this->merge(['name' => preg_replace('/\s+/', ' ', trim(strip_tags((string) $this->input('name'))))]);
        }
        if ($this->filled('notes')) {
            $this->merge(['notes' => trim(strip_tags((string) $this->input('notes')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', new IndonesianPhone],
            'car' => ['nullable', 'string', 'max:120', Rule::exists('cars', 'slug')->where('is_published', true)->whereNull('deleted_at')->whereNot('status', 'nonaktif')],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date'],
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'notes' => ['nullable', 'string', 'max:500'],
            // Honeypot: kolom tersembunyi yang hanya diisi bot.
            'website' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $start = Carbon::parse($this->input('start_at'));
            $end = Carbon::parse($this->input('end_at'));
            if ($start->lt(now()->addMinutes(30))) {
                $v->errors()->add('start_at', 'Waktu mulai minimal 30 menit dari sekarang.');
            }
            if ($end->lte($start)) {
                $v->errors()->add('end_at', 'Waktu selesai harus setelah waktu mulai.');
            } elseif ($start->diffInDays($end) > 90) {
                $v->errors()->add('end_at', 'Durasi sewa maksimal 90 hari.');
            }
            if ($start->gt(now()->addYear())) {
                $v->errors()->add('start_at', 'Pemesanan maksimal 1 tahun ke depan.');
            }
        }];
    }

    public function attributes(): array
    {
        return ['name' => 'nama', 'phone' => 'nomor WhatsApp', 'car' => 'mobil', 'start_at' => 'waktu mulai', 'end_at' => 'waktu selesai', 'service_type' => 'layanan', 'notes' => 'catatan'];
    }

    public function messages(): array
    {
        return ['car.exists' => 'Mobil yang dipilih tidak tersedia untuk dipesan.'];
    }

    public function isBot(): bool
    {
        return filled($this->input('website'));
    }
}
