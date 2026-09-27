<?php

namespace App\Enums;

/**
 * What happened to a review, as a closed set.
 *
 * The column was previously a free-form string, which meant the reviewer screen
 * had to guess a label for it and a typo would render as raw English on a page
 * an administrator reads to decide a child's enrolment. Making it an enum also
 * makes the set of things a reviewer can do enumerable rather than something a
 * test has to rediscover from the service code.
 *
 * The application-level events (assignment, decision, revision_requested) and the
 * document-level events (document_*) share one vocabulary because they are
 * rendered in the same review history; which rows carry which event is decided by
 * whether application_document_id is set, not by the enum.
 */
enum AdmissionReviewEventType: string
{
    case Assignment = 'assignment';
    case Decision = 'decision';
    case RevisionRequested = 'revision_requested';
    case DocumentUploaded = 'document_uploaded';
    case DocumentVerified = 'document_verified';
    case DocumentRejected = 'document_rejected';
    case DocumentRevisionRequested = 'document_revision_requested';
    case DocumentSuperseded = 'document_superseded';

    public function label(): string
    {
        return match ($this) {
            self::Assignment => 'Reviewer ditugaskan',
            self::Decision => 'Keputusan dicatat',
            self::RevisionRequested => 'Revisi pengajuan diminta',
            self::DocumentUploaded => 'Dokumen diunggah',
            self::DocumentVerified => 'Dokumen diterima',
            self::DocumentRejected => 'Dokumen ditolak',
            self::DocumentRevisionRequested => 'Dokumen dikembalikan untuk diperbaiki',
            self::DocumentSuperseded => 'Dokumen digantikan versi baru',
        };
    }

    /**
     * Whether this event concerns a single document rather than the application.
     */
    public function isDocumentLevel(): bool
    {
        return str_starts_with($this->value, 'document_');
    }

    /**
     * Resolve a stored value, falling back to the raw string for rows written
     * before the vocabulary was closed — a review history is a record, and losing
     * an older line because its label is unknown would be worse than showing it
     * verbatim.
     */
    public static function describe(?string $value): string
    {
        if ($value === null || $value === '') {
            return 'Peristiwa review';
        }

        return self::tryFrom($value)?->label() ?? $value;
    }
}
