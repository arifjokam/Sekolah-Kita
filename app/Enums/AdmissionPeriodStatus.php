<?php

namespace App\Enums;

enum AdmissionPeriodStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Open => 'Dibuka',
            self::Closed => 'Ditutup',
            self::Archived => 'Diarsipkan',
        };
    }
}
