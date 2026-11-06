<?php

namespace App\Enums;

enum ShareBasis: string
{
    use HasLabel;

    case Kotor = 'kotor';
    case Bersih = 'bersih';

    public function label(): string
    {
        return match ($this) {
            self::Kotor => 'Dari pendapatan kotor',
            self::Bersih => 'Dari laba bersih',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Kotor => 'pendapatan kotor',
            self::Bersih => 'laba bersih',
        };
    }
}
