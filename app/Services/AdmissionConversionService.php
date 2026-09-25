<?php

namespace App\Services;

use App\Enums\AdmissionActorType;
use App\Enums\AdmissionApplicationStatus;
use App\Enums\AdmissionConversionStatus;
use App\Enums\AdmissionDocumentVerificationStatus;
use App\Enums\ClassStatus;
use App\Enums\MediaAssetKind;
use App\Enums\MediaProcessingStatus;
use App\Enums\MediaScanStatus;
use App\Enums\MediaVisibility;
use App\Enums\NotificationCategory;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPeriod;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AdmissionApplicantStatusEmailNotification;
use App\Notifications\AdmissionStatusChangedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdmissionConversionService
{
    public function __construct(
        private readonly StudentProvisioningService $students,
        private readonly EnrollmentService $enrollments,
        private readonly AuditLogService $audit,
        private readonly NotificationPreferenceService $notificationPreferences,
    ) {}

    /**
     * @return array{student: Student, enrollment: Enrollment}
     */
    public function convert(User $actor, AdmissionApplication $application): array
    {
        try {
            Gate::forUser($actor)->authorize('convert', $application);
        } catch (AuthorizationException $exception) {
            $this->audit->record(
                $actor,
                'admission_application.conversion_denied',
                'admission_application',
                $application->getKey(),
                result: 'denied',
            );

            throw $exception;
        }

        $shouldNotify = false;

        try {
            $result = DB::transaction(function () use ($actor, $application, &$shouldNotify): array {
                $lockedApplication = AdmissionApplication::query()
                    ->with('period')
                    ->whereKey($application->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                Gate::forUser($actor)->authorize('convert', $lockedApplication);

                if ($lockedApplication->conversion_status === AdmissionConversionStatus::Completed
                    && $lockedApplication->converted_student_id !== null
                    && $lockedApplication->converted_enrollment_id !== null) {
                    return [
                        'student' => $lockedApplication->convertedStudent,
                        'enrollment' => $lockedApplication->convertedEnrollment,
                    ];
                }

                $shouldNotify = true;

                if ($lockedApplication->status !== AdmissionApplicationStatus::Accepted) {
                    throw ValidationException::withMessages(['status' => 'Only an accepted application can be converted.']);
                }

                if ($lockedApplication->target_class_id === null) {
                    throw ValidationException::withMessages(['target_class_id' => 'A target class is required before conversion.']);
                }

                $verifiedDocuments = $lockedApplication->documents()
                    ->where('verification_status', AdmissionDocumentVerificationStatus::Verified->value)
                    ->where(function (Builder $query): void {
                        $query->whereNull('valid_from')->orWhere('valid_from', '<=', today()->toDateString());
                    })
                    ->where(function (Builder $query): void {
                        $query->whereNull('valid_until')->orWhere('valid_until', '>=', today()->toDateString());
                    })
                    ->whereHas('mediaAsset', function (Builder $query): void {
                        $query
                            ->where('media_kind', MediaAssetKind::Document->value)
                            ->where('processing_status', MediaProcessingStatus::Ready->value)
                            ->where('scan_status', MediaScanStatus::Clean->value)
                            ->where('visibility', MediaVisibility::Private->value)
                            ->whereNotNull('private_object_key')
                            ->whereNull('deleted_at');
                    })
                    ->count();
                if ($verifiedDocuments < 1) {
                    throw ValidationException::withMessages(['documents' => 'At least one verified admission document is required before conversion.']);
                }

                $period = AdmissionPeriod::query()->whereKey($lockedApplication->admission_period_id)->lockForUpdate()->firstOrFail();
                $targetClass = ClassModel::query()->whereKey($lockedApplication->target_class_id)->lockForUpdate()->firstOrFail();

                if ((int) $targetClass->academic_year_id !== (int) $period->academic_year_id) {
                    throw ValidationException::withMessages(['target_class_id' => 'The target class is outside the admission period academic year.']);
                }
                if ($targetClass->status === ClassStatus::Closed) {
                    throw ValidationException::withMessages(['target_class_id' => 'The target class is closed.']);
                }

                if ($period->quota !== null) {
                    $convertedCount = AdmissionApplication::query()
                        ->where('admission_period_id', $period->getKey())
                        ->where('conversion_status', AdmissionConversionStatus::Completed->value)
                        ->lockForUpdate()
                        ->count();
                    if ($convertedCount >= $period->quota) {
                        throw ValidationException::withMessages(['quota' => 'The admission period quota has been reached.']);
                    }
                }

                $student = $this->students->createStudent($lockedApplication);
                $this->students->linkGuardians($lockedApplication, $student);
                $enrollment = $this->enrollments->create(
                    actor: $actor,
                    student: $student,
                    class: $targetClass,
                    enrolledOn: today()->toDateString(),
                    effectiveFrom: today()->toDateString(),
                    transitionReason: 'PPDB conversion',
                );

                $nextVersion = ((int) $lockedApplication->workflow_version) + 1;
                $lockedApplication->forceFill([
                    'converted_student_id' => $student->getKey(),
                    'converted_enrollment_id' => $enrollment->getKey(),
                    'conversion_status' => AdmissionConversionStatus::Completed,
                    'converted_at' => now(),
                    'conversion_error_code' => null,
                    'workflow_version' => $nextVersion,
                ])->save();
                $lockedApplication->statusHistories()->create([
                    'from_status' => AdmissionApplicationStatus::Accepted,
                    'to_status' => AdmissionApplicationStatus::Accepted,
                    'actor_type' => AdmissionActorType::Staff,
                    'actor_user_id' => $actor->getKey(),
                    'reason_code' => 'conversion_completed',
                    'public_message' => 'Pengajuan Anda telah diproses menjadi data siswa.',
                    'correlation_id' => (string) Str::uuid(),
                    'workflow_version' => $nextVersion,
                    'occurred_at' => now(),
                ]);
                $this->audit->recordOrFail($actor, 'admission_application.converted', 'admission_application', $lockedApplication->getKey(), context: ['workflow_version' => $nextVersion, 'conversion_status' => AdmissionConversionStatus::Completed->value]);

                return [
                    'student' => $student,
                    'enrollment' => $enrollment,
                ];
            }, attempts: 3);
        } catch (AuthorizationException|ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->markFailed($actor, $application, $exception);

            throw $exception;
        }

        if ($shouldNotify) {
            $this->notificationPreferences->sendToAdministrators(
                new AdmissionStatusChangedNotification($application->getKey(), AdmissionApplicationStatus::Accepted->value),
                NotificationCategory::Admission,
            );
            if (config('sekolah-kita.notifications.email_enabled', true) && filled($application->contact_email)) {
                Notification::route('mail', $application->contact_email)->notify(new AdmissionApplicantStatusEmailNotification($application->getKey()));
            }
        }

        return $result;
    }

    private function markFailed(User $actor, AdmissionApplication $application, Throwable $exception): void
    {
        try {
            DB::transaction(function () use ($actor, $application, $exception): void {
                $locked = AdmissionApplication::query()
                    ->whereKey($application->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($locked === null || $locked->conversion_status === AdmissionConversionStatus::Completed) {
                    return;
                }

                $locked->forceFill([
                    'conversion_status' => AdmissionConversionStatus::Failed,
                    'conversion_error_code' => Str::limit($exception::class, 80, ''),
                ])->save();
                $this->audit->record(
                    $actor,
                    'admission_application.conversion_failed',
                    'admission_application',
                    $locked->getKey(),
                    result: 'failed',
                    context: ['conversion_status' => AdmissionConversionStatus::Failed->value],
                );
            }, attempts: 3);
        } catch (Throwable $markFailure) {
            report($markFailure);
        }
    }
}
