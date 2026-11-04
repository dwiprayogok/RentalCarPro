<?php

namespace App\Enums;

enum PaymentMethod: string
{
    use HasLabel;

    case Tunai = 'tunai';
    case Transfer = 'transfer';
    case Qris = 'qris';

    public function label(): string
    {
        return match ($this) {
            self::Tunai => 'Tunai',
            self::Transfer => 'Transfer',
            self::Qris => 'QRIS',
        };
    }
}
