<?php

namespace App\Services;

use App\Enums\AdmissionAccessTokenPurpose;
use App\Enums\AdmissionApplicationStatus;
use App\Enums\AdmissionDocumentType;
use App\Enums\AdmissionDocumentVerificationStatus;
use App\Enums\MediaAssetKind;
use App\Enums\MediaProcessingStatus;
use App\Enums\MediaScanStatus;
use App\Enums\MediaVisibility;
use App\Jobs\ProcessAdmissionDocument;
use App\Models\AdmissionApplication;
use App\Models\AdmissionReview;
use App\Models\ApplicationDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmissionDocumentService
{
    public function __construct(
        private readonly PrivateDocumentService $documents,
        private readonly AdmissionAccessService $access,
        private readonly AuditLogService $audit,
    ) {}

    public function uploadForReviewer(
        User $actor,
        AdmissionApplication $application,
        UploadedFile $file,
        AdmissionDocumentType $type,
    ): ApplicationDocument {
        Gate::forUser($actor)->authorize('review', $application);

        if (! in_array($application->status, [
            AdmissionApplicationStatus::Submitted,
            AdmissionApplicationStatus::UnderReview,
            AdmissionApplicationStatus::RevisionRequested,
        ], true)) {
            throw ValidationException::withMessages([
                'document' => 'Dokumen hanya dapat ditambahkan selama pengajuan ditinjau.',
            ]);
        }

        return $this->store($actor, $application, $file, $type);
    }

    public function uploadForApplicant(
        AdmissionApplication $application,
        string $rawToken,
        UploadedFile $file,
        AdmissionDocumentType $type,
    ): ApplicationDocument {
        $tokenApplication = $this->access->resolve($rawToken, AdmissionAccessTokenPurpose::DocumentUpload);

        if ($tokenApplication === null || (int) $tokenApplication->getKey() !== (int) $application->getKey()) {
            throw ValidationException::withMessages(['document' => 'The document upload link is invalid or expired.']);
        }

        if (! $application->canRevise()) {
            throw ValidationException::withMessages(['document' => 'Document upload is only available when a revision is requested before the deadline.']);
        }

        return $this->store(null, $application, $file, $type);
    }

    /**
     * Upload a document on behalf of a signed-in applicant portal account.
     *
     * No access token is involved: authorisation is the policy check on the
     * owning application, which the caller performs before reaching this method.
     *
     * A new version is only accepted when the previous one left the applicant
     * with something to do: no file at all, a file nobody has reviewed yet, or a
     * file the reviewer explicitly sent back. Once a document has been accepted
     * or finally rejected it is frozen, so an applicant cannot swap a reviewed
     * file after the fact.
     */
    public function uploadForPortal(
        AdmissionApplication $application,
        UploadedFile $file,
        AdmissionDocumentType $type,
    ): ApplicationDocument {
        /*
         * Authorisation is repeated here, not only in the calling FormRequest.
         * The request has to run its check before the controller exists, which
         * makes it easy for a future caller to reach this method without one; the
         * policy call is the thing that actually guarantees a candidate can only
         * ever touch their own application.
         */
        $applicant = $application->applicantUser;

        if (! $applicant instanceof User) {
            throw ValidationException::withMessages([
                'document' => 'Pengajuan ini tidak tertaut ke akun pendaftar.',
            ]);
        }

        Gate::forUser($applicant)->authorize('viewOwn', $application);

        if (! $application->canAcceptDocument()) {
            throw ValidationException::withMessages([
                'document' => 'Unggah dokumen hanya tersedia saat pengajuan masih disusun atau menunggu perbaikan.',
            ]);
        }

        return $this->store($applicant, $application, $file, $type, enforceReplaceable: true);
    }

    public function verify(User $actor, ApplicationDocument $document): ApplicationDocument
    {
        return DB::transaction(function () use ($actor, $document): ApplicationDocument {
            $locked = ApplicationDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('verify', $locked);
            $this->assertDocumentIsPending($locked);
            $this->assertApplicationIsReviewable($locked);
            $this->assertMediaIsScanned($locked);

            $locked->forceFill([
                'verification_status' => AdmissionDocumentVerificationStatus::Verified,
                'reviewed_by_user_id' => $actor->getKey(),
                'reviewed_at' => now(),
                'reviewer_note' => null,
            ])->save();

            AdmissionReview::query()->create([
                'admission_application_id' => (int) $locked->admission_application_id,
                'application_document_id' => $locked->getKey(),
                'reviewer_user_id' => $actor->getKey(),
                'event_type' => 'document_verified',
                'decision' => null,
                'internal_note' => null,
                'reviewed_at' => now(),
            ]);

            $this->audit->recordOrFail($actor, 'admission_document.verified', 'application_document', $locked->getKey(), context: ['document_type' => $locked->document_type->value]);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function reject(User $actor, ApplicationDocument $document, ?string $reason = null): ApplicationDocument
    {
        return DB::transaction(function () use ($actor, $document, $reason): ApplicationDocument {
            $locked = ApplicationDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('reject', $locked);
            $this->assertDocumentIsPending($locked);
            $this->assertApplicationIsReviewable($locked);

            $locked->forceFill([
                'verification_status' => AdmissionDocumentVerificationStatus::Rejected,
                'reviewed_by_user_id' => $actor->getKey(),
                'reviewed_at' => now(),
                'reviewer_note' => $reason,
            ])->save();

            AdmissionReview::query()->create([
                'admission_application_id' => (int) $locked->admission_application_id,
                'application_document_id' => $locked->getKey(),
                'reviewer_user_id' => $actor->getKey(),
                'event_type' => 'document_rejected',
                'decision' => null,
                'internal_note' => $reason,
                'reviewed_at' => now(),
            ]);

            $this->audit->recordOrFail($actor, 'admission_document.rejected', 'application_document', $locked->getKey(), context: ['document_type' => $locked->document_type->value]);

            return $locked->refresh();
        }, attempts: 3);
    }

    /**
     * Send a document back for a replacement, with a reason the applicant reads.
     *
     * This is the state that makes the revision loop a one-document fix instead
     * of a whole-application restart: only this document is replaceable, and only
     * the category it belongs to.
     */
    public function requestRevision(
        User $actor,
        ApplicationDocument $document,
        ?string $note = null,
    ): ApplicationDocument {
        if ($note === null || trim($note) === '') {
            throw ValidationException::withMessages([
                'reviewer_note' => 'Alasan perbaikan wajib diisi agar pemohon tahu apa yang harus diunggah ulang.',
            ]);
        }

        return DB::transaction(function () use ($actor, $document, $note): ApplicationDocument {
            $locked = ApplicationDocument::query()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('requestRevision', $locked);
            $this->assertDocumentIsPending($locked);
            $this->assertApplicationIsReviewable($locked);

            $locked->forceFill([
                'verification_status' => AdmissionDocumentVerificationStatus::NeedsRevision,
                'reviewed_by_user_id' => $actor->getKey(),
                'reviewed_at' => now(),
                'reviewer_note' => trim($note),
            ])->save();

            AdmissionReview::query()->create([
                'admission_application_id' => (int) $locked->admission_application_id,
                'application_document_id' => $locked->getKey(),
                'reviewer_user_id' => $actor->getKey(),
                'event_type' => 'document_revision_requested',
                'decision' => null,
                'internal_note' => trim($note),
                'reviewed_at' => now(),
            ]);

            $this->audit->recordOrFail($actor, 'admission_document.revision_requested', 'application_document', $locked->getKey(), context: ['document_type' => $locked->document_type->value]);

            return $locked->refresh();
        }, attempts: 3);
    }

    /**
     * Download a document the requesting account is entitled to.
     *
     * Shared by the reviewer route and the applicant portal route; which policy
     * ability gates it is the caller's decision, so a candidate can only ever
     * reach a document belonging to their own application.
     */
    public function download(User $actor, ApplicationDocument $document, string $ability = 'downloadPrivate'): StreamedResponse
    {
        Gate::forUser($actor)->authorize($ability, $document);
        $media = $document->mediaAsset;

        if ($media === null
            || $media->deleted_at !== null
            || $media->media_kind !== MediaAssetKind::Document
            || $media->processing_status !== MediaProcessingStatus::Ready
            || $media->scan_status !== MediaScanStatus::Clean
            || $media->visibility !== MediaVisibility::Private
            || $media->private_disk === null
            || $media->private_object_key === null) {
            throw ValidationException::withMessages(['document' => 'The private document is unavailable until processing and scanning succeed.']);
        }

        $disk = Storage::disk($media->private_disk);
        if (! $disk->exists($media->private_object_key)) {
            throw ValidationException::withMessages(['document' => 'The private document object is unavailable.']);
        }

        $this->audit->recordOrFail($actor, 'admission_document.downloaded', 'application_document', $document->getKey(), context: ['document_type' => $document->document_type->value]);

        return $disk->download(
            $media->private_object_key,
            $media->original_filename ?: 'dokumen-admission',
            ['Cache-Control' => 'private, no-store'],
        );
    }

    /**
     * Persist a new version of one document category.
     *
     * Authorisation and the "may a new version exist at all?" rule are decided by
     * the caller, because they differ per entry point: a reviewer may add a
     * document while an application is under review, a token holder may only add
     * one inside an open revision window, and a signed-in applicant may add or
     * replace while the application is a draft or awaiting a requested revision.
     * What is left here is identical in all three cases, so it lives in one place
     * and cannot drift between them.
     */
    private function store(?User $actor, AdmissionApplication $application, UploadedFile $file, AdmissionDocumentType $type, bool $enforceReplaceable = false): ApplicationDocument
    {
        $media = $this->documents->store($file, $actor);

        try {
            $document = DB::transaction(function () use ($actor, $application, $media, $type, $enforceReplaceable): ApplicationDocument {
                $lockedApplication = AdmissionApplication::query()
                    ->whereKey($application->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * "May a new version exist at all?" is decided on the locked
                 * application, not on the caller's earlier read. Deciding it
                 * outside meant two concurrent uploads of the same category both
                 * passed, and the second silently superseded the first — so the
                 * reviewer would end up looking at a file the applicant never
                 * meant to send.
                 */
                if ($enforceReplaceable) {
                    $previous = $lockedApplication->documents()
                        ->where('document_type', $type->value)
                        ->orderByDesc('version')
                        ->first();

                    $replaceable = $previous === null
                        || $previous->verification_status === AdmissionDocumentVerificationStatus::Pending
                        || $previous->verification_status === AdmissionDocumentVerificationStatus::NeedsRevision;

                    if (! $replaceable) {
                        throw ValidationException::withMessages([
                            'document' => 'Dokumen ini sudah ditinjau. Hubungi administrator bila perlu diperbarui.',
                        ]);
                    }
                }

                $version = ((int) $lockedApplication->documents()
                    ->where('document_type', $type->value)
                    ->max('version')) + 1;
                $lockedApplication->documents()
                    ->where('document_type', $type->value)
                    ->where('verification_status', '!=', AdmissionDocumentVerificationStatus::Superseded->value)
                    ->update(['verification_status' => AdmissionDocumentVerificationStatus::Superseded->value]);

                $document = $lockedApplication->documents()->create([
                    'media_asset_id' => $media->getKey(),
                    'document_type' => $type,
                    'version' => $version,
                    'verification_status' => AdmissionDocumentVerificationStatus::Pending,
                ]);
                $this->audit->recordOrFail($actor, 'admission_document.uploaded', 'application_document', $document->getKey(), context: ['document_type' => $type->value, 'version' => $version]);

                return $document;
            }, attempts: 3);
        } catch (\Throwable $exception) {
            $this->documents->markFailed($media, 'reference_failed');

            throw $exception;
        }

        ProcessAdmissionDocument::dispatch($media->getKey());

        return $document->load('mediaAsset');
    }

    private function assertDocumentIsPending(ApplicationDocument $document): void
    {
        if ($document->verification_status !== AdmissionDocumentVerificationStatus::Pending) {
            throw ValidationException::withMessages(['document' => 'Only a pending document can be reviewed.']);
        }
    }

    private function assertApplicationIsReviewable(ApplicationDocument $document): void
    {
        $status = $document->application?->status;

        if (! in_array($status, [
            AdmissionApplicationStatus::Submitted,
            AdmissionApplicationStatus::UnderReview,
            AdmissionApplicationStatus::RevisionRequested,
        ], true)) {
            throw ValidationException::withMessages(['document' => 'Documents can only be reviewed while an application is under review.']);
        }
    }

    private function assertMediaIsScanned(ApplicationDocument $document): void
    {
        $media = $document->mediaAsset;

        if ($media === null || $media->processing_status->value !== 'ready' || $media->scan_status->value !== 'clean') {
            throw ValidationException::withMessages(['document' => 'The document is still quarantined or failed scanning.']);
        }
    }
}
