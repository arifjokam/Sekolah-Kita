<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Transferred = 'transferred';
    case Completed = 'completed';
    case Withdrawn = 'withdrawn';
    case OnLeave = 'on_leave';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Active => 'Aktif',
            self::Transferred => 'Dipindahkan',
            self::Completed => 'Selesai',
            self::Withdrawn => 'Keluar',
            self::OnLeave => 'Cuti',
        };
    }
}
