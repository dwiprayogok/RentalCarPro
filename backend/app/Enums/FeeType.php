<?php

namespace App\Enums;

enum FeeType: string
{
    use HasLabel;

    case Bbm = 'bbm';
    case Kebersihan = 'kebersihan';
    case KelebihanKm = 'kelebihan_km';
    case Kerusakan = 'kerusakan';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Bbm => 'BBM kurang',
            self::Kebersihan => 'Kebersihan',
            self::KelebihanKm => 'Kelebihan km',
            self::Kerusakan => 'Kerusakan',
            self::Lainnya => 'Lainnya',
        };
    }
}
