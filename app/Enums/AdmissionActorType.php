<?php

namespace App\Enums;

enum AdmissionActorType: string
{
    case Applicant = 'applicant';
    case Staff = 'staff';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Applicant => 'Pemohon',
            self::Staff => 'Staf',
            self::System => 'Sistem',
        };
    }
}
