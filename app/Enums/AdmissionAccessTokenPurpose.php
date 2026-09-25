<?php

namespace App\Enums;

enum AdmissionAccessTokenPurpose: string
{
    case ContactVerification = 'contact_verification';
    case Status = 'status';
    case Revision = 'revision';
    case DocumentUpload = 'document_upload';
    case Withdrawal = 'withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::ContactVerification => 'Verifikasi kontak',
            self::Status => 'Status',
            self::Revision => 'Revisi',
            self::DocumentUpload => 'Unggah dokumen',
            self::Withdrawal => 'Pengajuan berhenti',
        };
    }
}
