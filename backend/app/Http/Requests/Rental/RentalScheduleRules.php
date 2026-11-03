<?php

namespace App\Http\Requests\Rental;

use App\Enums\ServiceType;
use Illuminate\Validation\Rule;

/** Aturan bersama untuk Sewa Baru dan ringkasan biaya (quote). */
trait RentalScheduleRules
{
    /** @return array<string, mixed> */
    protected function scheduleRules(bool $strict): array
    {
        $req = $strict ? 'required' : 'nullable';

        return [
            'customer_id' => [$req, 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'car_id' => ['required', 'integer', Rule::exists('cars', 'id')->whereNull('deleted_at')],
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'driver_id' => ['nullable', 'integer', Rule::exists('drivers', 'id')->whereNull('deleted_at')],
            'start_now' => ['sometimes', 'boolean'],
            'start_at' => ['required_unless:start_now,true', 'nullable', 'date'],
            'planned_end_at' => ['required', 'date'],
            'deposit_required' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ];
    }

    /** @return array<string, string> */
    protected function scheduleAttributes(): array
    {
        return [
            'customer_id' => 'pelanggan',
            'car_id' => 'mobil',
            'service_type' => 'layanan',
            'driver_id' => 'driver',
            'start_at' => 'waktu mulai',
            'planned_end_at' => 'jam kembali rencana',
            'deposit_required' => 'deposit',
            'discount' => 'diskon',
            'discount_reason' => 'alasan diskon',
            'blacklist_override_reason' => 'alasan pengecualian blacklist',
            'notes' => 'catatan',
            'handover.odometer' => 'odometer serah terima',
            'handover.fuel_level' => 'level BBM',
            'handover.checklist' => 'checklist kondisi',
            'handover.notes' => 'catatan serah terima',
            'payments.*.type' => 'jenis pembayaran',
            'payments.*.method' => 'metode pembayaran',
            'payments.*.amount' => 'jumlah pembayaran',
            'payments.*.reference_number' => 'nomor referensi',
        ];
    }
}
