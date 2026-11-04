<?php

namespace App\Services\Rental;

use App\Enums\CarStatus;
use App\Enums\FeeType;
use App\Enums\InspectionType;
use App\Enums\MaintenanceType;
use App\Enums\PaymentType;
use App\Enums\RentalStatus;
use App\Enums\RiskStatus;
use App\Models\Car;
use App\Models\Customer;
use App\Models\MaintenanceRecord;
use App\Models\Rental;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Proses Kembali.
 *
 *  total akhir   = sewa − diskon + denda keterlambatan + biaya tambahan
 *  sisa sebelum  = total akhir − pembayaran diterima
 *  potong deposit (default) = min(deposit ditahan, sisa sebelum)
 *  pembayaran akhir         ≤ sisa setelah potongan
 *  deposit kembali          = deposit ditahan − potongan (SELALU diproses saat Kembali)
 *
 * preview() murni (tanpa menulis). complete() melakukan semuanya dalam SATU transaksi:
 * inspeksi kembali, biaya tambahan, denda, pembayaran, deposit, odometer & status mobil,
 * status sewa "selesai", auto-flag pelanggan, dan activity log.
 */
class ReturnService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly PaymentService $payments,
        private readonly InspectionService $inspections,
        private readonly SettingService $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function preview(Rental $rental, array $data): array
    {
        $this->assertActive($rental);

        return $this->summarize($rental, $data, strict: false);
    }

    /** @param array<string, mixed> $data */
    public function complete(Rental $rental, array $data, User $by): Rental
    {
        return DB::transaction(function () use ($rental, $data, $by) {
            /** @var Car $car */
            $car = Car::query()->lockForUpdate()->findOrFail($rental->car_id);
            /** @var Rental $rental */
            $rental = Rental::query()->lockForUpdate()->findOrFail($rental->id);
            $rental->load(['customer', 'car', 'handoverInspection']);
            $this->assertActive($rental);

            $s = $this->summarize($rental, $data, strict: true);
            $returnedAt = Carbon::parse($s['returned_at'])->utc();

            // 1) Inspeksi kembali.
            $this->inspections->create($rental, InspectionType::Kembali, [
                'odometer' => $data['odometer'],
                'fuel_level' => $data['fuel_level'],
                'checklist' => $data['checklist'] ?? [],
                'notes' => $data['notes'] ?? null,
            ], $by);

            // 2) Biaya tambahan (masing-masing dengan alasan).
            foreach ($s['fees'] as $fee) {
                $rental->fees()->create($fee + ['created_by' => $by->id]);
            }

            // 3) Denda & total akhir.
            $rental->forceFill([
                'returned_at' => $returnedAt,
                'late_fee' => $s['late']['total'],
                'late_extra_days' => $s['late']['extra_days'],
                'extra_fees_total' => $s['extra_fees_total'],
                'total_amount' => $s['total_amount'],
                'updated_by' => $by->id,
            ])->save();

            // 4) Pembayaran & deposit (PaymentService memvalidasi ulang terhadap total baru).
            if ($s['deposit_deduction'] > 0) {
                $this->payments->record($rental, [
                    'type' => PaymentType::DepositPotong->value, 'method' => null, 'amount' => $s['deposit_deduction'],
                    'paid_at' => $returnedAt, 'notes' => 'Potong deposit saat Kembali',
                ], $by);
            }
            if ($s['payment_amount'] > 0) {
                $this->payments->record($rental, [
                    'type' => PaymentType::Pelunasan->value, 'method' => $data['payment']['method'], 'amount' => $s['payment_amount'],
                    'paid_at' => $returnedAt, 'reference_number' => $data['payment']['reference_number'] ?? null,
                ], $by);
            }
            if ($s['deposit_refund'] > 0) {
                $this->payments->record($rental, [
                    'type' => PaymentType::DepositKembali->value, 'method' => $data['refund_method'], 'amount' => $s['deposit_refund'],
                    'paid_at' => $returnedAt, 'notes' => $s['deposit_deduction'] > 0 ? 'Deposit dikembalikan setelah dipotong' : 'Deposit dikembalikan penuh',
                ], $by);
            }

            // 5) Mobil: odometer + status.
            $carStatus = CarStatus::from($s['car_status']);
            $car->forceFill(['odometer' => max($car->odometer, (int) $data['odometer']), 'status' => $carStatus])->save();
            if ($carStatus === CarStatus::Servis && $s['has_damage']) {
                MaintenanceRecord::create([
                    'car_id' => $car->id,
                    'rental_id' => $rental->id,
                    'type' => MaintenanceType::Perbaikan,
                    'title' => "Perbaikan kerusakan dari {$rental->code}",
                    'performed_at' => $returnedAt->copy()->setTimezone(config('app.display_timezone'))->toDateString(),
                    'odometer' => (int) $data['odometer'],
                    'cost' => 0,
                    'notes' => collect($s['fees'])->where('type', FeeType::Kerusakan->value)->pluck('reason')->implode('; '),
                    'created_by' => $by->id,
                ]);
            }

            // 6) Selesai.
            $rental->forceFill(['status' => RentalStatus::Selesai])->save();

            // 7) Auto-flag pelanggan terlambat.
            if ($s['customer_flag']) {
                $customer = Customer::query()->lockForUpdate()->findOrFail($rental->customer_id);
                if ($customer->risk_status === RiskStatus::Lancar) {
                    $customer->forceFill([
                        'risk_status' => RiskStatus::Perhatian,
                        'risk_note' => trim(($customer->risk_note ? $customer->risk_note."\n" : '')
                            ."Otomatis: terlambat {$s['late']['late_label']} pada {$rental->code}."),
                        'risk_updated_at' => now(),
                        'risk_updated_by' => $by->id,
                    ])->save();
                }
            }

            activity('rental')->performedOn($rental)->causedBy($by)->event('returned')
                ->withProperties([
                    'returned_at' => $returnedAt->toIso8601String(),
                    'late' => $s['late'],
                    'extra_fees_total' => $s['extra_fees_total'],
                    'total_amount' => $s['total_amount'],
                    'deposit_deduction' => $s['deposit_deduction'],
                    'deposit_refund' => $s['deposit_refund'],
                    'payment_amount' => $s['payment_amount'],
                    'remaining' => $s['remaining'],
                    'car_status' => $s['car_status'],
                    'customer_flagged' => $s['customer_flag'],
                ])
                ->log("Mobil kembali, sewa {$rental->code} selesai");

            return $rental->refresh();
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function summarize(Rental $rental, array $data, bool $strict): array
    {
        $tz = config('app.display_timezone');
        $returnedAt = ! empty($data['returned_at']) ? Carbon::parse($data['returned_at'])->utc() : now()->startOfMinute();
        $pickedUp = $rental->picked_up_at ?? $rental->start_at;

        $errors = [];
        if ($returnedAt->gt(now()->addMinute())) {
            $errors['returned_at'] = 'Waktu kembali tidak boleh di masa depan.';
        }
        if ($returnedAt->lt($pickedUp)) {
            $errors['returned_at'] = 'Waktu kembali tidak boleh sebelum mobil diserahkan.';
        }

        $startOdometer = $rental->handoverInspection?->odometer ?? $rental->car->odometer;
        if (isset($data['odometer']) && (int) $data['odometer'] < $startOdometer) {
            $errors['odometer'] = 'Odometer akhir tidak boleh lebih kecil dari odometer serah terima ('.number_format($startOdometer, 0, ',', '.').' km).';
        }

        // Denda keterlambatan.
        $late = $this->pricing->lateFee($rental->planned_end_at, $returnedAt, $rental->daily_rate);
        $lateArr = $late->toArray() + ['late_label' => self::duration($late->lateMinutes)];

        // Biaya tambahan.
        $fees = collect($data['fees'] ?? [])
            ->filter(fn ($f) => (int) ($f['amount'] ?? 0) > 0)
            ->map(fn ($f) => ['type' => $f['type'], 'amount' => (int) $f['amount'], 'reason' => trim((string) ($f['reason'] ?? ''))])
            ->values()->all();
        foreach ($data['fees'] ?? [] as $i => $f) {
            if ((int) ($f['amount'] ?? 0) > 0 && trim((string) ($f['reason'] ?? '')) === '') {
                $errors["fees.{$i}.reason"] = 'Alasan wajib diisi untuk setiap biaya tambahan.';
            }
        }
        $extra = array_sum(array_column($fees, 'amount'));
        $hasDamage = collect($fees)->contains('type', FeeType::Kerusakan->value);

        $total = max(0, $rental->rental_amount - $rental->discount) + $late->total() + $extra;
        $paid = $rental->paid_amount;
        $balanceBefore = max(0, $total - $paid);
        $held = max(0, $rental->deposit_held);

        $maxDeduction = min($held, $balanceBefore);
        $deduction = array_key_exists('deposit_deduction', $data) && $data['deposit_deduction'] !== null
            ? (int) $data['deposit_deduction']
            : $maxDeduction;
        if ($deduction > $maxDeduction) {
            $errors['deposit_deduction'] = 'Potongan deposit maksimal '.self::rupiah($maxDeduction).' (deposit ditahan / sisa tagihan).';
            $deduction = $maxDeduction;
        }

        $afterDeduction = $balanceBefore - $deduction;
        $payment = (int) ($data['payment']['amount'] ?? 0);
        if ($payment > $afterDeduction) {
            $errors['payment.amount'] = 'Pembayaran melebihi sisa tagihan ('.self::rupiah($afterDeduction).').';
            $payment = $afterDeduction;
        }
        $refund = $held - $deduction;
        if ($strict && $refund > 0 && empty($data['refund_method'])) {
            $errors['refund_method'] = 'Pilih metode pengembalian deposit.';
        }
        if ($strict && $payment > 0 && empty($data['payment']['method'])) {
            $errors['payment.method'] = 'Pilih metode pembayaran.';
        }

        $carStatus = $data['car_status'] ?? ($hasDamage ? CarStatus::Servis->value : CarStatus::Tersedia->value);
        $customerFlag = $late->total() > 0
            && $this->settings->bool('auto_flag_late_customer', true)
            && $rental->customer->risk_status === RiskStatus::Lancar;

        if ($strict && $errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'returned_at' => $returnedAt->copy()->setTimezone($tz)->toIso8601String(),
            'planned_end_at' => $rental->planned_end_at->copy()->setTimezone($tz)->toIso8601String(),
            'start_odometer' => $startOdometer,
            'distance_km' => isset($data['odometer']) ? max(0, (int) $data['odometer'] - $startOdometer) : null,
            'late' => $lateArr,
            'fees' => $fees,
            'extra_fees_total' => $extra,
            'rental_amount' => $rental->rental_amount,
            'discount' => $rental->discount,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'balance_before' => $balanceBefore,
            'deposit_held' => $held,
            'deposit_deduction_max' => $maxDeduction,
            'deposit_deduction' => $deduction,
            'payment_amount' => $payment,
            'remaining' => $afterDeduction - $payment,
            'deposit_refund' => $refund,
            'has_damage' => $hasDamage,
            'car_status' => $carStatus,
            'car_status_suggested' => $hasDamage ? CarStatus::Servis->value : CarStatus::Tersedia->value,
            'customer_flag' => $customerFlag,
            'errors' => (object) $errors,
        ];
    }

    private function assertActive(Rental $rental): void
    {
        if ($rental->status !== RentalStatus::Aktif) {
            throw ValidationException::withMessages(['status' => 'Proses Kembali hanya untuk sewa berstatus Aktif.']);
        }
    }

    public static function duration(int $minutes): string
    {
        $d = intdiv($minutes, 1440);
        $h = intdiv($minutes % 1440, 60);
        $m = $minutes % 60;
        $parts = array_filter([$d ? "{$d} hari" : null, $h ? "{$h} jam" : null, (! $d && $m) ? "{$m} menit" : null]);

        return $parts ? implode(' ', $parts) : '0 menit';
    }

    private static function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
