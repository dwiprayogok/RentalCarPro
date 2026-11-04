<?php

namespace App\Services\Rental;

use App\Enums\CarStatus;
use App\Enums\RiskStatus;
use App\Enums\ServiceType;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Carbon;

/**
 * Ringkasan biaya real-time untuk form Sewa Baru: harga, bentrok jadwal, dan peringatan.
 * Tidak menyimpan & tidak mengunci apa pun. Validasi final tetap di RentalService::create().
 */
class RentalQuoteService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly AvailabilityService $availability,
    ) {}

    /** @param array<string, mixed> $data */
    public function quote(array $data, User $by): array
    {
        $car = Car::query()->findOrFail($data['car_id']);
        $service = ServiceType::from($data['service_type']);
        $start = ! empty($data['start_now']) || empty($data['start_at']) ? now()->startOfMinute() : Carbon::parse($data['start_at'])->utc();
        $end = Carbon::parse($data['planned_end_at'])->utc();

        $errors = [];
        $warnings = [];

        if ($end->lte($start)) {
            $errors['planned_end_at'] = 'Jam kembali harus setelah waktu mulai sewa.';
        }

        $quote = $end->gt($start) ? $this->pricing->quote($car, $service, $start, $end) : null;
        $discount = (int) ($data['discount'] ?? 0);
        $deposit = array_key_exists('deposit_required', $data) && $data['deposit_required'] !== null
            ? (int) $data['deposit_required']
            : $car->default_deposit;

        if ($car->status === CarStatus::Nonaktif) {
            $errors['car_id'] = 'Mobil ini nonaktif dan tidak bisa disewakan.';
        } elseif ($car->status === CarStatus::Servis) {
            $warnings[] = 'Mobil sedang berstatus Servis.';
        }

        if ($quote) {
            $conflicts = $this->availability->carConflicts($car->id, $start, $end);
            if ($conflicts->isNotEmpty()) {
                $errors['car_id'] = $this->availability->conflictMessage('Mobil ini', $conflicts);
            }
        }

        if ($service === ServiceType::DenganDriver && ! empty($data['driver_id']) && $quote) {
            $driver = Driver::query()->find($data['driver_id']);
            $conflicts = $driver ? $this->availability->driverConflicts($driver->id, $start, $end) : collect();
            if ($driver && $conflicts->isNotEmpty()) {
                $errors['driver_id'] = $this->availability->conflictMessage("Driver {$driver->name}", $conflicts);
            }
        }

        if (! empty($data['customer_id']) && $customer = Customer::query()->find($data['customer_id'])) {
            if ($customer->risk_status === RiskStatus::Blacklist) {
                $errors['customer_id'] = $by->can(Permissions::RENTAL_OVERRIDE_BLACKLIST)
                    ? 'Pelanggan Blacklist: isi alasan pengecualian untuk melanjutkan.'
                    : 'Pelanggan ini berstatus Blacklist dan tidak bisa membuat sewa baru.';
            } elseif ($customer->risk_status === RiskStatus::Perhatian) {
                $warnings[] = 'Pelanggan berstatus Perhatian: '.($customer->risk_note ?? 'periksa catatan risiko.');
            }
            if ($service === ServiceType::LepasKunci && ($missing = $customer->selfDriveMissing()) !== []) {
                $errors['customer_id'] ??= 'Data pelanggan belum lengkap untuk Lepas Kunci: '.implode(', ', $missing).'.';
            }
        }

        $rentalAmount = $quote?->rentalAmount ?? 0;

        return [
            'pricing' => $quote?->toArray(),
            'daily_rate' => $this->pricing->dailyRate($car, $service),
            'rental_amount' => $rentalAmount,
            'discount' => min($discount, $rentalAmount),
            'total_amount' => max(0, $rentalAmount - $discount),
            'deposit_required' => $deposit,
            'car_odometer' => $car->odometer,
            'buffer_minutes' => $this->availability->bufferMinutes(),
            'available' => ! isset($errors['car_id']),
            'errors' => (object) $errors,
            'warnings' => $warnings,
        ];
    }
}
