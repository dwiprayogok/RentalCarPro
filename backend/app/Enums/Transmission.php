<?php

namespace App\Enums;

enum Transmission: string
{
    use HasLabel;

    case Matic = 'matic';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Matic => 'Matic',
            self::Manual => 'Manual',
        };
    }
}
