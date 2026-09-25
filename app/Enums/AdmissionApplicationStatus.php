<?php

namespace App\Enums;

enum AdmissionApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case RevisionRequested = 'revision_requested';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Waitlisted = 'waitlisted';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Dikyatmakan',
            self::UnderReview => 'Ditinjau',
            self::RevisionRequested => 'Perlu revisi',
            self::Accepted => 'Diterima',
            self::Rejected => 'Ditolak',
            self::Waitlisted => 'Daftar tunggu',
            self::Withdrawn => 'Dihentikan',
            self::Expired => 'Kedaluwarsa',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Draft => [self::Submitted, self::Withdrawn, self::Expired],
            self::Submitted => [self::UnderReview, self::RevisionRequested, self::Withdrawn, self::Expired],
            self::UnderReview => [self::RevisionRequested, self::Accepted, self::Rejected, self::Waitlisted],
            self::RevisionRequested => [self::Submitted, self::Withdrawn, self::Expired],
            self::Accepted => [],
            self::Rejected => [],
            self::Waitlisted => [self::UnderReview, self::Accepted, self::Rejected, self::Withdrawn, self::Expired],
            self::Withdrawn => [],
            self::Expired => [],
        }, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected, self::Withdrawn, self::Expired], true);
    }
}
