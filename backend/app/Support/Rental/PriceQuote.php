<?php

namespace App\Support\Rental;

/**
 * Hasil perhitungan tarif sewa (immutable).
 *
 * @phpstan-type Line array{date: string, weekday: int, rate: int, special: bool}
 */
final class PriceQuote
{
    /**
     * @param  list<array{date: string, weekday: int, rate: int, special: bool}>  $lines
     */
    public function __construct(
        public readonly int $billedDays,
        public readonly int $durationMinutes,
        public readonly int $graceMinutes,
        public readonly int $baseRate,
        public readonly array $lines,
        public readonly int $rentalAmount,
    ) {}

    public function hasSpecialRates(): bool
    {
        foreach ($this->lines as $line) {
            if ($line['special']) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'billed_days' => $this->billedDays,
            'duration_minutes' => $this->durationMinutes,
            'grace_minutes' => $this->graceMinutes,
            'base_rate' => $this->baseRate,
            'lines' => $this->lines,
            'rental_amount' => $this->rentalAmount,
        ];
    }
}
