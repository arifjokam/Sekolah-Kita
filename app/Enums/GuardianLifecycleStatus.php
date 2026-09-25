<?php

namespace App\Enums;

enum GuardianLifecycleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Tidak aktif',
            self::Archived => 'Diarsipkan',
        };
    }
}
