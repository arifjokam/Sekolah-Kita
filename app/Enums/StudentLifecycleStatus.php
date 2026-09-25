<?php

namespace App\Enums;

enum StudentLifecycleStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Graduated = 'graduated';
    case Transferred = 'transferred';
    case Withdrawn = 'withdrawn';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::OnLeave => 'Cuti',
            self::Graduated => 'Lulus',
            self::Transferred => 'Mutasi',
            self::Withdrawn => 'Keluar',
            self::Archived => 'Diarsipkan',
        };
    }
}
