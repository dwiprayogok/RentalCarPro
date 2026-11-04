<?php

namespace App\Enums;

/**
 * Jenis pergerakan uang pada sewa.
 *
 * - dp, pelunasan        : uang masuk untuk tagihan sewa.
 * - refund               : uang tagihan dikembalikan ke pelanggan (mengurangi terbayar).
 * - deposit              : dana titipan masuk (TIDAK dihitung sebagai pembayaran tagihan).
 * - deposit_kembali      : dana titipan dikembalikan ke pelanggan.
 * - deposit_potong       : dana titipan dipakai untuk menutup tagihan (denda/biaya),
 *                          dihitung sebagai pembayaran tagihan dan mengurangi saldo deposit.
 */
enum PaymentType: string
{
    use HasLabel;

    case Dp = 'dp';
    case Pelunasan = 'pelunasan';
    case Refund = 'refund';
    case Deposit = 'deposit';
    case DepositKembali = 'deposit_kembali';
    case DepositPotong = 'deposit_potong';

    public function label(): string
    {
        return match ($this) {
            self::Dp => 'DP',
            self::Pelunasan => 'Pelunasan',
            self::Refund => 'Refund',
            self::Deposit => 'Deposit',
            self::DepositKembali => 'Pengembalian Deposit',
            self::DepositPotong => 'Potong Deposit',
        };
    }

    /** Pengaruh terhadap "pembayaran diterima" untuk tagihan: +1, -1, atau 0. */
    public function billEffect(): int
    {
        return match ($this) {
            self::Dp, self::Pelunasan, self::DepositPotong => 1,
            self::Refund => -1,
            self::Deposit, self::DepositKembali => 0,
        };
    }

    /** Pengaruh terhadap saldo deposit yang ditahan: +1, -1, atau 0. */
    public function depositEffect(): int
    {
        return match ($this) {
            self::Deposit => 1,
            self::DepositKembali, self::DepositPotong => -1,
            default => 0,
        };
    }
}
