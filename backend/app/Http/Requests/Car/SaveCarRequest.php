<?php

namespace App\Http\Requests\Car;

use App\Enums\CarStatus;
use App\Enums\Transmission;
use App\Http\Requests\Concerns\NormalizesInput;
use App\Models\Car;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Dipakai untuk POST /cars (buat) dan PUT /cars/{car} (ubah, kirim seluruh field form).
 */
class SaveCarRequest extends FormRequest
{
    use NormalizesInput;

    public function car(): ?Car
    {
        $car = $this->route('car');

        return $car instanceof Car ? $car : null;
    }

    public function authorize(): bool
    {
        return $this->car()
            ? $this->user()->can('update', $this->car())
            : $this->user()->can('create', Car::class);
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->filled('plate_number')) {
            $merge['plate_number'] = Str::upper(preg_replace('/\s+/', ' ', trim((string) $this->input('plate_number'))));
        }
        if ($this->filled('slug')) {
            $merge['slug'] = Str::slug((string) $this->input('slug'));
        }
        if (is_array($this->input('features'))) {
            $merge['features'] = array_values(array_filter(array_map(
                fn ($f) => is_string($f) ? trim($f) : $f,
                $this->input('features'),
            ), fn ($f) => $f !== '' && $f !== null));
        }
        $this->merge($merge);
        $this->normalizeBooleans(['is_published']);
    }

    public function rules(): array
    {
        $car = $this->car();
        $maxYear = (int) now()->format('Y') + 1;
        $rupiah = ['integer', 'min:0', 'max:1000000000'];

        return [
            'brand' => ['required', 'string', 'max:60'],
            'model' => ['required', 'string', 'max:80'],
            // Contoh: B 1234 ABC, F 1576 FGH, D 12 A
            'plate_number' => [
                'required', 'string', 'max:15', 'regex:/^[A-Z]{1,2} \d{1,4}( [A-Z]{1,3})?$/',
                // Termasuk data terhapus: kolom DB unik untuk semua baris.
                Rule::unique('cars', 'plate_number')->ignore($car?->id),
            ],
            'transmission' => ['required', Rule::enum(Transmission::class)],
            'year' => ['required', 'integer', 'between:1990,'.$maxYear],
            'color' => ['nullable', 'string', 'max:40'],
            'seats' => ['nullable', 'integer', 'between:2,20'],
            'fuel_type' => ['nullable', 'string', 'max:30'],

            'rate_self_drive' => ['required', ...$rupiah, 'min:1'],
            'rate_with_driver' => ['required', ...$rupiah, 'min:1', 'gte:rate_self_drive'],
            'weekend_rate_self_drive' => ['nullable', ...$rupiah, 'min:1'],
            'weekend_rate_with_driver' => ['nullable', ...$rupiah, 'min:1'],
            'default_deposit' => ['nullable', ...$rupiah],

            'odometer' => ['required', 'integer', 'min:0', 'max:5000000'],
            'status' => ['required', Rule::enum(CarStatus::class)],

            'tax_due_date' => ['nullable', 'date'],
            'stnk_due_date' => ['nullable', 'date'],
            'next_service_date' => ['nullable', 'date'],
            'next_service_odometer' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'service_interval_km' => ['nullable', 'integer', 'min:500', 'max:100000'],

            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('cars', 'slug')->ignore($car?->id)],
            'is_published' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'features' => ['nullable', 'array', 'max:12'],
            'features.*' => ['string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $car = $this->car();
            $status = $this->input('status');

            // "disewa" diatur otomatis oleh transaksi sewa, tidak bisa dipilih manual.
            if ($status === CarStatus::Disewa->value && $car?->status !== CarStatus::Disewa) {
                $validator->errors()->add('status', 'Status "Disewa" diatur otomatis oleh transaksi sewa.');
            }
            if ($car?->status === CarStatus::Disewa && $status !== CarStatus::Disewa->value) {
                $validator->errors()->add('status', 'Mobil sedang disewa. Status akan berubah otomatis saat mobil kembali.');
            }

            // Odometer tidak boleh mundur (kecuali owner untuk koreksi salah input).
            if ($car && $this->filled('odometer') && (int) $this->input('odometer') < $car->odometer
                && ! $this->user()->hasRole('owner')) {
                $validator->errors()->add('odometer', 'Odometer tidak boleh lebih kecil dari nilai terakhir ('.number_format($car->odometer, 0, ',', '.').' km).');
            }

            // Toggle landing butuh permission car.publish.
            if ($this->has('is_published') && (bool) $this->input('is_published') !== (bool) ($car?->is_published ?? false)
                && ! $this->user()->can('publish', Car::class)) {
                $validator->errors()->add('is_published', 'Anda tidak berwenang mengubah tampilan mobil di landing page.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'brand' => 'merk',
            'model' => 'tipe',
            'plate_number' => 'plat nomor',
            'transmission' => 'transmisi',
            'year' => 'tahun',
            'color' => 'warna',
            'seats' => 'kapasitas kursi',
            'fuel_type' => 'bahan bakar',
            'rate_self_drive' => 'tarif lepas kunci',
            'rate_with_driver' => 'tarif dengan driver',
            'weekend_rate_self_drive' => 'tarif akhir pekan lepas kunci',
            'weekend_rate_with_driver' => 'tarif akhir pekan dengan driver',
            'default_deposit' => 'deposit',
            'odometer' => 'odometer',
            'status' => 'status operasional',
            'tax_due_date' => 'jatuh tempo pajak',
            'stnk_due_date' => 'jatuh tempo STNK',
            'next_service_date' => 'tanggal servis berikutnya',
            'next_service_odometer' => 'odometer servis berikutnya',
            'service_interval_km' => 'interval servis',
            'slug' => 'slug',
            'is_published' => 'tampilkan di landing',
            'description' => 'deskripsi',
            'features' => 'fitur unggulan',
            'features.*' => 'fitur unggulan',
            'notes' => 'catatan internal',
        ];
    }

    public function messages(): array
    {
        return [
            'plate_number.regex' => 'Format plat nomor tidak valid. Contoh: F 1576 FGH.',
            'plate_number.unique' => 'Plat nomor ini sudah terdaftar.',
            'rate_with_driver.gte' => 'Tarif dengan driver adalah total per hari termasuk driver, jadi tidak boleh lebih kecil dari tarif lepas kunci.',
            'slug.regex' => 'Slug hanya boleh berisi huruf kecil, angka, dan tanda hubung.',
            'slug.unique' => 'Slug ini sudah dipakai mobil lain.',
        ];
    }
}
