<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Planned = 'planned';
    case Active = 'active';
    case Ended = 'ended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Direncanakan',
            self::Active => 'Aktif',
            self::Ended => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
