<?php

namespace App\Enums;

enum AdmissionDocumentType: string
{
    case Identity = 'identity';
    case BirthCertificate = 'birth_certificate';
    case PreviousSchoolReport = 'previous_school_report';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Identity => 'Dokumen identitas',
            self::BirthCertificate => 'Akta kelahiran',
            self::PreviousSchoolReport => 'Rapor sekolah sebelumnya',
            self::Other => 'Dokumen pendukung',
        };
    }
}
