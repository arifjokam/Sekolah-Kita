<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Tidak aktif',
            self::Suspended => 'Ditangguhkan',
        };
    }
}
