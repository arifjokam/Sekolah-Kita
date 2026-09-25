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
            $this->audit->recordOrFail($actor, 'admission_document.rejected', 'application_document', $locked->getKey(), context: ['document_type' => $locked->document_type->value]);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function download(User $actor, ApplicationDocument $document): StreamedResponse
    {
        Gate::forUser($actor)->authorize('downloadPrivate', $document);
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

    private function store(?User $actor, AdmissionApplication $application, UploadedFile $file, AdmissionDocumentType $type): ApplicationDocument
    {
        $media = $this->documents->store($file, $actor);

        try {
            $document = DB::transaction(function () use ($actor, $application, $media, $type): ApplicationDocument {
                $lockedApplication = AdmissionApplication::query()
                    ->whereKey($application->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($actor !== null) {
                    Gate::forUser($actor)->authorize('review', $lockedApplication);
                    if (! in_array($lockedApplication->status, [
                        AdmissionApplicationStatus::Submitted,
                        AdmissionApplicationStatus::UnderReview,
                        AdmissionApplicationStatus::RevisionRequested,
                    ], true)) {
                        throw ValidationException::withMessages(['document' => 'Documents can only be added while an application is under review.']);
                    }
                } elseif (! $lockedApplication->canRevise()) {
                    throw ValidationException::withMessages(['document' => 'Document upload is only available when a revision is requested before the deadline.']);
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
