<?php

namespace App\Enums;

enum CarStatus: string
{
    use HasLabel;

    case Tersedia = 'tersedia';
    case Disewa = 'disewa';
    case Servis = 'servis';
    case Nonaktif = 'nonaktif';

    public function label(): string
    {
        return match ($this) {
            self::Tersedia => 'Tersedia',
            self::Disewa => 'Disewa',
            self::Servis => 'Servis',
            self::Nonaktif => 'Nonaktif',
        };
    }
}
