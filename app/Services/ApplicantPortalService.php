<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\AdmissionActorType;
use App\Enums\AdmissionApplicationStatus;
use App\Enums\AdmissionConversionStatus;
use App\Enums\AdmissionDocumentType;
use App\Enums\AdmissionDocumentVerificationStatus;
use App\Enums\GuardianRelationshipType;
use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPeriod;
use App\Models\ApplicationDocument;
use App\Models\ApplicationGuardian;
use App\Models\ApplicationStatusHistory;
use App\Models\User;
use App\Notifications\AdmissionStatusChangedNotification;
use App\Notifications\CandidatePortalNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The PPDB applicant portal.
 *
 * Three responsibilities, kept apart on purpose:
 *
 *  1. account creation for a candidate (role: calon) — a candidate is NOT a
 *     student, and nothing here creates a Student, an Enrollment or an internal
 *     account. Conversion remains an explicit administrator action that reuses
 *     AdmissionConversionService;
 *  2. the progressive draft: biodata, guardians, documents, each saved
 *     independently, with a completeness calculation that names what is missing;
 *  3. submission, which is refused unless every required section is complete.
 */
class ApplicantPortalService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly NotificationService $notifications,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Accounts
    |--------------------------------------------------------------------------
    */

    /**
     * Create a candidate account and their draft application.
     *
     * The account is inactive until the address is verified, exactly like every
     * other account in the platform, so a candidate cannot reach the portal with
     * an unverified address and every PPDB message has a deliverable destination.
     *
     * @param  array{name: string, email: string, phone: string, password: string, whatsapp?: string|null}  $data
     * @return array{user: User, application: AdmissionApplication}
     */
    public function register(array $data, ?Request $request = null): array
    {
        $period = $this->openPeriod();

        if ($period === null) {
            throw ValidationException::withMessages([
                'admission_period_id' => 'Pendaftaran PPDB belum sedang dibuka.',
            ]);
        }

        $email = Str::lower(trim($data['email']));
        $phone = $this->normalisePhone($data['phone']);
        $whatsapp = $this->normalisePhone($data['whatsapp'] ?? null) ?? $phone;

        $result = DB::transaction(function () use ($period, $data, $email, $phone, $whatsapp, $request): array {
            $user = new User([
                'name' => trim($data['name']),
                'email' => $email,
                // 'hashed' cast; never stored or logged in the clear.
                'password' => $data['password'],
            ]);

            /*
             * role and status are deliberately not mass assignable — the same
             * protection every other account relies on, so a request body can
             * never promote itself. Registration is the one place that
             * legitimately sets them, so it does so explicitly.
             */
            $user->forceFill([
                'role' => UserRole::Calon,
                'status' => AccountStatus::Inactive,
            ])->save();

            $application = $this->createDraft($user, $period, [
                'applicant_name' => trim($data['name']),
                'contact_email' => $email,
                'contact_phone' => $phone,
                'contact_whatsapp' => $whatsapp,
            ]);

            $this->audit->recordOrFail(
                actor: $user,
                action: 'ppdb_portal.registered',
                targetType: 'admission_application',
                targetId: $application->getKey(),
                context: [
                    'admission_period_id' => $period->getKey(),
                    'status' => AdmissionApplicationStatus::Draft->value,
                ],
                request: $request,
            );

            return ['user' => $user, 'application' => $application];
        }, attempts: 3);

        // Verification mail is dispatched after the transaction commits so a
        // rollback cannot leave a candidate holding a token for nothing.
        $result['user']->sendEmailVerificationNotification();

        return $result;
    }

    /**
     * The application a candidate account owns, creating a draft when the
     * account has none yet and a period is open.
     */
    public function currentApplication(User $user, bool $createIfMissing = false): ?AdmissionApplication
    {
        $application = AdmissionApplication::query()
            ->where('applicant_user_id', $user->getKey())
            ->orderByDesc('id')
            ->first();

        if ($application instanceof AdmissionApplication || ! $createIfMissing) {
            return $application;
        }

        $period = $this->openPeriod();

        if ($period === null) {
            return null;
        }

        return DB::transaction(fn (): AdmissionApplication => $this->createDraft($user, $period, [
            'applicant_name' => $user->name,
            'contact_email' => $user->email,
            'contact_phone' => null,
        ]), attempts: 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Draft sections
    |--------------------------------------------------------------------------
    */

    /**
     * Save the student biodata section.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveBiodata(User $actor, AdmissionApplication $application, array $data, ?Request $request = null): AdmissionApplication
    {
        Gate::forUser($actor)->authorize('updateOwn', $application);

        DB::transaction(function () use ($actor, $application, $data, $request): void {
            $application->fill([
                'applicant_name' => (string) $data['applicant_name'],
                'preferred_name' => $this->blankToNull($data['preferred_name'] ?? null),
                'national_id' => $this->blankToNull($data['national_id'] ?? null),
                'nisn' => $this->blankToNull($data['nisn'] ?? null),
                'birth_place' => (string) $data['birth_place'],
                'birth_date' => (string) $data['birth_date'],
                'gender' => (string) $data['gender'],
                'address' => (string) $data['address'],
                'address_rt' => $this->blankToNull($data['address_rt'] ?? null),
                'address_rw' => $this->blankToNull($data['address_rw'] ?? null),
                'village' => $this->blankToNull($data['village'] ?? null),
                'district' => $this->blankToNull($data['district'] ?? null),
                'regency' => $this->blankToNull($data['regency'] ?? null),
                'province' => $this->blankToNull($data['province'] ?? null),
                'postal_code' => $this->blankToNull($data['postal_code'] ?? null),
                'contact_phone' => $this->normalisePhone($data['contact_phone'] ?? null),
                'contact_whatsapp' => $this->normalisePhone($data['contact_whatsapp'] ?? null),
                'current_school' => $this->blankToNull($data['current_school'] ?? null),
                'graduation_year' => $this->blankToNull($data['graduation_year'] ?? null),
            ])->save();

            $this->audit->recordOrFail(
                $actor,
                'ppdb_portal.biodata_saved',
                'admission_application',
                $application->getKey(),
                request: $request,
            );
        }, attempts: 3);

        return $application->refresh();
    }

    /**
     * Add a guardian to the application.
     *
     * @param  array<string, mixed>  $data
     */
    public function addGuardian(User $actor, AdmissionApplication $application, array $data, ?Request $request = null): ApplicationGuardian
    {
        Gate::forUser($actor)->authorize('create', [ApplicationGuardian::class, $application]);

        return DB::transaction(function () use ($actor, $application, $data, $request): ApplicationGuardian {
            $guardian = $application->guardians()->create([
                'legal_name' => (string) $data['legal_name'],
                'national_id' => $this->blankToNull($data['national_id'] ?? null),
                'relationship_type' => GuardianRelationshipType::from((string) $data['relationship_type']),
                'phone' => $this->normalisePhone($data['phone'] ?? null),
                'whatsapp_number' => $this->normalisePhone($data['whatsapp_number'] ?? null),
                'email' => $this->blankToNull($data['email'] ?? null),
                'occupation' => $this->blankToNull($data['occupation'] ?? null),
                'address' => $this->blankToNull($data['address'] ?? null),
                'is_primary_contact' => (bool) ($data['is_primary_contact'] ?? false),
                'is_emergency_contact' => (bool) ($data['is_emergency_contact'] ?? false),
                'priority_order' => (int) ($data['priority_order'] ?? $application->guardians()->count() + 1),
            ]);

            // Exactly one primary contact is a domain rule, not a UI hint, so it
            // is re-asserted here whenever a new primary is added.
            if ($guardian->is_primary_contact) {
                $application->guardians()
                    ->whereKeyNot($guardian->getKey())
                    ->update(['is_primary_contact' => false]);
            }

            $this->audit->recordOrFail(
                $actor,
                'ppdb_portal.guardian_added',
                'admission_application',
                $application->getKey(),
                context: ['relationship_type' => $guardian->relationship_type->value],
                request: $request,
            );

            return $guardian;
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateGuardian(
        User $actor,
        ApplicationGuardian $guardian,
        array $data,
        ?Request $request = null,
    ): ApplicationGuardian {
        Gate::forUser($actor)->authorize('update', $guardian);

        return DB::transaction(function () use ($actor, $guardian, $data, $request): ApplicationGuardian {
            /*
             * Lock the parent application before touching the guardian rows.
             * The "exactly one primary contact" rule spans sibling rows, so without
             * the lock two concurrent edits could each demote the other and leave
             * the application with no primary contact at all.
             */
            $application = AdmissionApplication::query()
                ->whereKey($guardian->admission_application_id)
                ->lockForUpdate()
                ->first();

            if (! $application instanceof AdmissionApplication) {
                throw ValidationException::withMessages([
                    'legal_name' => 'Pengajuan induk tidak ditemukan.',
                ]);
            }

            $wantsPrimary = (bool) ($data['is_primary_contact'] ?? false);

            $guardian->fill([
                'legal_name' => (string) $data['legal_name'],
                'national_id' => $this->blankToNull($data['national_id'] ?? null),
                'relationship_type' => GuardianRelationshipType::from((string) $data['relationship_type']),
                'phone' => $this->normalisePhone($data['phone'] ?? null),
                'whatsapp_number' => $this->normalisePhone($data['whatsapp_number'] ?? null),
                'email' => $this->blankToNull($data['email'] ?? null),
                'occupation' => $this->blankToNull($data['occupation'] ?? null),
                'address' => $this->blankToNull($data['address'] ?? null),
                'is_emergency_contact' => (bool) ($data['is_emergency_contact'] ?? false),
            ])->save();

            /*
             * Demote the siblings only when this guardian is becoming the primary
             * contact. Doing it unconditionally meant that editing a *secondary*
             * guardian — say, to correct an occupation — silently cleared the
             * primary flag from every other guardian and then set it to false here
             * as well, leaving the application with no primary contact and
             * therefore unsubmittable, from an unrelated edit.
             */
            if ($wantsPrimary) {
                $application->guardians()
                    ->whereKeyNot($guardian->getKey())
                    ->where('is_primary_contact', true)
                    ->update(['is_primary_contact' => false]);
            }

            $guardian->forceFill(['is_primary_contact' => $wantsPrimary])->save();

            $this->audit->recordOrFail(
                $actor,
                'ppdb_portal.guardian_updated',
                'admission_application',
                (int) $guardian->admission_application_id,
                context: ['is_primary_contact' => $wantsPrimary],
                request: $request,
            );

            return $guardian;
        }, attempts: 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Completion and submission
    |--------------------------------------------------------------------------
    */

    /**
     * Everything the dashboard needs to render without guessing.
     *
     * @return array{
     *     application: AdmissionApplication,
     *     period: AdmissionPeriod|null,
     *     sections: array<string, array{complete: bool, ratio: int, missing: list<string>}>,
     *     percentage: int,
     *     is_complete: bool,
     *     missing: list<string>,
     *     documents: Collection<int, AdmissionDocument>,
     *     document_slots: list<array{type: AdmissionDocumentType, required: bool, document: AdmissionDocument|null}>,
     *     can_edit: bool,
     *     can_submit: bool,
     *     next_action: array{label: string, route: string},
     *     whatsapp: array{driver: string, configured: bool, reason: string|null, number: string|null},
     *     feedback: Collection<int, ApplicationStatusHistory>
     * }
     */
    public function progress(User $user, AdmissionApplication $application): array
    {
        $application->loadMissing([
            // published_at is part of this list on purpose: AdmissionPeriod::
            // isCurrentlyOpen() reads it, and a column list that omitted it would
            // leave a cached-but-incomplete relation that always reports "closed".
            'period:id,name,opens_at,closes_at,published_at,status,academic_year_id',
            'guardians',
            // mediaAsset is loaded because every portal document screen reads its
            // scan state; without it each row cost one extra query. The document
            // review trail is *not* loaded here — only the administrator's review
            // screen reads it, and it loads its own copy.
            'documents.mediaAsset:id,mime_type,processing_status,scan_status,original_filename',
        ]);

        $biodata = $this->biodataSection($application);
        $guardians = $this->guardianSection($application);
        $documents = $this->documentSection($application);

        $sections = [
            'biodata' => $biodata,
            'guardians' => $guardians,
            'documents' => $documents,
        ];

        $percentage = (int) round(array_sum(array_column($sections, 'ratio')) / max(1, count($sections)));
        $missing = array_merge(
            ...array_values(array_map(static fn (array $section): array => $section['missing'], $sections)),
        );

        $isComplete = $percentage === 100;
        $canEdit = $application->isPortalEditable();
        $canSubmit = $application->isDraft() && $isComplete;

        $documentSlots = [];
        foreach (AdmissionDocumentType::portalCategories() as $type) {
            $documentSlots[] = [
                'type' => $type,
                'required' => $type->isRequired(),
                'document' => $application->documents
                    ->where('document_type', $type)
                    ->sortByDesc('version')
                    ->first(),
            ];
        }

        // The recent-notice list is bounded to the newest few rows. It used to be
        // read on every portal page even though only the dashboard renders it, and
        // the status page re-read the same table without a limit, so a long-running
        // application loaded its whole history into memory to show five lines.
        $feedback = $application->statusHistories()
            ->orderByDesc('workflow_version')
            ->limit(5)
            ->get();

        return [
            'application' => $application,
            'period' => $application->period,
            'sections' => $sections,
            'percentage' => $percentage,
            'is_complete' => $isComplete,
            'missing' => $missing,
            'documents' => $application->documents->sortByDesc('id'),
            'document_slots' => $documentSlots,
            'can_edit' => $canEdit,
            'can_submit' => $canSubmit,
            'applicant_status' => $this->applicantStatus($application, $isComplete),
            'next_action' => $this->nextAction($application, $isComplete, $canSubmit),
            'whatsapp' => $this->whatsappState($application),
            'feedback' => $feedback,
        ];
    }

    /**
     * The applicant-facing status.
     *
     * The internal AdmissionApplicationStatus enum is a workflow state, not a
     * sentence: "draft" does not tell a parent whether anything is still
     * missing, and "submitted" does not tell them whether the school has looked
     * at it yet. This maps the workflow plus the completeness calculation onto
     * the states a school actually talks about.
     *
     * @return array{value: string, label: string, tone: string}
     */
    private function applicantStatus(AdmissionApplication $application, bool $isComplete): array
    {
        $state = match (true) {
            $application->isAccepted() => ['accepted', 'Diterima', 'emerald'],
            $application->status === AdmissionApplicationStatus::Rejected => ['rejected', 'Ditolak', 'rose'],
            $application->canRevise() => ['needs_revision', 'Perlu perbaikan', 'amber'],
            $application->status === AdmissionApplicationStatus::Waitlisted => ['waitlisted', 'Daftar tunggu', 'violet'],
            $application->status === AdmissionApplicationStatus::UnderReview => ['under_review', 'Dalam verifikasi', 'cyan'],
            $application->isSubmitted() => ['submitted', 'Diajukan', 'cyan'],
            $isComplete => ['ready', 'Siap diajukan', 'emerald'],
            $application->isDraft() => ['incomplete', 'Belum lengkap', 'amber'],
            default => ['draft', 'Draf', 'slate'],
        };

        return ['value' => $state[0], 'label' => $state[1], 'tone' => $state[2]];
    }

    /**
     * Submit the application.
     *
     * Refuses unless every required section is complete, so an incomplete
     * application can never reach the reviewer's queue.
     */
    public function submit(User $actor, AdmissionApplication $application, ?Request $request = null): AdmissionApplication
    {
        Gate::forUser($actor)->authorize('submitOwn', $application);

        if (! $request?->boolean('consent')) {
            throw ValidationException::withMessages([
                'consent' => 'Anda harus menyetujui pernyataan data sebelum mengajukan pendaftaran.',
            ]);
        }

        $progress = $this->progress($actor, $application);

        if (! $progress['is_complete']) {
            throw ValidationException::withMessages([
                'status' => 'Pengajuan belum lengkap: '.implode('; ', array_slice($progress['missing'], 0, 5)).'.',
            ]);
        }

        $period = $application->period;

        if (! $period instanceof AdmissionPeriod || ! $period->isCurrentlyOpen()) {
            throw ValidationException::withMessages([
                'status' => 'Periode PPDB untuk pengajuan ini sudah ditutup.',
            ]);
        }

        DB::transaction(function () use ($actor, $application, $request): void {
            $locked = AdmissionApplication::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Re-check on the locked row, not on the instance the authorization ran
             * against. Between the two, a reviewer may have accepted, rejected or
             * already converted this application. Writing a blind `submitted` here
             * would drag a decided application back into the queue and — because
             * conversion_status is reset below — erase a completed conversion.
             */
            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan ini sudah tidak berstatus draf, sehingga tidak dapat diajukan lagi.',
                ]);
            }

            if ($locked->conversion_status === AdmissionConversionStatus::Completed) {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan ini sudah diproses menjadi data siswa.',
                ]);
            }

            $previousStatus = $locked->status;

            $locked->forceFill([
                'status' => AdmissionApplicationStatus::Submitted,
                'submitted_at' => now(),
                'locked_at' => now(),
                'workflow_version' => ((int) $locked->workflow_version) + 1,
                'conversion_status' => AdmissionConversionStatus::NotStarted,
                'duplicate_fingerprint' => $this->fingerprint($locked),
                // Consent is recorded here, and only here: this is the moment the
                // candidate ticks the declaration on the review screen.
                'consent_version' => (string) config('sekolah-kita.ppdb.consent_version'),
                'consent_accepted_at' => now(),
            ])->save();

            ApplicationStatusHistory::query()->create([
                'admission_application_id' => $locked->getKey(),
                // Recorded from the locked row rather than assumed, so the timeline
                // cannot claim a transition that did not happen.
                'from_status' => $previousStatus,
                'to_status' => AdmissionApplicationStatus::Submitted,
                'actor_type' => AdmissionActorType::Applicant,
                'actor_user_id' => $actor->getKey(),
                'reason_code' => 'ppdb_portal_submitted',
                'public_message' => 'Pengajuan Anda lengkap dan sudah dikirim untuk ditinjau.',
                'correlation_id' => (string) Str::uuid(),
                'workflow_version' => (int) $locked->workflow_version,
                'occurred_at' => now(),
            ]);

            $this->audit->recordOrFail(
                $actor,
                'admission_application.submitted',
                'admission_application',
                $locked->getKey(),
                context: [
                    'status' => AdmissionApplicationStatus::Submitted->value,
                    'source' => 'ppdb_portal',
                ],
                request: $request,
            );
        }, attempts: 3);

        $this->notifications->deliverToAdministrators(
            new AdmissionStatusChangedNotification(
                (int) $application->getKey(),
                AdmissionApplicationStatus::Submitted->value,
            ),
            NotificationCategory::Admission,
        );

        $this->notifications->deliver(
            $actor,
            new CandidatePortalNotification(
                'Pengajuan PPDB terkirim',
                [
                    'Nomor pendaftaran: '.$application->registration_number,
                    'Pengajuan Anda sedang menunggu peninjauan administrator.',
                    'Pantau statusnya di dashboard PPDB.',
                ],
                NotificationCategory::Admission,
                route('ppdb.portal.status'),
            ),
            NotificationCategory::Admission,
            $application->contact_whatsapp,
        );

        return $application->refresh();
    }

    /**
     * Record a document decision and tell the applicant about it.
     *
     * Shared by the reviewer screens so the notification cannot drift from the
     * state change: the applicant is always told what happened and, when a
     * revision is needed, exactly why.
     */
    public function recordDocumentDecision(
        ApplicationDocument $document,
        AdmissionDocumentVerificationStatus $status,
        ?string $note,
    ): void {
        $application = $document->application;
        $type = $document->document_type;
        $owner = $application?->applicantUser;

        $this->notifications->deliver(
            $owner,
            new CandidatePortalNotification(
                match ($status) {
                    AdmissionDocumentVerificationStatus::Verified => 'Dokumen diterima',
                    AdmissionDocumentVerificationStatus::NeedsRevision => 'Dokumen perlu diperbaiki',
                    AdmissionDocumentVerificationStatus::Rejected => 'Dokumen ditolak',
                    default => 'Status dokumen diperbarui',
                },
                array_values(array_filter([
                    $type->label().' — '.$status->label().'.',
                    $note,
                    $status === AdmissionDocumentVerificationStatus::NeedsRevision
                        ? 'Silakan unggah ulang dokumen ini di halaman Dokumen Pendukung.'
                        : null,
                ])),
                NotificationCategory::Admission,
                $owner === null ? null : route('ppdb.portal.documents'),
            ),
            NotificationCategory::Admission,
            $application?->contact_whatsapp ?? $application?->contact_phone,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $seed
     */
    private function createDraft(User $user, AdmissionPeriod $period, array $seed): AdmissionApplication
    {
        $existing = AdmissionApplication::query()
            ->where('admission_period_id', $period->getKey())
            ->where('applicant_user_id', $user->getKey())
            ->lockForUpdate()
            ->first();

        if ($existing instanceof AdmissionApplication) {
            return $existing;
        }

        $application = new AdmissionApplication([
            'applicant_name' => $seed['applicant_name'],
            'contact_email' => $seed['contact_email'],
            'contact_phone' => $seed['contact_phone'] ?? null,
            'contact_whatsapp' => $seed['contact_whatsapp'] ?? null,
        ]);

        $application->forceFill([
            'admission_period_id' => $period->getKey(),
            'applicant_user_id' => $user->getKey(),
            'registration_number' => $this->uniqueRegistrationNumber(),
            'source' => 'ppdb_portal',
            'status' => AdmissionApplicationStatus::Draft,
            'conversion_status' => AdmissionConversionStatus::NotStarted,
            'workflow_version' => 1,
            'duplicate_fingerprint' => '',
            'duplicate_flag' => false,
            // No consent_version yet: a draft has not been submitted, so the
            // candidate has not seen or accepted the declaration. It is recorded
            // together with consent_accepted_at at submission time.
            'consent_version' => null,
            'consent_accepted_at' => null,
        ]);

        $application->save();

        // Recompute the duplicate fingerprint from whatever biodata exists so
        // the reviewer queue's duplicate detection is armed from day one.
        $application->forceFill(['duplicate_fingerprint' => $this->fingerprint($application)])->save();

        ApplicationStatusHistory::query()->create([
            'admission_application_id' => $application->getKey(),
            'from_status' => null,
            'to_status' => AdmissionApplicationStatus::Draft,
            'actor_type' => AdmissionActorType::Applicant,
            'actor_user_id' => $user->getKey(),
            'reason_code' => 'ppdb_portal_created',
            'public_message' => 'Pendaftaran PPDB Anda dibuat. Lengkapi setiap bagian sebelum mengirim.',
            'correlation_id' => (string) Str::uuid(),
            'workflow_version' => 1,
            'occurred_at' => now(),
        ]);

        return $application->refresh();
    }

    public function openPeriod(): ?AdmissionPeriod
    {
        return AdmissionPeriod::query()->open()->orderByDesc('opens_at')->first();
    }

    /**
     * @return array{complete: bool, ratio: int, missing: list<string>}
     */
    private function biodataSection(AdmissionApplication $application): array
    {
        $required = [
            'applicant_name' => 'nama lengkap',
            'national_id' => 'NIK',
            'birth_place' => 'tempat lahir',
            'birth_date' => 'tanggal lahir',
            'gender' => 'jenis kelamin',
            'address' => 'alamat',
            'regency' => 'kabupaten/kota',
            'province' => 'provinsi',
            'contact_phone' => 'nomor telepon',
        ];

        $missing = [];

        foreach ($required as $field => $label) {
            $value = $application->{$field};

            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[] = 'Biodata siswa ('.$label.')';
            }
        }

        $filled = count($required) - count($missing);

        return [
            'complete' => $missing === [],
            'ratio' => (int) round(($filled / count($required)) * 100),
            'missing' => $missing,
        ];
    }

    /**
     * @return array{complete: bool, ratio: int, missing: list<string>}
     */
    private function guardianSection(AdmissionApplication $application): array
    {
        $guardians = $application->guardians;
        $missing = [];

        if ($guardians->isEmpty()) {
            $missing[] = 'Orang tua / wali (minimal satu wali)';
        } else {
            if (! $guardians->contains(fn (ApplicationGuardian $guardian): bool => $guardian->is_primary_contact)) {
                $missing[] = 'Orang tua / wali (kontak utama belum dipilih)';
            }

            foreach ($guardians as $guardian) {
                if ($guardian->legal_name === null || trim($guardian->legal_name) === '') {
                    $missing[] = 'Orang tua / wali (nama wali belum lengkap)';
                }
            }
        }

        $ratio = match (true) {
            $guardians->isEmpty() => 0,
            $missing === [] => 100,
            default => 50,
        };

        return ['complete' => $missing === [], 'ratio' => $ratio, 'missing' => $missing];
    }

    /**
     * @return array{complete: bool, ratio: int, missing: list<string>}
     */
    private function documentSection(AdmissionApplication $application): array
    {
        $required = AdmissionDocumentType::required();
        $supplied = $application->documents
            ->filter(fn (ApplicationDocument $document): bool => $document->verification_status->isSupplied())
            ->pluck('document_type')
            ->unique()
            ->all();

        $missing = [];
        $filled = 0;

        foreach ($required as $type) {
            if (in_array($type, $supplied, true)) {
                $filled++;

                continue;
            }

            $missing[] = 'Dokumen ('.$type->label().')';
        }

        return [
            'complete' => $missing === [],
            'ratio' => (int) round(($filled / count($required)) * 100),
            'missing' => $missing,
        ];
    }

    /**
     * @return array{label: string, route: string}
     */
    private function nextAction(AdmissionApplication $application, bool $isComplete, bool $canSubmit): array
    {
        if ($application->isAccepted()) {
            return ['label' => 'Lihat hasil penerimaan', 'route' => 'ppdb.portal.status'];
        }

        if ($application->status === AdmissionApplicationStatus::Rejected) {
            return ['label' => 'Baca alasan penolakan', 'route' => 'ppdb.portal.status'];
        }

        if ($canSubmit) {
            return ['label' => 'Ajukan pendaftaran', 'route' => 'ppdb.portal.review'];
        }

        if ($application->canRevise()) {
            $pending = $application->documents
                ->filter(fn (ApplicationDocument $document): bool => $document->needsReplacement())
                ->first();

            if ($pending instanceof ApplicationDocument) {
                return ['label' => 'Unggah ulang '.$pending->document_type->label(), 'route' => 'ppdb.portal.documents'];
            }

            return ['label' => 'Perbarui data pendaftaran', 'route' => 'ppdb.portal.biodata'];
        }

        if ($application->isPortalEditable()) {
            $progress = $this->biodataSection($application);
            if (! $progress['complete']) {
                return ['label' => 'Lengkapi biodata siswa', 'route' => 'ppdb.portal.biodata'];
            }

            $guardians = $this->guardianSection($application);
            if (! $guardians['complete']) {
                return ['label' => 'Lengkapi data orang tua / wali', 'route' => 'ppdb.portal.guardians'];
            }

            return ['label' => 'Unggah dokumen pendukung', 'route' => 'ppdb.portal.documents'];
        }

        if ($application->isSubmitted()) {
            return ['label' => 'Pantau status pendaftaran', 'route' => 'ppdb.portal.status'];
        }

        return ['label' => 'Lihat status pendaftaran', 'route' => 'ppdb.portal.status'];
    }

    /**
     * @return array{driver: string, configured: bool, reason: string|null, number: string|null}
     */
    private function whatsappState(AdmissionApplication $application): array
    {
        $status = $this->notifications->whatsappStatus();

        return [
            'driver' => $status['driver'],
            'configured' => $status['configured'],
            'reason' => $status['reason'],
            'number' => $application->contact_whatsapp ?? $application->contact_phone,
        ];
    }

    private function uniqueRegistrationNumber(): string
    {
        do {
            $number = 'PPDB-'.now()->format('Y').'-'.Str::upper(Str::random(8));
        } while (AdmissionApplication::query()->where('registration_number', $number)->exists());

        return $number;
    }

    private function fingerprint(AdmissionApplication $application): string
    {
        return hash_hmac('sha256', implode('|', [
            (string) $application->applicant_name,
            $application->birth_date?->toDateString() ?? '',
            (string) $application->contact_email,
        ]), (string) config('app.key'));
    }

    private function normalisePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $trimmed = trim($phone);

        if ($trimmed === '') {
            return null;
        }

        // Keep the digits and the leading international marker only, so the same
        // number typed as 0812…, +62812… or 62812… stores identically.
        $normalised = preg_replace('/(?!^\+)[^\d]/', '', $trimmed) ?? '';

        if ($normalised === '') {
            return null;
        }

        return Str::limit($normalised, 30, '');
    }

    private function blankToNull(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
