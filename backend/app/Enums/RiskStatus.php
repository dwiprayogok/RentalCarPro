<?php

namespace App\Enums;

enum RiskStatus: string
{
    use HasLabel;

    case Lancar = 'lancar';
    case Perhatian = 'perhatian';
    case Blacklist = 'blacklist';

    public function label(): string
    {
        return match ($this) {
            self::Lancar => 'Lancar',
            self::Perhatian => 'Perhatian',
            self::Blacklist => 'Blacklist',
        };
    }

    /** Status perhatian dan blacklist wajib disertai catatan risiko. */
    public function requiresNote(): bool
    {
        return $this !== self::Lancar;
    }
}
