<?php

namespace App\Enums;

enum ServiceType: string
{
    use HasLabel;

    case LepasKunci = 'lepas_kunci';
    case DenganDriver = 'dengan_driver';

    public function label(): string
    {
        return match ($this) {
            self::LepasKunci => 'Lepas Kunci',
            self::DenganDriver => 'Dengan Driver',
        };
    }
}
