<?php

namespace App\Enums;

enum UserRole: string
{
    use HasLabel;

    case Owner = 'owner';
    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Pemilik',
            self::Admin => 'Admin',
            self::Staff => 'Staf',
        };
    }
}
