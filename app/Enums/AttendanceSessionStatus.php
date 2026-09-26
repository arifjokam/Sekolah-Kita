<?php

namespace App\Enums;

enum AttendanceSessionStatus: string
{
    case Open = 'open';
    case Submitted = 'submitted';
    case Corrected = 'corrected';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Belum diisi',
            self::Submitted => 'Sudah diisi',
            self::Corrected => 'Dikoreksi',
        };
    }

    public function isEditable(): bool
    {
        return $this !== self::Submitted;
    }
}
