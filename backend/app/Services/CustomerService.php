<?php

namespace App\Services;

use App\Enums\RentalStatus;
use App\Enums\RiskStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    /** Field yang disimpan dari form (for_self_drive hanya untuk validasi). */
    private const FIELDS = [
        'name', 'phone', 'nik', 'sim_number', 'sim_type', 'sim_expires_at', 'address',
        'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'risk_status', 'risk_note', 'notes',
    ];

    /** @param array<string, mixed> $data */
    public function create(array $data, User $by): Customer
    {
        $attributes = Arr::only($data, self::FIELDS);
        $attributes['risk_status'] ??= RiskStatus::Lancar->value;
        $attributes['created_by'] = $by->id;

        if ($attributes['risk_status'] !== RiskStatus::Lancar->value) {
            $attributes['risk_updated_at'] = now();
            $attributes['risk_updated_by'] = $by->id;
        }
        if ($attributes['risk_status'] === RiskStatus::Lancar->value) {
            $attributes['risk_note'] = $attributes['risk_note'] ?? null;
        }

        return Customer::create($attributes);
    }

    /** @param array<string, mixed> $data */
    public function update(Customer $customer, array $data, User $by): Customer
    {
        $attributes = Arr::only($data, self::FIELDS);

        if (blank($attributes['nik'] ?? null)) {
            unset($attributes['nik']); // NIK kosong = tidak diubah
        }

        return DB::transaction(function () use ($customer, $attributes, $by) {
            $customer->fill($attributes);

            if ($customer->isDirty('risk_status')) {
                $customer->risk_updated_at = now();
                $customer->risk_updated_by = $by->id;
            }

            $customer->save();

            return $customer;
        });
    }

    public function delete(Customer $customer): void
    {
        if ($customer->rentals()->whereIn('status', RentalStatus::blocking())->exists()) {
            throw ValidationException::withMessages([
                'customer' => 'Pelanggan masih memiliki sewa berstatus Booking/Aktif.',
            ]);
        }

        $customer->delete();
    }
}
