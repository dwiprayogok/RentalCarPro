<?php

namespace App\Enums;

enum BookingRequestStatus: string
{
    use HasLabel;

    case Baru = 'baru';
    case Dihubungi = 'dihubungi';
    case Dikonfirmasi = 'dikonfirmasi';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::Dihubungi => 'Dihubungi',
            self::Dikonfirmasi => 'Dikonfirmasi',
            self::Ditolak => 'Ditolak',
        };
    }
}
