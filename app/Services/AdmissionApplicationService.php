<?php

namespace App\Services;

use App\Enums\AdmissionAccessTokenPurpose;
use App\Enums\AdmissionActorType;
use App\Enums\AdmissionApplicationStatus;
use App\Enums\AdmissionConversionStatus;
use App\Enums\AdmissionReviewDecision;
use App\Enums\GuardianRelationshipType;
use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPeriod;
use App\Models\AdmissionReview;
use App\Models\ApplicationStatusHistory;
use App\Models\ClassModel;
use App\Models\User;
use App\Notifications\AdmissionStatusChangedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdmissionApplicationService
{
    public function __construct(
        private readonly AdmissionAccessService $access,
        private readonly AuditLogService $audit,
        private readonly NotificationPreferenceService $notificationPreferences,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{application: AdmissionApplication, token: string}
     */
    public function submit(AdmissionPeriod $period, array $data, ?Request $request = null): array
    {
        $result = DB::transaction(function () use ($period, $data, $request): array {
            $lockedPeriod = AdmissionPeriod::query()->whereKey($period->getKey())->lockForUpdate()->firstOrFail();

            if (! $lockedPeriod->isCurrentlyOpen()) {
                throw ValidationException::withMessages(['admission_period_id' => 'The admission period is not open.']);
            }

            $applicant = $this->normalizeApplicant($data);
            $guardians = $this->validateGuardians($data['guardians'] ?? []);
            $fingerprint = $this->duplicateFingerprint($applicant);
            $duplicate = AdmissionApplication::query()
                ->where('admission_period_id', $lockedPeriod->getKey())
                ->where('duplicate_fingerprint', $fingerprint)
                ->whereNotIn('status', [
                    AdmissionApplicationStatus::Rejected->value,
                    AdmissionApplicationStatus::Withdrawn->value,
                    AdmissionApplicationStatus::Expired->value,
                ])
                ->lockForUpdate()
                ->exists();
            $registrationNumber = $this->uniqueRegistrationNumber();
            $application = new AdmissionApplication([
                'applicant_name' => $applicant['applicant_name'],
                'preferred_name' => $applicant['preferred_name'],
                'birth_date' => $applicant['birth_date'],
                'birth_place' => $applicant['birth_place'],
                'gender' => $applicant['gender'],
                'contact_email' => $applicant['contact_email'],
                'contact_phone' => $applicant['contact_phone'],
                'address' => $applicant['address'],
                'current_school' => $applicant['current_school'],
                'requested_grade_level' => $applicant['requested_grade_level'],
                'source' => 'public_web',
                'consent_version' => (string) config('sekolah-kita.ppdb.consent_version'),
                'consent_accepted_at' => now(),
                'submitted_at' => now(),
            ]);
            $application->forceFill([
                'admission_period_id' => $lockedPeriod->getKey(),
                'registration_number' => $registrationNumber,
                'status' => AdmissionApplicationStatus::Submitted,
                'conversion_status' => AdmissionConversionStatus::NotStarted,
                'workflow_version' => 1,
                'duplicate_fingerprint' => $fingerprint,
                'duplicate_flag' => $duplicate,
            ]);
            $application->save();

            foreach ($guardians as $guardian) {
                $application->guardians()->create($guardian);
            }

            ApplicationStatusHistory::query()->create([
                'admission_application_id' => $application->getKey(),
                'from_status' => null,
                'to_status' => AdmissionApplicationStatus::Submitted,
                'actor_type' => AdmissionActorType::Applicant,
                'actor_user_id' => null,
                'reason_code' => 'application_submitted',
                'public_message' => 'Pengajuan Anda telah diterima dan menunggu peninjauan.',
                'correlation_id' => $this->audit->requestContext($request)['correlation_id'],
                'workflow_version' => 1,
                'occurred_at' => now(),
            ]);

            $token = $this->access->issue($application, AdmissionAccessTokenPurpose::Status);
            $this->audit->recordOrFail(
                actor: null,
                action: 'admission_application.submitted',
                targetType: 'admission_application',
                targetId: $application->getKey(),
                context: [
                    'status' => AdmissionApplicationStatus::Submitted->value,
                    'period_id' => $lockedPeriod->getKey(),
                    'duplicate_review' => $duplicate,
                ],
                request: $request,
            );

            return [
                'application' => $application,
                'token' => $token,
            ];
        }, attempts: 3);

        $this->notificationPreferences->sendToAdministrators(
            new AdmissionStatusChangedNotification($result['application']->getKey(), AdmissionApplicationStatus::Submitted->value),
            NotificationCategory::Admission,
        );

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicStatus(AdmissionApplication $application): array
    {
        $latestMessage = $application->statusHistories()
            ->orderByDesc('workflow_version')
            ->value('public_message');

        return [
            'reference' => $application->registration_number,
            'status' => $application->status->value,
            'status_label' => $application->status->label(),
            'revision_deadline' => $application->revision_deadline?->toIso8601String(),
            'public_message' => $latestMessage,
            'can_revise' => $application->canRevise(),
        ];
    }

    public function assignReviewer(User $actor, AdmissionApplication $application, User $reviewer): AdmissionApplication
    {
        Gate::forUser($actor)->authorize('assignReviewer', $application);

        if (! $reviewer->isActive() || ! $reviewer->hasVerifiedEmail() || $reviewer->role !== UserRole::Administrator) {
            throw ValidationException::withMessages(['reviewer_user_id' => 'The reviewer must be an active verified administrator.']);
        }

        return DB::transaction(function () use ($actor, $application, $reviewer): AdmissionApplication {
            $locked = $this->lockApplication($application);
            $locked->forceFill(['assigned_reviewer_user_id' => $reviewer->getKey()])->save();
            AdmissionReview::query()->create([
                'admission_application_id' => $locked->getKey(),
                'reviewer_user_id' => $reviewer->getKey(),
                'event_type' => 'assignment',
                'decision' => null,
                'internal_note' => null,
                'reviewed_at' => now(),
            ]);
            $this->audit->recordOrFail($actor, 'admission_application.reviewer_assigned', 'admission_application', $locked->getKey(), context: ['workflow_version' => $locked->workflow_version]);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function startReview(User $actor, AdmissionApplication $application): AdmissionApplication
    {
        return $this->transition($actor, $application, AdmissionApplicationStatus::UnderReview, 'review_started', 'Pengajuan Anda sedang ditinjau.');
    }

    public function requestRevision(
        User $actor,
        AdmissionApplication $application,
        string $publicMessage,
        ?string $deadline = null,
    ): AdmissionApplication {
        return DB::transaction(function () use ($actor, $application, $publicMessage, $deadline): AdmissionApplication {
            $locked = $this->lockApplication($application);
            Gate::forUser($actor)->authorize('requestRevision', $locked);
            $this->assertExpectedAssignedReviewer($actor, $locked);
            $this->transitionLocked($locked, AdmissionApplicationStatus::RevisionRequested, $actor, 'revision_requested', $publicMessage);
            $locked->forceFill([
                'revision_requested_at' => now(),
                'revision_deadline' => $deadline,
            ])->save();
            $this->access->revokeForApplication($locked, AdmissionAccessTokenPurpose::Revision);
            $this->access->revokeForApplication($locked, AdmissionAccessTokenPurpose::DocumentUpload);
            $this->access->revokeForApplication($locked, AdmissionAccessTokenPurpose::Withdrawal);
            AdmissionReview::query()->create([
                'admission_application_id' => $locked->getKey(),
                'reviewer_user_id' => $actor->getKey(),
                'event_type' => 'revision_requested',
                'decision' => null,
                'internal_note' => null,
                'reviewed_at' => now(),
            ]);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function decide(
        User $actor,
        AdmissionApplication $application,
        AdmissionReviewDecision $decision,
        ?ClassModel $targetClass,
        ?string $internalNote = null,
    ): AdmissionApplication {
        return DB::transaction(function () use ($actor, $application, $decision, $targetClass, $internalNote): AdmissionApplication {
            $locked = $this->lockApplication($application);
            Gate::forUser($actor)->authorize('decide', $locked);
            $this->assertExpectedAssignedReviewer($actor, $locked);

            $nextStatus = match ($decision) {
                AdmissionReviewDecision::Accept => AdmissionApplicationStatus::Accepted,
                AdmissionReviewDecision::Reject => AdmissionApplicationStatus::Rejected,
                AdmissionReviewDecision::Waitlist => AdmissionApplicationStatus::Waitlisted,
            };

            if ($decision === AdmissionReviewDecision::Accept) {
                if ($targetClass === null) {
                    throw ValidationException::withMessages(['target_class_id' => 'A target class is required for acceptance.']);
                }

                if ((int) $targetClass->academic_year_id !== (int) $locked->period->academic_year_id) {
                    throw ValidationException::withMessages(['target_class_id' => 'The target class must belong to the admission period academic year.']);
                }
            }

            $this->transitionLocked($locked, $nextStatus, $actor, 'decision_'.$decision->value, 'Status pengajuan Anda telah diperbarui.');
            $locked->forceFill([
                'target_class_id' => $targetClass?->getKey(),
                'decided_at' => now(),
                'accepted_at' => $decision === AdmissionReviewDecision::Accept ? now() : null,
            ])->save();
            AdmissionReview::query()->create([
                'admission_application_id' => $locked->getKey(),
                'reviewer_user_id' => $actor->getKey(),
                'event_type' => 'decision',
                'decision' => $decision,
                'internal_note' => $internalNote,
                'reviewed_at' => now(),
            ]);
            if (in_array($nextStatus, [AdmissionApplicationStatus::Accepted, AdmissionApplicationStatus::Rejected], true)) {
                $this->access->revokeForApplication($locked, AdmissionAccessTokenPurpose::Revision);
                $this->access->revokeForApplication($locked, AdmissionAccessTokenPurpose::DocumentUpload);
                $this->access->revokeForApplication($locked, AdmissionAccessTokenPurpose::Withdrawal);
            }

            return $locked->refresh();
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resubmit(AdmissionApplication $application, array $data): AdmissionApplication
    {
        return DB::transaction(function () use ($application, $data): AdmissionApplication {
            $locked = $this->lockApplication($application);
            if (! $locked->canRevise()) {
                throw ValidationException::withMessages(['status' => 'This application is not open for revision.']);
            }

            $applicant = $this->normalizeApplicant($this->correctionApplicant($locked, $data));
            $contactChanged = $locked->contact_email !== $applicant['contact_email']
                || $locked->contact_phone !== $applicant['contact_phone'];
            $locked->fill(Arr::only($applicant, [
                'applicant_name',
                'preferred_name',
                'birth_date',
                'birth_place',
                'gender',
                'contact_email',
                'contact_phone',
                'address',
                'current_school',
                'requested_grade_level',
            ]));
            $locked->forceFill([
                'consent_version' => (string) config('sekolah-kita.ppdb.consent_version'),
                'consent_accepted_at' => now(),
                'submitted_at' => now(),
                'revision_requested_at' => null,
                'revision_deadline' => null,
                'contact_verified_at' => $contactChanged ? null : $locked->contact_verified_at,
            ]);
            $this->refreshDuplicateState($locked);
            $locked->save();
            $this->transitionLocked($locked, AdmissionApplicationStatus::Submitted, null, 'application_resubmitted', 'Perubahan telah dikirim dan akan ditinjau kembali.');
            $this->access->revokeForApplication($locked, AdmissionAccessTokenPurpose::Revision);
            $this->access->revokeForApplication($locked, AdmissionAccessTokenPurpose::DocumentUpload);
            $this->access->revokeForApplication($locked, AdmissionAccessTokenPurpose::Withdrawal);
            $this->audit->recordOrFail(null, 'admission_application.resubmitted', 'admission_application', $locked->getKey(), context: ['workflow_version' => $locked->workflow_version]);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function withdraw(AdmissionApplication $application, ?string $reasonCode = null): AdmissionApplication
    {
        return DB::transaction(function () use ($application, $reasonCode): AdmissionApplication {
            $locked = $this->lockApplication($application);
            if ($locked->status->isTerminal()) {
                throw ValidationException::withMessages(['status' => 'A terminal application cannot be withdrawn.']);
            }

            $this->transitionLocked($locked, AdmissionApplicationStatus::Withdrawn, null, $reasonCode ?? 'applicant_withdrawn', 'Pengajuan telah dihentikan.');
            $this->access->revokeForApplication($locked, AdmissionAccessTokenPurpose::Revision);
            $this->access->revokeForApplication($locked, AdmissionAccessTokenPurpose::DocumentUpload);
            $this->access->revokeForApplication($locked, AdmissionAccessTokenPurpose::Withdrawal);
            $this->audit->recordOrFail(null, 'admission_application.withdrawn', 'admission_application', $locked->getKey(), context: ['workflow_version' => $locked->workflow_version]);

            return $locked->refresh();
        }, attempts: 3);
    }

    public function transition(
        User $actor,
        AdmissionApplication $application,
        AdmissionApplicationStatus $nextStatus,
        string $reasonCode,
        ?string $publicMessage = null,
    ): AdmissionApplication {
        return DB::transaction(function () use ($actor, $application, $nextStatus, $reasonCode, $publicMessage): AdmissionApplication {
            $locked = $this->lockApplication($application);
            Gate::forUser($actor)->authorize('review', $locked);
            $this->assertExpectedAssignedReviewer($actor, $locked);
            $this->transitionLocked($locked, $nextStatus, $actor, $reasonCode, $publicMessage);

            return $locked->refresh();
        }, attempts: 3);
    }

    private function transitionLocked(
        AdmissionApplication $application,
        AdmissionApplicationStatus $nextStatus,
        ?User $actor,
        string $reasonCode,
        ?string $publicMessage,
    ): void {
        $currentStatus = $application->status;
        if (! $currentStatus->canTransitionTo($nextStatus)) {
            throw ValidationException::withMessages(['status' => 'This application status transition is not allowed.']);
        }

        $nextVersion = ((int) $application->workflow_version) + 1;
        $application->forceFill([
            'status' => $nextStatus,
            'workflow_version' => $nextVersion,
        ])->save();
        ApplicationStatusHistory::query()->create([
            'admission_application_id' => $application->getKey(),
            'from_status' => $currentStatus,
            'to_status' => $nextStatus,
            'actor_type' => $actor === null ? AdmissionActorType::Applicant : AdmissionActorType::Staff,
            'actor_user_id' => $actor?->getKey(),
            'reason_code' => $reasonCode,
            'public_message' => $publicMessage,
            'correlation_id' => (string) Str::uuid(),
            'workflow_version' => $nextVersion,
            'occurred_at' => now(),
        ]);
        $this->audit->recordOrFail(
            $actor,
            'admission_application.status_changed',
            'admission_application',
            $application->getKey(),
            context: [
                'from_status' => $currentStatus->value,
                'to_status' => $nextStatus->value,
                'workflow_version' => $nextVersion,
            ],
        );
    }

    private function lockApplication(AdmissionApplication $application): AdmissionApplication
    {
        return AdmissionApplication::query()->whereKey($application->getKey())->lockForUpdate()->firstOrFail();
    }

    private function assertExpectedAssignedReviewer(User $actor, AdmissionApplication $application): void
    {
        if ((int) $application->assigned_reviewer_user_id !== (int) $actor->getKey()) {
            throw ValidationException::withMessages(['reviewer' => 'Only the assigned reviewer can perform this action.']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function correctionApplicant(AdmissionApplication $application, array $data): array
    {
        $fields = [
            'applicant_name',
            'preferred_name',
            'birth_date',
            'birth_place',
            'gender',
            'contact_email',
            'contact_phone',
            'address',
            'current_school',
            'requested_grade_level',
        ];
        $applicant = [];

        foreach ($fields as $field) {
            $applicant[$field] = array_key_exists($field, $data)
                ? $data[$field]
                : $application->{$field};
        }

        return $applicant;
    }

    private function refreshDuplicateState(AdmissionApplication $application): void
    {
        $fingerprint = $this->duplicateFingerprint([
            'applicant_name' => $application->applicant_name,
            'birth_date' => $application->birth_date,
            'contact_email' => $application->contact_email,
        ]);
        $duplicate = AdmissionApplication::query()
            ->where('admission_period_id', $application->admission_period_id)
            ->where('duplicate_fingerprint', $fingerprint)
            ->whereKeyNot($application->getKey())
            ->whereNotIn('status', [
                AdmissionApplicationStatus::Rejected->value,
                AdmissionApplicationStatus::Withdrawn->value,
                AdmissionApplicationStatus::Expired->value,
            ])
            ->lockForUpdate()
            ->exists();

        $application->forceFill([
            'duplicate_fingerprint' => $fingerprint,
            'duplicate_flag' => $duplicate,
            'duplicate_reviewed_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeApplicant(array $data): array
    {
        return [
            'applicant_name' => trim((string) ($data['applicant_name'] ?? $data['name'] ?? '')),
            'preferred_name' => trim((string) ($data['preferred_name'] ?? '')) ?: null,
            'birth_date' => $data['birth_date'] ?? null,
            'birth_place' => trim((string) ($data['birth_place'] ?? '')) ?: null,
            'gender' => $data['gender'] ?? null,
            'contact_email' => Str::lower(trim((string) ($data['contact_email'] ?? $data['email'] ?? ''))),
            'contact_phone' => trim((string) ($data['contact_phone'] ?? $data['phone'] ?? '')),
            'address' => trim((string) ($data['address'] ?? '')) ?: null,
            'current_school' => trim((string) ($data['current_school'] ?? '')) ?: null,
            'requested_grade_level' => $data['requested_grade_level'] ?? null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function validateGuardians(mixed $guardians): array
    {
        if (! is_array($guardians) || $guardians === []) {
            throw ValidationException::withMessages(['guardians' => 'At least one guardian is required.']);
        }

        $normalized = [];
        $primaryCount = 0;

        foreach ($guardians as $guardian) {
            if (! is_array($guardian)) {
                throw ValidationException::withMessages(['guardians' => 'Guardian data is invalid.']);
            }

            $relationship = GuardianRelationshipType::tryFrom((string) ($guardian['relationship_type'] ?? ''));
            if ($relationship === null) {
                throw ValidationException::withMessages(['guardians.relationship_type' => 'The guardian relationship is invalid.']);
            }

            $isPrimary = filter_var($guardian['is_primary_contact'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $primaryCount += $isPrimary ? 1 : 0;
            $normalized[] = [
                'legal_name' => trim((string) ($guardian['legal_name'] ?? '')),
                'relationship_type' => $relationship,
                'phone' => trim((string) ($guardian['phone'] ?? '')),
                'email' => Str::lower(trim((string) ($guardian['email'] ?? ''))) ?: null,
                'address' => trim((string) ($guardian['address'] ?? '')) ?: null,
                'is_primary_contact' => $isPrimary,
                'is_emergency_contact' => filter_var($guardian['is_emergency_contact'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'priority_order' => $guardian['priority_order'] ?? null,
            ];
        }

        if ($primaryCount !== 1) {
            throw ValidationException::withMessages(['guardians.is_primary_contact' => 'Exactly one primary guardian is required.']);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $applicant
     */
    private function duplicateFingerprint(array $applicant): string
    {
        return hash_hmac('sha256', implode('|', [
            $applicant['applicant_name'],
            $applicant['birth_date'] ?? '',
            $applicant['contact_email'],
        ]), (string) config('app.key'));
    }

    private function uniqueRegistrationNumber(): string
    {
        do {
            $number = 'PPDB-'.now()->format('Y').'-'.Str::upper(Str::random(10));
        } while (AdmissionApplication::query()->where('registration_number', $number)->exists());

        return $number;
    }
}
