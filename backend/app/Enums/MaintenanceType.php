<?php

namespace App\Enums;

enum MaintenanceType: string
{
    use HasLabel;

    case ServisRutin = 'servis_rutin';
    case Perbaikan = 'perbaikan';
    case Ban = 'ban';
    case Pajak = 'pajak';
    case Stnk = 'stnk';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::ServisRutin => 'Servis rutin',
            self::Perbaikan => 'Perbaikan',
            self::Ban => 'Ban',
            self::Pajak => 'Pajak tahunan',
            self::Stnk => 'Perpanjang STNK',
            self::Lainnya => 'Lainnya',
        };
    }
}
