<?php

namespace App\Support\Rental;

final class LateFee
{
    public function __construct(
        public readonly int $lateMinutes,
        public readonly bool $withinGrace,
        public readonly int $extraDays,
        public readonly int $extraDaysAmount,
        public readonly int $chargedHours,
        public readonly int $hourlyFee,
        public readonly bool $capped,
    ) {}

    public static function none(): self
    {
        return new self(0, false, 0, 0, 0, 0, false);
    }

    /** Total yang harus ditagihkan karena keterlambatan (hari tambahan + denda jam). */
    public function total(): int
    {
        return $this->extraDaysAmount + $this->hourlyFee;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'late_minutes' => $this->lateMinutes,
            'within_grace' => $this->withinGrace,
            'extra_days' => $this->extraDays,
            'extra_days_amount' => $this->extraDaysAmount,
            'charged_hours' => $this->chargedHours,
            'hourly_fee' => $this->hourlyFee,
            'capped' => $this->capped,
            'total' => $this->total(),
        ];
    }
}
