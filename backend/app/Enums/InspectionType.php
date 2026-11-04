<?php

namespace App\Enums;

enum InspectionType: string
{
    use HasLabel;

    case SerahTerima = 'serah_terima';
    case Kembali = 'kembali';

    public function label(): string
    {
        return match ($this) {
            self::SerahTerima => 'Serah Terima',
            self::Kembali => 'Kembali',
        };
    }
}
