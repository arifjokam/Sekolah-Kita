<?php

namespace App\Enums;

enum AdmissionDocumentVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
    case Expired = 'expired';

    /**
     * Returned to the applicant with a reviewer note.
     *
     * Distinct from Rejected: a rejected document ends that category for the
     * application, while a document needing revision can simply be replaced by a
     * new version without restarting the whole application.
     */
    case NeedsRevision = 'needs_revision';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu verifikasi',
            self::Verified => 'Diterima',
            self::Rejected => 'Ditolak',
            self::Superseded => 'Digantikan',
            self::Expired => 'Kedaluwarsa',
            self::NeedsRevision => 'Perlu perbaikan',
        };
    }

    /**
     * The applicant-facing label. Identical today, kept as a separate method so
     * an internal-only nuance never leaks into the portal copy.
     */
    public function applicantLabel(): string
    {
        return $this->label();
    }

    /**
     * A document in one of these states still counts as "uploaded" for the
     * completeness calculation: the applicant supplied it, the reviewer simply
     * has not accepted the latest version yet.
     */
    public function isSupplied(): bool
    {
        return in_array($this, [
            self::Pending,
            self::Verified,
            self::Rejected,
            self::NeedsRevision,
            self::Expired,
        ], true);
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Verified, self::Rejected, self::NeedsRevision], true);
    }

    /**
     * @return array<string, string>
     */
    public static function reviewerOptions(): array
    {
        return [
            self::Verified->value => 'Terima dokumen',
            self::NeedsRevision->value => 'Perlu perbaikan',
            self::Rejected->value => 'Tolak dokumen',
        ];
    }
}
