<?php

namespace App\Services\Rental;

use App\Enums\RentalStatus;
use App\Models\Rental;
use App\Support\Rental\LateFee;

/**
 * Ringkasan tagihan sebuah sewa (dihitung, tidak disimpan):
 * total, terbayar, sisa, estimasi denda berjalan, dan label status pembayaran.
 */
class RentalBilling
{
    public function __construct(private readonly PricingService $pricing) {}

    public function runningLateFee(Rental $rental): LateFee
    {
        if (! $rental->isOverdue()) {
            return LateFee::none();
        }

        return $this->pricing->lateFee($rental->planned_end_at, now(), $rental->daily_rate);
    }

    /** @return array<string, mixed> */
    public function summary(Rental $rental): array
    {
        $balance = max(0, $rental->total_amount - $rental->paid_amount);
        $late = $this->runningLateFee($rental);
        $running = $late->total();

        [$status, $label] = match (true) {
            $rental->status === RentalStatus::Batal => ['batal', 'Batal'],
            $rental->total_amount > 0 && $rental->paid_amount <= 0 => ['belum_bayar', 'Belum bayar'],
            $balance > 0 => ['belum_lunas', 'Belum lunas'],
            $running > 0 => ['lunas_belum_denda', 'Lunas (belum termasuk denda)'],
            default => ['lunas', 'Lunas'],
        };

        return [
            'total_amount' => $rental->total_amount,
            'paid_amount' => $rental->paid_amount,
            'balance' => $balance,
            'running_late_fee' => $running,
            'running_late' => $running > 0 || $late->withinGrace ? $late->toArray() : null,
            'estimated_due' => $balance + $running,
            'deposit_required' => $rental->deposit_required,
            'deposit_held' => $rental->deposit_held,
            'deposit_outstanding' => max(0, $rental->deposit_required - $rental->deposit_held),
            'payment_status' => $status,
            'payment_status_label' => $label,
        ];
    }
}
