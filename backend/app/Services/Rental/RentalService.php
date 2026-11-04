<?php

namespace App\Services\Rental;

use App\Enums\BookingRequestStatus;
use App\Enums\CarStatus;
use App\Enums\InspectionType;
use App\Enums\RentalStatus;
use App\Enums\ServiceType;
use App\Models\BookingRequest;
use App\Models\Car;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Rental;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Support\Permissions;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur sewa: buat (booking / langsung aktif), serah terima, batal.
 *
 * Anti double-booking: di dalam satu transaksi, baris mobil (dan driver) dikunci dengan
 * SELECT ... FOR UPDATE SEBELUM cek bentrok. Transaksi lain untuk mobil yang sama harus
 * menunggu sampai transaksi ini commit, lalu melihat sewa yang baru dibuat.
 */
class RentalService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly AvailabilityService $availability,
        private readonly DocumentNumberService $numbers,
        private readonly PaymentService $payments,
        private readonly InspectionService $inspections,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Data tervalidasi dari StoreRentalRequest
     */
    public function create(array $data, User $by): Rental
    {
        $startNow = (bool) ($data['start_now'] ?? false);
        $service = ServiceType::from($data['service_type']);
        $start = $startNow ? now()->startOfMinute() : Carbon::parse($data['start_at'])->utc();
        $end = Carbon::parse($data['planned_end_at'])->utc();

        if ($end->lte($start)) {
            throw ValidationException::withMessages(['planned_end_at' => 'Jam kembali harus setelah waktu mulai sewa.']);
        }

        // attempts: 3 → diulang otomatis bila MySQL melaporkan deadlock / serialization failure.
        return DB::transaction(function () use ($data, $by, $startNow, $service, $start, $end) {
            // 1) Kunci sumber daya lebih dulu (urutan tetap: mobil lalu driver) untuk mencegah deadlock.
            /** @var Car $car */
            $car = Car::query()->lockForUpdate()->findOrFail($data['car_id']);
            $driver = isset($data['driver_id'])
                ? Driver::query()->lockForUpdate()->findOrFail($data['driver_id'])
                : null;

            $this->raceWindowForTests();

            // Konversi dari Permintaan Booking (landing): kunci baris agar tidak dikonversi dua kali.
            $bookingRequest = isset($data['booking_request_id'])
                ? BookingRequest::query()->lockForUpdate()->findOrFail($data['booking_request_id'])
                : null;
            if ($bookingRequest) {
                if (! $by->can('convert', $bookingRequest)) {
                    throw ValidationException::withMessages(['booking_request_id' => 'Anda tidak berwenang mengonversi permintaan booking.']);
                }
                if (! in_array($bookingRequest->status, [BookingRequestStatus::Baru, BookingRequestStatus::Dihubungi], true)
                    || Rental::query()->where('booking_request_id', $bookingRequest->id)->exists()) {
                    throw ValidationException::withMessages(['booking_request_id' => 'Permintaan booking ini sudah diproses (dikonfirmasi atau ditolak).']);
                }
            }

            // 2) Validasi bisnis (setelah kunci, agar data yang dibaca terbaru).
            $customer = Customer::query()->findOrFail($data['customer_id']);
            $this->assertCustomerAllowed($customer, $service, $data, $by);
            $this->assertCarUsable($car, $startNow);
            $this->assertDriver($service, $driver, $start, $end);

            $conflicts = $this->availability->carConflicts($car->id, $start, $end);
            if ($conflicts->isNotEmpty()) {
                throw ValidationException::withMessages(['car_id' => $this->availability->conflictMessage('Mobil ini', $conflicts)]);
            }

            // 3) Harga.
            $quote = $this->pricing->quote($car, $service, $start, $end);
            $discount = (int) ($data['discount'] ?? 0);
            if ($discount > 0 && ! $by->can(Permissions::RENTAL_DISCOUNT)) {
                throw ValidationException::withMessages(['discount' => 'Anda tidak berwenang memberi diskon.']);
            }
            if ($discount > $quote->rentalAmount) {
                throw ValidationException::withMessages(['discount' => 'Diskon tidak boleh melebihi biaya sewa.']);
            }

            $override = $customer->isBlacklisted();

            // 4) Simpan.
            $rental = Rental::create([
                'code' => $this->numbers->next('TRX'),
                'customer_id' => $customer->id,
                'car_id' => $car->id,
                'driver_id' => $driver?->id,
                'booking_request_id' => $bookingRequest?->id,
                'service_type' => $service,
                'status' => $startNow ? RentalStatus::Aktif : RentalStatus::Booking,
                'start_at' => $start,
                'planned_end_at' => $end,
                'picked_up_at' => $startNow ? now() : null,
                'daily_rate' => $quote->baseRate,
                'billed_days' => $quote->billedDays,
                'rental_amount' => $quote->rentalAmount,
                'price_breakdown' => $quote->toArray(),
                'discount' => $discount,
                'discount_reason' => $discount > 0 ? ($data['discount_reason'] ?? null) : null,
                'total_amount' => $quote->rentalAmount - $discount,
                'deposit_required' => (int) ($data['deposit_required'] ?? $car->default_deposit),
                'blacklist_override_by' => $override ? $by->id : null,
                'blacklist_override_reason' => $override ? $data['blacklist_override_reason'] : null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $by->id,
                'updated_by' => $by->id,
            ]);

            if ($bookingRequest) {
                $bookingRequest->forceFill([
                    'status' => BookingRequestStatus::Dikonfirmasi, 'handled_by' => $by->id, 'handled_at' => now(),
                ])->save();
            }

            if ($override) {
                activity('rental')->performedOn($rental)->causedBy($by)->event('blacklist_override')
                    ->withProperties(['customer_id' => $customer->id, 'reason' => $data['blacklist_override_reason']])
                    ->log('Override blacklist pelanggan');
            }

            if ($startNow) {
                $this->doHandover($rental, $car, $data['handover'], $by);
            }

            foreach ($data['payments'] ?? [] as $payment) {
                $this->payments->record($rental, $payment, $by);
            }

            return $rental->refresh();
        }, attempts: 3);
    }

    /**
     * Serah terima untuk sewa berstatus booking → aktif.
     *
     * @param  array{odometer: int, fuel_level: int, checklist?: ?array, notes?: ?string}  $handover
     */
    public function handover(Rental $rental, array $handover, User $by): Rental
    {
        return DB::transaction(function () use ($rental, $handover, $by) {
            $car = Car::query()->lockForUpdate()->findOrFail($rental->car_id);
            /** @var Rental $rental */
            $rental = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if ($rental->status !== RentalStatus::Booking) {
                throw ValidationException::withMessages(['status' => 'Serah terima hanya untuk sewa berstatus Booking.']);
            }

            $this->assertCarUsable($car, true);

            // Diambil lebih awal dari jadwal? Pastikan tidak menabrak sewa sebelumnya.
            $from = now()->min($rental->start_at);
            $conflicts = $this->availability->carConflicts($car->id, $from, $rental->planned_end_at, $rental->id);
            if ($conflicts->isNotEmpty()) {
                throw ValidationException::withMessages(['car_id' => $this->availability->conflictMessage('Mobil ini', $conflicts)]);
            }

            $rental->forceFill(['status' => RentalStatus::Aktif, 'picked_up_at' => now(), 'updated_by' => $by->id])->save();
            $this->doHandover($rental, $car, $handover, $by);

            return $rental->refresh();
        }, attempts: 3);
    }

    public function cancel(Rental $rental, string $reason, User $by): Rental
    {
        return DB::transaction(function () use ($rental, $reason, $by) {
            /** @var Rental $rental */
            $rental = Rental::query()->lockForUpdate()->findOrFail($rental->id);
            if ($rental->status !== RentalStatus::Booking) {
                throw ValidationException::withMessages(['status' => 'Hanya sewa berstatus Booking yang bisa dibatalkan. Sewa aktif diselesaikan lewat proses Kembali.']);
            }

            $rental->forceFill([
                'status' => RentalStatus::Batal,
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
                'updated_by' => $by->id,
            ])->save();

            return $rental;
        });
    }

    /** @param array{odometer: int, fuel_level: int, checklist?: ?array, notes?: ?string} $handover */
    private function doHandover(Rental $rental, Car $car, array $handover, User $by): void
    {
        if ((int) $handover['odometer'] < $car->odometer) {
            throw ValidationException::withMessages([
                'handover.odometer' => 'Odometer serah terima tidak boleh lebih kecil dari odometer terakhir mobil ('.number_format($car->odometer, 0, ',', '.').' km).',
            ]);
        }

        $this->inspections->create($rental, InspectionType::SerahTerima, $handover, $by);
        $car->forceFill(['status' => CarStatus::Disewa, 'odometer' => (int) $handover['odometer']])->save();
    }

    /** @param array<string, mixed> $data */
    private function assertCustomerAllowed(Customer $customer, ServiceType $service, array $data, User $by): void
    {
        if ($customer->isBlacklisted()) {
            if (! $by->can(Permissions::RENTAL_OVERRIDE_BLACKLIST)) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Pelanggan ini berstatus Blacklist dan tidak bisa membuat sewa baru. Hanya pemilik yang dapat memberi pengecualian.',
                ]);
            }
            if (blank($data['blacklist_override_reason'] ?? null)) {
                throw ValidationException::withMessages([
                    'blacklist_override_reason' => 'Alasan pengecualian wajib diisi untuk pelanggan Blacklist.',
                ]);
            }
        }

        if ($service === ServiceType::LepasKunci && ($missing = $customer->selfDriveMissing()) !== []) {
            throw ValidationException::withMessages([
                'customer_id' => 'Data pelanggan belum lengkap untuk layanan Lepas Kunci: '.implode(', ', $missing).'.',
            ]);
        }
    }

    private function assertCarUsable(Car $car, bool $startNow): void
    {
        if ($car->status === CarStatus::Nonaktif) {
            throw ValidationException::withMessages(['car_id' => 'Mobil ini nonaktif dan tidak bisa disewakan.']);
        }
        if ($startNow && $car->status === CarStatus::Servis) {
            throw ValidationException::withMessages(['car_id' => 'Mobil sedang servis. Ubah status ke Tersedia setelah servis selesai.']);
        }
    }

    private function assertDriver(ServiceType $service, ?Driver $driver, Carbon $start, Carbon $end): void
    {
        if ($service === ServiceType::LepasKunci) {
            if ($driver) {
                throw ValidationException::withMessages(['driver_id' => 'Layanan Lepas Kunci tidak memakai driver.']);
            }

            return;
        }

        if (! $driver) {
            throw ValidationException::withMessages(['driver_id' => 'Pilih driver untuk layanan Dengan Driver.']);
        }
        if (! $driver->is_active) {
            throw ValidationException::withMessages(['driver_id' => 'Driver ini sedang nonaktif.']);
        }
        if ($driver->license_expires_at && $driver->license_expires_at->lt($end->copy()->setTimezone(config('app.display_timezone'))->startOfDay())) {
            throw ValidationException::withMessages(['driver_id' => 'SIM driver kedaluwarsa sebelum sewa berakhir.']);
        }

        $conflicts = $this->availability->driverConflicts($driver->id, $start, $end);
        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages(['driver_id' => $this->availability->conflictMessage("Driver {$driver->name}", $conflicts)]);
        }
    }

    /**
     * Hanya untuk test race condition: menahan transaksi sesudah mengunci baris
     * agar proses paralel benar-benar saling tumpang tindih. Tidak aktif di luar testing.
     */
    private function raceWindowForTests(): void
    {
        $ms = (int) Arr::get($_SERVER, 'RENTALPRO_RACE_DELAY_MS', getenv('RENTALPRO_RACE_DELAY_MS') ?: 0);
        if ($ms > 0 && app()->environment('testing')) {
            usleep($ms * 1000);
        }
    }
}
