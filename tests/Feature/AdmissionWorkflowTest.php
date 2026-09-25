<?php

namespace Tests\Feature;

use App\Enums\AdmissionAccessTokenPurpose;
use App\Enums\AdmissionActorType;
use App\Enums\AdmissionApplicationStatus;
use App\Enums\AdmissionDocumentType;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPeriod;
use App\Models\ApplicationStatusHistory;
use App\Models\ClassModel;
use App\Models\MediaAsset;
use App\Models\Student;
use App\Models\User;
use App\Notifications\AdmissionApplicantStatusEmailNotification;
use App\Notifications\AdmissionStatusChangedNotification;
use App\Services\AdmissionAccessService;
use App\Services\AdmissionApplicationService;
use App\Services\AdmissionConversionService;
use App\Services\AdmissionDocumentService;
use App\Services\EnrollmentService;
use App\Services\PrivateDocumentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdmissionWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ppdb_schema_is_additive_and_has_no_applicant_account_boundary(): void
    {
        foreach ([
            'admission_periods',
            'admission_applications',
            'application_guardians',
            'application_documents',
            'admission_reviews',
            'application_status_histories',
            'admission_application_access_tokens',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table.' is missing');
        }

        $this->assertTrue(Schema::hasColumns('media_assets', ['media_kind', 'original_filename', 'scan_status', 'scanned_at']));
        $this->assertFalse(Schema::hasColumn('admission_applications', 'user_id'));
        $this->assertFalse(Schema::hasColumn('admission_applications', 'school_id'));
        $this->assertFalse(Schema::hasColumn('application_guardians', 'user_id'));

        if (DB::getDriverName() === 'mysql') {
            foreach ([
                'admission_periods_public_window_index' => 'admission_periods',
                'admission_applications_period_queue_index' => 'admission_applications',
                'admission_applications_reviewer_queue_index' => 'admission_applications',
                'application_documents_type_version_unique' => 'application_documents',
                'application_status_histories_application_version_unique' => 'application_status_histories',
            ] as $index => $table) {
                $this->assertNotEmpty(DB::select('SHOW INDEX FROM '.$table.' WHERE Key_name = "'.$index.'"'));
            }
        }
    }

    public function test_public_period_and_submission_create_a_limited_application_without_user(): void
    {
        $period = AdmissionPeriod::factory()->create();
        $payload = $this->applicationPayload($period);

        $this->get(route('ppdb.index'))->assertOk()->assertSee($period->name);
        $this->get(route('ppdb.application.create'))->assertOk()->assertSee('Formulir pengajuan');
        $response = $this->post(route('ppdb.application.store'), $payload);

        $response->assertRedirect();
        $application = AdmissionApplication::query()->firstOrFail();
        $this->assertSame(AdmissionApplicationStatus::Submitted, $application->status);
        $this->assertSame(0, User::query()->count());
        $this->assertDatabaseHas('admission_applications', ['id' => $application->id, 'consent_version' => config('sekolah-kita.ppdb.consent_version')]);
        $this->assertArrayNotHasKey('period', app(AdmissionApplicationService::class)->publicStatus($application));
        $this->assertArrayNotHasKey('submitted_at', app(AdmissionApplicationService::class)->publicStatus($application));
        $this->assertDatabaseCount('application_status_histories', 1);
        $this->assertStringContainsString('/ppdb/status/', $response->headers->get('Location'));
    }

    public function test_submission_rejects_closed_period_and_invalid_consent_without_creating_data(): void
    {
        $period = AdmissionPeriod::factory()->closed()->create();

        $this->from(route('ppdb.application.create'))->post(route('ppdb.application.store'), $this->applicationPayload($period))
            ->assertSessionHasErrors('admission_period_id');

        $this->assertSame(0, AdmissionApplication::query()->count());
    }

    public function test_registration_number_alone_does_not_grant_status_access(): void
    {
        $period = AdmissionPeriod::factory()->create();
        $this->post(route('ppdb.application.store'), $this->applicationPayload($period));
        $application = AdmissionApplication::query()->firstOrFail();

        $this->get('/ppdb/status/'.$application->registration_number)->assertNotFound();
        $this->get('/ppdb/status/'.str_repeat('a', 64))->assertNotFound();
    }

    public function test_status_token_returns_only_limited_projection_and_never_applicant_pii(): void
    {
        $period = AdmissionPeriod::factory()->create();
        $response = $this->post(route('ppdb.application.store'), $this->applicationPayload($period));
        $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
        $application = AdmissionApplication::query()->firstOrFail();

        $response = $this->get(route('ppdb.status.show', $token));
        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee($application->registration_number)
            ->assertSee('Dikyatmakan')
            ->assertDontSee($application->applicant_name)
            ->assertDontSee($application->contact_email);
    }

    public function test_only_assigned_reviewer_can_transition_application(): void
    {
        $period = AdmissionPeriod::factory()->create();
        $application = AdmissionApplication::factory()->create(['admission_period_id' => $period->id]);
        $assigned = User::factory()->administrator()->create();
        $other = User::factory()->administrator()->create();

        $this->actingAs($other)->post(route('admin.ppdb.applications.reviewer.assign', $application), ['reviewer_user_id' => $assigned->id])->assertRedirect();
        $this->actingAs($other)->post(route('admin.ppdb.applications.start-review', $application))->assertForbidden();
        $this->actingAs($assigned)->post(route('admin.ppdb.applications.start-review', $application))->assertRedirect();

        $this->assertSame(AdmissionApplicationStatus::UnderReview, $application->refresh()->status);
        $this->assertDatabaseHas('admission_reviews', ['admission_application_id' => $application->id, 'reviewer_user_id' => $assigned->id]);
    }

    public function test_accepted_application_requires_documents_and_target_class_before_conversion(): void
    {
        $period = AdmissionPeriod::factory()->create();
        $class = ClassModel::factory()->for($period->academicYear)->active()->create(['capacity' => 2]);
        $reviewer = User::factory()->administrator()->create();
        $application = AdmissionApplication::factory()->accepted()->create([
            'admission_period_id' => $period->id,
            'assigned_reviewer_user_id' => $reviewer->id,
            'target_class_id' => $class->id,
        ]);

        $this->actingAs($reviewer)->post(route('admin.ppdb.applications.convert', $application))->assertSessionHasErrors('documents');
        $this->assertNull($application->converted_student_id);
    }

    public function test_document_upload_is_private_quarantined_and_download_is_audited(): void
    {
        Storage::fake('local');
        $period = AdmissionPeriod::factory()->create();
        $reviewer = User::factory()->administrator()->create();
        $application = AdmissionApplication::factory()->create([
            'admission_period_id' => $period->id,
            'assigned_reviewer_user_id' => $reviewer->id,
            'status' => AdmissionApplicationStatus::UnderReview,
        ]);
        Queue::fake();
        $pdf = UploadedFile::fake()->createWithContent('identitas.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n");

        $this->actingAs($reviewer)->post(route('admin.ppdb.applications.documents.store', $application), [
            'document_type' => AdmissionDocumentType::Identity->value,
            'document' => $pdf,
        ])->assertRedirect();

        $document = $application->documents()->firstOrFail();
        $media = $document->mediaAsset;
        $this->assertSame('private', $media->visibility->value);
        $this->assertSame('quarantined', $media->processing_status->value);
        Storage::disk('local')->assertExists($media->private_object_key);

        $this->assertTrue(app(PrivateDocumentService::class)->inspect($media));
        $this->actingAs($reviewer)->get(route('admin.ppdb.documents.download', $document))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'admission_document.downloaded']);
    }

    public function test_quarantined_document_cannot_be_downloaded(): void
    {
        Storage::fake('local');
        Queue::fake();
        $reviewer = User::factory()->administrator()->create();
        $application = AdmissionApplication::factory()->underReview()->create([
            'assigned_reviewer_user_id' => $reviewer->id,
        ]);
        $document = $application->documents()->create([
            'media_asset_id' => MediaAsset::factory()->create([
                'media_kind' => 'document',
                'processing_status' => 'quarantined',
                'scan_status' => 'pending',
            ])->id,
            'document_type' => AdmissionDocumentType::Identity,
            'version' => 1,
            'verification_status' => 'pending',
        ]);

        $this->expectException(ValidationException::class);
        app(AdmissionDocumentService::class)->download($reviewer, $document);
    }

    public function test_private_image_document_is_sanitized_before_quarantine(): void
    {
        Storage::fake('local');
        Queue::fake();
        $reviewer = User::factory()->administrator()->create();
        $application = AdmissionApplication::factory()->underReview()->create([
            'assigned_reviewer_user_id' => $reviewer->id,
        ]);
        $image = UploadedFile::fake()->image('identitas.png', 40, 30);
        $originalBinary = file_get_contents($image->getRealPath());

        $document = app(AdmissionDocumentService::class)->uploadForReviewer(
            $reviewer,
            $application,
            $image,
            AdmissionDocumentType::Identity,
        );
        $storedBinary = Storage::disk('local')->get($document->mediaAsset->private_object_key);
        $storedInfo = getimagesizefromstring((string) $storedBinary);

        $this->assertNotSame($originalBinary, $storedBinary);
        $this->assertNotNull($storedInfo);
        $this->assertSame(40, $storedInfo[0]);
        $this->assertSame(30, $storedInfo[1]);
        $this->assertNotNull($document->mediaAsset->metadata_sanitized_at);
        $this->assertTrue(app(PrivateDocumentService::class)->inspect($document->mediaAsset));
    }

    public function test_applicant_document_upload_requires_a_revision_and_purpose_scoped_token(): void
    {
        Storage::fake('local');
        Queue::fake();
        $period = AdmissionPeriod::factory()->create();
        $reviewer = User::factory()->administrator()->create();
        $application = AdmissionApplication::factory()->revisionRequested()->create([
            'admission_period_id' => $period->id,
            'assigned_reviewer_user_id' => $reviewer->id,
        ]);
        $access = app(AdmissionAccessService::class);
        $statusToken = $access->issue($application, AdmissionAccessTokenPurpose::Status);
        $documentToken = $access->issue($application, AdmissionAccessTokenPurpose::DocumentUpload);
        $pdf = UploadedFile::fake()->createWithContent('identitas.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n");

        $this->post(route('ppdb.status.document.store', $statusToken), [
            'access_token' => $documentToken,
            'document_type' => AdmissionDocumentType::Identity->value,
            'document' => $pdf,
        ])->assertRedirect();

        $this->assertDatabaseHas('application_documents', [
            'admission_application_id' => $application->id,
            'verification_status' => 'pending',
        ]);
        $this->assertSame(0, User::query()->where('email', $application->contact_email)->count());
    }

    public function test_replacing_a_document_allocates_a_new_version_and_supersedes_the_previous_one(): void
    {
        Storage::fake('local');
        Queue::fake();
        $reviewer = User::factory()->administrator()->create();
        $application = AdmissionApplication::factory()->underReview()->create([
            'assigned_reviewer_user_id' => $reviewer->id,
        ]);
        $documents = app(AdmissionDocumentService::class);
        $first = $documents->uploadForReviewer(
            $reviewer,
            $application,
            UploadedFile::fake()->createWithContent('identitas.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n"),
            AdmissionDocumentType::Identity,
        );
        $this->assertTrue(app(PrivateDocumentService::class)->inspect($first->mediaAsset));
        $documents->verify($reviewer, $first->refresh());

        $second = $documents->uploadForReviewer(
            $reviewer,
            $application,
            UploadedFile::fake()->createWithContent('identitas-baru.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n"),
            AdmissionDocumentType::Identity,
        );

        $this->assertSame(1, $first->refresh()->version);
        $this->assertSame(2, $second->version);
        $this->assertSame('superseded', $first->verification_status->value);
        $this->assertSame('pending', $second->verification_status->value);
    }

    public function test_conversion_is_transactional_idempotent_and_never_creates_user(): void
    {
        $period = AdmissionPeriod::factory()->create();
        $class = ClassModel::factory()->for($period->academicYear)->active()->create(['capacity' => 2]);
        $reviewer = User::factory()->administrator()->create();
        $application = AdmissionApplication::factory()->accepted()->create([
            'admission_period_id' => $period->id,
            'assigned_reviewer_user_id' => $reviewer->id,
            'target_class_id' => $class->id,
        ]);
        $application->documents()->create([
            'media_asset_id' => MediaAsset::factory()->create(['media_kind' => 'document', 'processing_status' => 'ready', 'scan_status' => 'clean'])->id,
            'document_type' => AdmissionDocumentType::Identity,
            'version' => 1,
            'verification_status' => 'verified',
        ]);
        Notification::fake();

        $first = app(AdmissionConversionService::class)->convert($reviewer, $application);
        $second = app(AdmissionConversionService::class)->convert($reviewer, $application->refresh());

        $this->assertSame($first['student']->id, $second['student']->id);
        $this->assertSame($first['enrollment']->id, $second['enrollment']->id);
        $this->assertSame(1, $application->refresh()->converted_student_id !== null ? 1 : 0);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('users', 1);
        Notification::assertSentToOnce($reviewer, AdmissionStatusChangedNotification::class);
        Notification::assertSentOnDemandOnce(AdmissionApplicantStatusEmailNotification::class);
    }

    public function test_class_capacity_is_enforced_during_conversion(): void
    {
        $period = AdmissionPeriod::factory()->create();
        $class = ClassModel::factory()->for($period->academicYear)->active()->create(['capacity' => 1]);
        $existingStudent = Student::factory()->create();
        app(EnrollmentService::class)->create(
            User::factory()->administrator()->create(),
            $existingStudent,
            $class,
            today()->toDateString(),
        );
        $reviewer = User::factory()->administrator()->create();
        $application = AdmissionApplication::factory()->accepted()->create([
            'admission_period_id' => $period->id,
            'assigned_reviewer_user_id' => $reviewer->id,
            'target_class_id' => $class->id,
        ]);
        $application->documents()->create([
            'media_asset_id' => MediaAsset::factory()->create(['media_kind' => 'document', 'processing_status' => 'ready', 'scan_status' => 'clean'])->id,
            'document_type' => AdmissionDocumentType::Identity,
            'version' => 1,
            'verification_status' => 'verified',
        ]);

        $this->actingAs($reviewer)->post(route('admin.ppdb.applications.convert', $application))->assertSessionHasErrors();
        $this->assertDatabaseCount('students', 1);
    }

    public function test_correction_requires_revision_purpose_and_preserves_omitted_fields(): void
    {
        $period = AdmissionPeriod::factory()->create();
        $reviewer = User::factory()->administrator()->create();
        $submittedAt = now()->subDay();
        $application = AdmissionApplication::factory()->revisionRequested()->create([
            'admission_period_id' => $period->id,
            'assigned_reviewer_user_id' => $reviewer->id,
            'applicant_name' => 'Nama Lama',
            'preferred_name' => 'Panggilan Lama',
            'birth_date' => '2012-01-02',
            'birth_place' => 'Kota Lama',
            'gender' => 'P',
            'contact_email' => 'lama@example.test',
            'contact_phone' => '081200000001',
            'address' => 'Alamat lama',
            'current_school' => 'SD Lama',
            'requested_grade_level' => 7,
            'submitted_at' => $submittedAt,
        ]);
        $access = app(AdmissionAccessService::class);
        $statusToken = $access->issue($application, AdmissionAccessTokenPurpose::Status);
        $revisionToken = $access->issue($application, AdmissionAccessTokenPurpose::Revision);
        $documentToken = $access->issue($application, AdmissionAccessTokenPurpose::DocumentUpload);

        $this->post(route('ppdb.status.correct', $statusToken), [
            'access_token' => $statusToken,
            'applicant_name' => 'Nama Baru',
            'contact_email' => 'baru@example.test',
            'contact_phone' => '081200000002',
            'address' => 'Alamat baru',
            'consent' => '1',
        ])->assertNotFound();

        $this->assertSame(AdmissionApplicationStatus::RevisionRequested, $application->refresh()->status);

        $this->post(route('ppdb.status.correct', $statusToken), [
            'access_token' => $revisionToken,
            'applicant_name' => 'Nama Baru',
            'contact_email' => 'baru@example.test',
            'contact_phone' => '081200000002',
            'address' => 'Alamat baru',
            'consent' => '1',
        ])->assertRedirect(route('ppdb.status.show', $statusToken));

        $application->refresh();
        $this->assertSame(AdmissionApplicationStatus::Submitted, $application->status);
        $this->assertSame('Panggilan Lama', $application->preferred_name);
        $this->assertSame('2012-01-02', $application->birth_date->toDateString());
        $this->assertSame('Kota Lama', $application->birth_place);
        $this->assertSame('P', $application->gender);
        $this->assertSame('SD Lama', $application->current_school);
        $this->assertSame(7, $application->requested_grade_level);
        $this->assertNull($application->revision_deadline);
        $this->assertNull($application->contact_verified_at);
        $this->assertTrue($application->submitted_at->greaterThan($submittedAt));
        $this->assertNull($access->resolve($revisionToken, AdmissionAccessTokenPurpose::Revision));
        $this->assertNull($access->resolve($documentToken, AdmissionAccessTokenPurpose::DocumentUpload));
    }

    public function test_correction_and_document_upload_are_closed_after_revision_deadline(): void
    {
        $period = AdmissionPeriod::factory()->create();
        $application = AdmissionApplication::factory()->revisionRequested()->create([
            'admission_period_id' => $period->id,
            'assigned_reviewer_user_id' => User::factory()->administrator()->create()->id,
            'revision_deadline' => now()->subMinute(),
        ]);
        $access = app(AdmissionAccessService::class);
        $statusToken = $access->issue($application, AdmissionAccessTokenPurpose::Status);
        $revisionToken = $access->issue($application, AdmissionAccessTokenPurpose::Revision);
        $documentToken = $access->issue($application, AdmissionAccessTokenPurpose::DocumentUpload);

        $this->get(route('ppdb.status.show', $statusToken))
            ->assertOk()
            ->assertSee('Batas revisi')
            ->assertDontSee('Kirim revisi');

        $this->post(route('ppdb.status.correct', $statusToken), [
            'access_token' => $revisionToken,
            'applicant_name' => 'Still Late',
            'contact_email' => 'late@example.test',
            'contact_phone' => '081200000003',
            'consent' => '1',
        ])->assertSessionHasErrors('status');

        $this->post(route('ppdb.status.document.store', $statusToken), [
            'access_token' => $documentToken,
            'document_type' => AdmissionDocumentType::Identity->value,
            'document' => UploadedFile::fake()->createWithContent('identitas.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n"),
        ])->assertSessionHasErrors('document');

        $this->assertSame(0, $application->documents()->count());
    }

    public function test_conversion_rejects_verified_document_with_failed_media_scan(): void
    {
        $period = AdmissionPeriod::factory()->create();
        $class = ClassModel::factory()->for($period->academicYear)->active()->create(['capacity' => 2]);
        $reviewer = User::factory()->administrator()->create();
        $application = AdmissionApplication::factory()->accepted()->create([
            'admission_period_id' => $period->id,
            'assigned_reviewer_user_id' => $reviewer->id,
            'target_class_id' => $class->id,
        ]);
        $application->documents()->create([
            'media_asset_id' => MediaAsset::factory()->create([
                'media_kind' => 'document',
                'processing_status' => 'failed',
                'scan_status' => 'failed',
            ])->id,
            'document_type' => AdmissionDocumentType::Identity,
            'version' => 1,
            'verification_status' => 'verified',
        ]);

        $this->actingAs($reviewer)
            ->post(route('admin.ppdb.applications.convert', $application))
            ->assertSessionHasErrors('documents');

        $this->assertDatabaseCount('students', 0);
    }

    public function test_conversion_enforces_period_quota_after_the_first_conversion(): void
    {
        $period = AdmissionPeriod::factory()->create(['quota' => 1]);
        $class = ClassModel::factory()->for($period->academicYear)->active()->create(['capacity' => 2]);
        $reviewer = User::factory()->administrator()->create();
        $applications = AdmissionApplication::factory()->count(2)->accepted()->create([
            'admission_period_id' => $period->id,
            'assigned_reviewer_user_id' => $reviewer->id,
            'target_class_id' => $class->id,
        ]);

        foreach ($applications as $application) {
            $application->documents()->create([
                'media_asset_id' => MediaAsset::factory()->create([
                    'media_kind' => 'document',
                    'processing_status' => 'ready',
                    'scan_status' => 'clean',
                ])->id,
                'document_type' => AdmissionDocumentType::Identity,
                'version' => 1,
                'verification_status' => 'verified',
            ]);
        }

        $this->actingAs($reviewer)->post(route('admin.ppdb.applications.convert', $applications->first()))->assertRedirect();
        $this->actingAs($reviewer)->post(route('admin.ppdb.applications.convert', $applications->last()))
            ->assertSessionHasErrors('quota');

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_unassigned_administrator_cannot_mutate_conversion_state(): void
    {
        $period = AdmissionPeriod::factory()->create();
        $assigned = User::factory()->administrator()->create();
        $unassigned = User::factory()->administrator()->create();
        $application = AdmissionApplication::factory()->accepted()->create([
            'admission_period_id' => $period->id,
            'assigned_reviewer_user_id' => $assigned->id,
        ]);

        $this->actingAs($unassigned)
            ->post(route('admin.ppdb.applications.convert', $application))
            ->assertForbidden();

        $this->assertSame('not_started', $application->refresh()->conversion_status->value);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'admission_application.conversion_failed']);
    }

    public function test_expired_access_token_cannot_resolve(): void
    {
        $application = AdmissionApplication::factory()->create();
        $access = app(AdmissionAccessService::class);
        $token = $access->issue($application, AdmissionAccessTokenPurpose::Status, -1);

        $this->assertNull($access->resolve($token, AdmissionAccessTokenPurpose::Status));
    }

    public function test_withdraw_requires_a_withdrawal_purpose_token_and_revokes_write_capabilities(): void
    {
        $period = AdmissionPeriod::factory()->create();
        $application = AdmissionApplication::factory()->create([
            'admission_period_id' => $period->id,
            'assigned_reviewer_user_id' => User::factory()->administrator()->create()->id,
        ]);
        $access = app(AdmissionAccessService::class);
        $statusToken = $access->issue($application, AdmissionAccessTokenPurpose::Status);
        $revisionToken = $access->issue($application, AdmissionAccessTokenPurpose::Revision);
        $documentToken = $access->issue($application, AdmissionAccessTokenPurpose::DocumentUpload);
        $withdrawalToken = $access->issue($application, AdmissionAccessTokenPurpose::Withdrawal);

        $this->post(route('ppdb.status.withdraw', $statusToken), [
            'access_token' => $statusToken,
        ])->assertNotFound();
        $this->assertSame(AdmissionApplicationStatus::Submitted, $application->refresh()->status);

        $this->post(route('ppdb.status.withdraw', $statusToken), [
            'access_token' => $withdrawalToken,
        ])->assertRedirect(route('ppdb.status.show', $statusToken));

        $this->assertSame(AdmissionApplicationStatus::Withdrawn, $application->refresh()->status);
        $this->assertNull($access->resolve($revisionToken, AdmissionAccessTokenPurpose::Revision));
        $this->assertNull($access->resolve($documentToken, AdmissionAccessTokenPurpose::DocumentUpload));
        $this->assertNull($access->resolve($withdrawalToken, AdmissionAccessTokenPurpose::Withdrawal));
    }

    public function test_application_status_history_is_append_only(): void
    {
        $application = AdmissionApplication::factory()->create();
        $history = ApplicationStatusHistory::query()->create([
            'admission_application_id' => $application->id,
            'from_status' => null,
            'to_status' => AdmissionApplicationStatus::Submitted,
            'actor_type' => AdmissionActorType::Applicant,
            'reason_code' => 'test',
            'workflow_version' => 1,
            'occurred_at' => now(),
        ]);

        $this->expectException(\LogicException::class);
        $history->update(['reason_code' => 'tampered']);
    }

    public function test_unassigned_administrator_does_not_receive_sensitive_application_projection(): void
    {
        $assigned = User::factory()->administrator()->create();
        $unassigned = User::factory()->administrator()->create();
        $application = AdmissionApplication::factory()->underReview()->create([
            'assigned_reviewer_user_id' => $assigned->id,
            'applicant_name' => 'Pemohon Sensitif',
            'contact_email' => 'sensitive@example.test',
        ]);

        $this->actingAs($unassigned)
            ->get(route('admin.ppdb.applications.show', $application))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee($application->registration_number)
            ->assertDontSee('Pemohon Sensitif')
            ->assertDontSee('sensitive@example.test');

        $this->actingAs($assigned)
            ->get(route('admin.ppdb.applications.show', $application))
            ->assertOk()
            ->assertSee('Pemohon Sensitif')
            ->assertSee('sensitive@example.test')
            ->assertSee('name="_token"', false);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $assigned->id,
            'action' => 'admission_application.viewed',
            'target_id' => (string) $application->id,
        ]);
    }

    public function test_administrator_can_open_ppdb_period_create_and_edit_pages(): void
    {
        $administrator = User::factory()->administrator()->create();
        $period = AdmissionPeriod::factory()->create();

        $this->actingAs($administrator)->get(route('admin.ppdb.periods.create'))->assertOk();
        $this->actingAs($administrator)->get(route('admin.ppdb.periods.edit', $period))->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationPayload(AdmissionPeriod $period): array
    {
        return [
            'admission_period_id' => $period->id,
            'applicant_name' => 'Budi Santoso',
            'preferred_name' => 'Budi',
            'birth_date' => '2012-05-10',
            'birth_place' => 'Bandung',
            'gender' => 'L',
            'contact_email' => 'budi@example.test',
            'contact_phone' => '08123456789',
            'address' => 'Jl.uji',
            'current_school' => 'SD Contoh',
            'requested_grade_level' => 7,
            'consent' => '1',
            'guardians' => [[
                'legal_name' => 'Bapak Budi',
                'relationship_type' => 'father',
                'phone' => '08123456789',
                'email' => 'wali@example.test',
                'address' => 'Jl. uji',
                'is_primary_contact' => true,
                'is_emergency_contact' => true,
                'priority_order' => 1,
            ]],
        ];
    }
}
