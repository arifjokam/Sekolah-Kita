<?php

namespace App\Enums;

enum TeacherEmploymentStatus: string
{
    case Active = 'active';
    case Leave = 'leave';
    case Inactive = 'inactive';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Leave => 'Cuti',
            self::Inactive => 'Tidak aktif',
            self::Retired => 'Pensiun',
        };
    }
}
