<?php

namespace Tests\Feature;

use App\Enums\AdmissionActorType;
use App\Enums\AdmissionApplicationStatus;
use App\Enums\AdmissionConversionStatus;
use App\Enums\AdmissionDocumentType;
use App\Enums\AdmissionDocumentVerificationStatus;
use App\Enums\AdmissionPeriodStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPeriod;
use App\Models\ApplicationStatusHistory;
use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\MediaAsset;
use App\Models\Student;
use App\Models\User;
use App\Notifications\CandidatePortalNotification;
use App\Services\ApplicantPortalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The PPDB applicant portal.
 *
 * The load-bearing property across this file is that an applicant is an
 * applicant and nothing else: no Student record, no Enrollment, no class, and no
 * access to any other candidate's application, guardians, documents or history.
 */
class ApplicantPortalTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function openPeriod(): AdmissionPeriod
    {
        $year = AcademicYear::factory()->create([
            'starts_on' => now()->subMonths(6)->toDateString(),
            'ends_on' => now()->addMonths(6)->toDateString(),
        ]);

        return AdmissionPeriod::factory()->create([
            'academic_year_id' => $year->getKey(),
            'status' => AdmissionPeriodStatus::Open,
            'opens_at' => now()->subMonth(),
            'closes_at' => now()->addMonths(2),
            'published_at' => now()->subMonth(),
        ]);
    }

    private function applicant(?AdmissionPeriod $period = null): array
    {
        $period ??= $this->openPeriod();
        $user = User::factory()->calon()->create(['email_verified_at' => now()]);

        $application = new AdmissionApplication([
            'applicant_name' => $user->name,
            'contact_email' => $user->email,
            'contact_phone' => '081200000001',
            'contact_whatsapp' => '081200000001',
        ]);
        $application->forceFill([
            'admission_period_id' => $period->getKey(),
            'applicant_user_id' => $user->getKey(),
            'registration_number' => 'PPDB-TEST-'.strtoupper(bin2hex(random_bytes(3))),
            'source' => 'ppdb_portal',
            'status' => AdmissionApplicationStatus::Draft,
            'conversion_status' => AdmissionConversionStatus::NotStarted,
            'workflow_version' => 1,
            'duplicate_fingerprint' => hash('sha256', $user->email),
        ]);
        $application->save();

        ApplicationStatusHistory::query()->create([
            'admission_application_id' => $application->getKey(),
            'from_status' => null,
            'to_status' => AdmissionApplicationStatus::Draft,
            'actor_type' => AdmissionActorType::Applicant,
            'actor_user_id' => $user->getKey(),
            'reason_code' => 'ppdb_portal_created',
            'public_message' => 'Pendaftaran PPDB Anda dibuat.',
            'correlation_id' => (string) Str::uuid(),
            'workflow_version' => 1,
            'occurred_at' => now(),
        ]);

        return [$user->refresh(), $application->refresh()];
    }

    /**
     * Put an application into a complete, submittable state.
     */
    private function completeApplication(AdmissionApplication $application): void
    {
        $application->fill([
            'applicant_name' => 'Nur Aisyah',
            'national_id' => '3273014501900001',
            'birth_place' => 'Bandung',
            'birth_date' => '2013-04-05',
            'gender' => 'P',
            'address' => 'Jl. Mawar No. 12',
            'regency' => 'Bandung',
            'province' => 'Jawa Barat',
            'contact_phone' => '081200000001',
        ])->save();

        $application->guardians()->create([
            'legal_name' => 'Budi Santoso',
            'national_id' => '3273011201800001',
            'relationship_type' => 'father',
            'phone' => '081200000002',
            'whatsapp_number' => '081200000002',
            'is_primary_contact' => true,
            'priority_order' => 1,
        ]);

        foreach (AdmissionDocumentType::required() as $type) {
            $media = MediaAsset::query()->create([
                'uploader_user_id' => $application->applicant_user_id,
                'media_kind' => 'document',
                'original_filename' => $type->value.'.pdf',
                'private_disk' => 'local',
                'private_object_key' => 'admission-documents/'.fake()->uuid().'.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 1024,
                'checksum_sha256' => hash('sha256', 'test-'.$type->value),
                'processing_status' => 'ready',
                'scan_status' => 'clean',
                'scanned_at' => now(),
                'visibility' => 'private',
            ]);

            $application->documents()->create([
                'media_asset_id' => $media->getKey(),
                'document_type' => $type,
                'version' => 1,
                'verification_status' => AdmissionDocumentVerificationStatus::Pending,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    */

    public function test_a_visitor_can_register_an_applicant_account(): void
    {
        Notification::fake();

        $this->openPeriod();

        $this->get(route('ppdb.portal.register.create'))->assertOk()->assertSee('Buat akun pendaftar');

        $this->post(route('ppdb.portal.register.store'), [
            'name' => 'Calon Pendaftar',
            'email' => 'calon@contoh.test',
            'phone' => '081200000009',
            'password' => 'SekolahKita#Pendaftaran2026',
            'password_confirmation' => 'SekolahKita#Pendaftaran2026',
            'consent' => '1',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'calon@contoh.test')->firstOrFail();

        $this->assertSame(UserRole::Calon, $user->role);
        $this->assertTrue($user->hasRole(UserRole::Calon));

        // The applicant is NOT a student. This is the single most important
        // assertion in this file.
        $this->assertNull($user->student);
        $this->assertSame(0, Student::query()->count());
        $this->assertSame(0, Enrollment::query()->count());

        $application = AdmissionApplication::query()->where('applicant_user_id', $user->getKey())->firstOrFail();
        $this->assertSame(AdmissionApplicationStatus::Draft, $application->status);
        $this->assertSame('ppdb_portal', $application->source);
        $this->assertNotEmpty($application->registration_number);
    }

    public function test_applicant_registration_validates_its_input(): void
    {
        $this->openPeriod();

        $existing = User::factory()->administrator()->create(['email' => 'sudah@ada.test']);

        $this->post(route('ppdb.portal.register.store'), [
            'name' => '',
            'email' => $existing->email,
            'phone' => 'bukan-nomor',
            'password' => 'pendek',
            'password_confirmation' => 'lain',
        ])->assertSessionHasErrors(['name', 'email', 'phone', 'password', 'consent']);

        $this->post(route('ppdb.portal.register.store'), [
            'name' => 'Calon Pendaftar',
            'email' => 'baru@contoh.test',
            'phone' => '081200000009',
            'password' => 'SekolahKita#Pendaftaran2026',
            'password_confirmation' => 'SekolahKita#Pendaftaran2026',
            'consent' => '1',
            // Honeypot: a bot filling every field trips this.
            'website' => 'https://spam.test',
        ])->assertSessionHasErrors('website');

        $this->assertNull(User::query()->where('email', 'baru@contoh.test')->first());
    }

    public function test_applicant_registration_requires_an_open_period(): void
    {
        $year = AcademicYear::factory()->create();

        AdmissionPeriod::factory()->create([
            'academic_year_id' => $year->getKey(),
            'status' => AdmissionPeriodStatus::Closed,
            'opens_at' => now()->subMonths(2),
            'closes_at' => now()->subMonth(),
            'published_at' => now()->subMonths(2),
        ]);

        $this->get(route('ppdb.portal.register.create'))
            ->assertRedirect(route('ppdb.index'));

        $this->post(route('ppdb.portal.register.store'), [
            'name' => 'Calon Pendaftar',
            'email' => 'calon@contoh.test',
            'phone' => '081200000009',
            'password' => 'SekolahKita#Pendaftaran2026',
            'password_confirmation' => 'SekolahKita#Pendaftaran2026',
            'consent' => '1',
        ])->assertRedirect(route('ppdb.index'));

        $this->assertNull(User::query()->where('email', 'calon@contoh.test')->first());
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    public function test_the_candidate_dashboard_is_never_blank_and_names_what_is_missing(): void
    {
        [$user] = $this->applicant();

        $response = $this->actingAs($user)->get(route('ppdb.portal.dashboard'));

        $response->assertOk()
            ->assertSee('Pendaftaran')
            ->assertSee('Nomor pendaftaran')
            // A draft names the exact gaps rather than showing an empty page.
            ->assertSee('NIK')
            ->assertSee('Orang tua / wali (minimal satu wali)')
            ->assertSee('Akta kelahiran');

        // Nothing is submitted and no reviewer is involved at this point.
        $this->assertSame(0, AuditLog::query()->where('action', 'admission_application.submitted')->count());
    }

    public function test_the_candidate_dashboard_reports_full_completion(): void
    {
        [$user, $application] = $this->applicant();
        $this->completeApplication($application->refresh());

        $this->actingAs($user)->get(route('ppdb.portal.dashboard'))
            ->assertOk()
            ->assertSee('100%')
            ->assertSee('Siap diajukan');
    }

    public function test_the_candidate_dashboard_reports_the_whatsapp_provider_state_honestly(): void
    {
        [$user] = $this->applicant();

        $response = $this->actingAs($user)->get(route('ppdb.portal.dashboard'));

        // No provider is configured in the test environment, and the screen has
        // to say so rather than implying a message was sent.
        $response->assertOk()->assertSee('Belum dikonfigurasi');
    }

    /*
    |--------------------------------------------------------------------------
    | Biodata
    |--------------------------------------------------------------------------
    */

    public function test_the_candidate_saves_biodata_as_a_draft(): void
    {
        [$user, $application] = $this->applicant();

        $this->actingAs($user)->put(route('ppdb.portal.biodata.update', $application), [
            'applicant_name' => 'Nur Aisyah',
            'national_id' => '3273014501900001',
            'birth_place' => 'Bandung',
            'birth_date' => '2013-04-05',
            'gender' => 'P',
            'address' => 'Jl. Mawar No. 12',
            'address_rt' => '003',
            'address_rw' => '005',
            'village' => 'Cibunar',
            'district' => 'Sukajadi',
            'regency' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40131',
            'contact_phone' => '081200000001',
            'contact_whatsapp' => '081200000001',
            'current_school' => 'SDN Cibunar 03',
            'graduation_year' => 2026,
        ])->assertRedirect(route('ppdb.portal.biodata'))->assertSessionHasNoErrors();

        $application->refresh();

        $this->assertSame('Nur Aisyah', $application->applicant_name);
        $this->assertSame('3273014501900001', $application->national_id);
        $this->assertSame('Jawa Barat', $application->province);
        $this->assertSame(AdmissionApplicationStatus::Draft, $application->status);
        $this->assertNull($application->submitted_at, 'Saving a draft must never submit the application.');
    }

    public function test_biodata_is_validated(): void
    {
        [$user, $application] = $this->applicant();

        $this->actingAs($user)->put(route('ppdb.portal.biodata.update', $application), [
            'applicant_name' => '',
            'national_id' => '12345',
            'birth_place' => '',
            'birth_date' => 'tomorrow',
            'gender' => 'X',
            'address' => '',
            'regency' => '',
            'province' => '',
            'contact_phone' => 'bukan',
        ])->assertSessionHasErrors([
            'applicant_name', 'national_id', 'birth_place', 'birth_date', 'gender', 'address', 'regency', 'province', 'contact_phone',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Guardians
    |--------------------------------------------------------------------------
    */

    public function test_the_candidate_adds_a_guardian(): void
    {
        [$user, $application] = $this->applicant();

        $this->actingAs($user)->post(route('ppdb.portal.guardians.store', $application), [
            'legal_name' => 'Budi Santoso',
            'national_id' => '3273011201800001',
            'relationship_type' => 'father',
            'phone' => '081200000002',
            'whatsapp_number' => '081200000002',
            'occupation' => 'Wiraswasta',
            'address' => 'Jl. Mawar No. 12',
            'is_primary_contact' => '1',
            'is_emergency_contact' => '1',
        ])->assertRedirect(route('ppdb.portal.guardians'))->assertSessionHasNoErrors();

        $guardian = $application->guardians()->firstOrFail();

        $this->assertSame('Budi Santoso', $guardian->legal_name);
        $this->assertSame('3273011201800001', $guardian->national_id);
        $this->assertTrue($guardian->is_primary_contact);
        $this->assertSame('081200000002', $guardian->whatsappTarget());
    }

    public function test_a_second_primary_guardian_demotes_the_first(): void
    {
        [$user, $application] = $this->applicant();

        foreach ([['Ayah', 'father'], ['Ibu', 'mother']] as $index => [$name, $relationship]) {
            $this->actingAs($user)->post(route('ppdb.portal.guardians.store', $application), [
                'legal_name' => $name,
                'national_id' => '327301120180000'.$index,
                'relationship_type' => $relationship,
                'phone' => '08120000000'.$index,
                'is_primary_contact' => '1',
            ])->assertSessionHasNoErrors();
        }

        $primary = $application->guardians()->where('is_primary_contact', true)->get();

        $this->assertCount(1, $primary, 'Exactly one guardian may be the primary contact.');
        $this->assertSame('Ibu', $primary->first()->legal_name);
    }

    public function test_editing_a_secondary_guardian_leaves_the_primary_contact_alone(): void
    {
        [$user, $application] = $this->applicant();

        // The primary contact, added first.
        $this->actingAs($user)->post(route('ppdb.portal.guardians.store', $application), [
            'legal_name' => 'Ayah',
            'national_id' => '3273011201800001',
            'relationship_type' => 'father',
            'phone' => '081200000001',
            'is_primary_contact' => '1',
        ])->assertSessionHasNoErrors();

        // A second, secondary guardian.
        $this->actingAs($user)->post(route('ppdb.portal.guardians.store', $application), [
            'legal_name' => 'Ibu',
            'national_id' => '3273014501900002',
            'relationship_type' => 'mother',
            'phone' => '081200000002',
        ])->assertSessionHasNoErrors();

        $secondary = $application->guardians()->where('legal_name', 'Ibu')->firstOrFail();
        $this->assertFalse((bool) $secondary->is_primary_contact);

        // An unrelated edit to the secondary guardian: correct the occupation, and
        // leave the primary checkbox exactly as the form presented it.
        $this->actingAs($user)
            ->put(route('ppdb.portal.guardians.update', [$application, $secondary]), [
                'legal_name' => 'Ibu',
                'national_id' => '3273014501900002',
                'relationship_type' => 'mother',
                'phone' => '081200000002',
                'occupation' => 'Guru',
            ])
            ->assertRedirect(route('ppdb.portal.guardians'))
            ->assertSessionHasNoErrors();

        $primary = $application->guardians()->where('is_primary_contact', true)->get();

        $this->assertCount(
            1,
            $primary,
            'Editing an unrelated field on a secondary guardian must not clear the primary contact; '
                .'doing so left the application with no primary contact and made it unsubmittable.'
        );
        $this->assertSame('Ayah', $primary->first()->legal_name);
        $this->assertSame('Guru', $application->guardians()->where('legal_name', 'Ibu')->firstOrFail()->occupation);
    }

    public function test_promoting_a_secondary_guardian_demotes_the_previous_primary(): void
    {
        [$user, $application] = $this->applicant();

        foreach ([['Ayah', 'father', '1'], ['Ibu', 'mother', '0']] as $index => [$name, $relationship, $primary]) {
            $this->actingAs($user)->post(route('ppdb.portal.guardians.store', $application), [
                'legal_name' => $name,
                'national_id' => '32730112018000'.$index.'1',
                'relationship_type' => $relationship,
                'phone' => '08120000000'.$index,
                'is_primary_contact' => $primary,
            ])->assertSessionHasNoErrors();
        }

        $secondary = $application->guardians()->where('legal_name', 'Ibu')->firstOrFail();

        // Now the candidate ticks the primary box on the secondary guardian.
        $this->actingAs($user)
            ->put(route('ppdb.portal.guardians.update', [$application, $secondary]), [
                'legal_name' => 'Ibu',
                'national_id' => '3273011201800011',
                'relationship_type' => 'mother',
                'phone' => '081200000001',
                'is_primary_contact' => '1',
            ])
            ->assertSessionHasNoErrors();

        $primary = $application->guardians()->where('is_primary_contact', true)->get();

        $this->assertCount(1, $primary);
        $this->assertSame('Ibu', $primary->first()->legal_name);
    }

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */

    public function test_the_candidate_uploads_a_private_document(): void
    {
        Notification::fake();
        Storage::fake('local');

        [$user, $application] = $this->applicant();

        $this->actingAs($user)->post(route('ppdb.portal.documents.store', $application), [
            'document_type' => AdmissionDocumentType::BirthCertificate->value,
            'document' => $this->fakePdf('akta.pdf'),
        ])->assertRedirect(route('ppdb.portal.documents'))->assertSessionHasNoErrors();

        $document = $application->documents()->firstOrFail();

        $this->assertSame(AdmissionDocumentType::BirthCertificate, $document->document_type);
        $this->assertSame(AdmissionDocumentVerificationStatus::Pending, $document->verification_status);
        $this->assertSame(1, $document->version);

        // Private all the way: a private disk, a random key, and no public
        // object key at all.
        $media = $document->mediaAsset;
        $this->assertSame('local', $media->private_disk);
        $this->assertNull($media->public_disk);
        $this->assertNull($media->public_object_key);
        Storage::disk('local')->assertExists($media->private_object_key);
    }

    public function test_document_upload_rejects_the_wrong_file_type(): void
    {
        Storage::fake('local');

        [$user, $application] = $this->applicant();

        $this->actingAs($user)->post(route('ppdb.portal.documents.store', $application), [
            'document_type' => AdmissionDocumentType::BirthCertificate->value,
            'document' => UploadedFile::fake()->create('akta.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors('document');

        $this->assertSame(0, $application->documents()->count());
    }

    public function test_an_already_reviewed_document_cannot_be_swapped_by_the_candidate(): void
    {
        Storage::fake('local');

        [$user, $application] = $this->applicant();
        $this->completeApplication($application->refresh());

        $document = $application->documents()
            ->where('document_type', AdmissionDocumentType::BirthCertificate->value)
            ->firstOrFail();
        $document->forceFill(['verification_status' => AdmissionDocumentVerificationStatus::Verified])->save();

        $this->actingAs($user)->put(route('ppdb.portal.documents.replace', $document), [
            'document' => $this->fakePdf('akta-baru.pdf'),
        ])->assertSessionHasErrors('document');

        $this->assertSame(1, $application->documents()->where('document_type', AdmissionDocumentType::BirthCertificate->value)->count());
    }

    public function test_the_candidate_can_download_their_own_document(): void
    {
        Storage::fake('local');

        [$user, $application] = $this->applicant();
        $this->completeApplication($application->refresh());

        $document = $application->documents()->firstOrFail();
        $media = $document->mediaAsset;
        Storage::disk('local')->put($media->private_object_key, '%PDF-1.4 dummy');

        $response = $this->actingAs($user)->get(route('ppdb.portal.documents.download', $document));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    /*
    |--------------------------------------------------------------------------
    | Review and submission
    |--------------------------------------------------------------------------
    */

    public function test_an_incomplete_application_cannot_be_submitted(): void
    {
        [$user, $application] = $this->applicant();

        $this->actingAs($user)->get(route('ppdb.portal.review'))
            ->assertOk()
            ->assertSee('Belum dapat diajukan');

        $this->actingAs($user)->post(route('ppdb.portal.submit'))
            ->assertSessionHasErrors('consent');

        $this->actingAs($user)->post(route('ppdb.portal.submit'), ['consent' => '1'])
            ->assertSessionHasErrors('status');

        $this->assertSame(AdmissionApplicationStatus::Draft, $application->refresh()->status);
        $this->assertNull($application->submitted_at);
    }

    public function test_a_complete_application_is_submitted_locked_and_audited(): void
    {
        Notification::fake();

        [$user, $application] = $this->applicant();
        $this->completeApplication($application->refresh());

        $this->actingAs($user)->post(route('ppdb.portal.submit'), ['consent' => '1'])
            ->assertRedirect(route('ppdb.portal.status'))
            ->assertSessionHasNoErrors();

        $application->refresh();

        $this->assertSame(AdmissionApplicationStatus::Submitted, $application->status);
        $this->assertNotNull($application->submitted_at);
        $this->assertNotNull($application->locked_at);
        $this->assertNotNull($application->consent_accepted_at, 'Consent is recorded at the moment of submission.');
        $this->assertFalse($application->isPortalEditable(), 'A submitted application locks its editable sections.');

        $this->assertSame(1, AuditLog::query()->where('action', 'admission_application.submitted')->count());

        $this->assertTrue(
            $application->statusHistories()
                ->where('to_status', AdmissionApplicationStatus::Submitted)
                ->where('actor_user_id', $user->getKey())
                ->exists(),
            'Submission must appear on the applicant-visible status timeline.'
        );

        // The administrator queue is notified, and the candidate gets an in-app
        // copy, without any WhatsApp provider being available.
        Notification::assertSentTo($user, CandidatePortalNotification::class);
    }

    public function test_a_submitted_application_cannot_be_submitted_twice(): void
    {
        Notification::fake();

        [$user, $application] = $this->applicant();
        $this->completeApplication($application->refresh());

        $this->actingAs($user)->post(route('ppdb.portal.submit'), ['consent' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('ppdb.portal.submit'), ['consent' => '1'])->assertForbidden();

        $this->assertSame(1, $application->statusHistories()->where('to_status', AdmissionApplicationStatus::Submitted)->count());
    }

    public function test_a_decided_application_is_never_dragged_back_into_the_queue(): void
    {
        Notification::fake();

        [$user, $application] = $this->applicant();
        $this->completeApplication($application->refresh());

        /*
         * The candidate's browser still holds a draft model while an administrator
         * decides the application. The service authorises against that model and
         * then re-reads the row under a lock, so the decision has to be re-checked
         * there — otherwise a stale submit would reset a decided application back
         * to "submitted" and, worse, wipe a completed conversion.
         */
        $reviewer = User::factory()->administrator()->create();
        $application->forceFill([
            'status' => AdmissionApplicationStatus::Accepted,
            'conversion_status' => AdmissionConversionStatus::Completed,
            'assigned_reviewer_user_id' => $reviewer->getKey(),
            'submitted_at' => now()->subDay(),
        ])->save();

        // A stale draft instance, exactly what a browser tab would still hold.
        $stale = AdmissionApplication::query()->findOrFail($application->getKey());
        $this->assertSame(AdmissionApplicationStatus::Accepted, $stale->status);

        // The refusal may come from the policy (the application is no longer a
        // draft) or from the re-check on the locked row; both exist, and this test
        // asserts the guarantee rather than which layer produced it.
        try {
            app(ApplicantPortalService::class)
                ->submit($user, $stale, Request::create('/', 'POST', ['consent' => '1']));
        } catch (AuthorizationException|ValidationException) {
            // Expected: a decided application is not submittable.
        }

        $application->refresh();

        $this->assertSame(
            AdmissionApplicationStatus::Accepted,
            $application->status,
            'A decided application must stay decided.'
        );
        $this->assertSame(
            AdmissionConversionStatus::Completed,
            $application->conversion_status,
            'A completed conversion must never be reset by a late submit.'
        );
        $this->assertSame(0, $application->statusHistories()->where('to_status', AdmissionApplicationStatus::Submitted)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Revision loop
    |--------------------------------------------------------------------------
    */

    public function test_a_document_sent_back_is_replaceable_and_returns_to_pending(): void
    {
        Notification::fake();
        Storage::fake('local');

        [$user, $application] = $this->applicant();
        $this->completeApplication($application->refresh());

        // A complete application is submitted, a reviewer takes it, and one
        // document is sent back. The application itself stays under review.
        $this->actingAs($user)->post(route('ppdb.portal.submit'), ['consent' => '1'])->assertSessionHasNoErrors();

        $reviewer = User::factory()->administrator()->create();
        $application->forceFill(['assigned_reviewer_user_id' => $reviewer->getKey()])->save();

        $this->actingAs($reviewer)
            ->post(route('admin.ppdb.applications.start-review', $application))
            ->assertSessionHasNoErrors();

        $document = $application->refresh()->documents()
            ->where('document_type', AdmissionDocumentType::GuardianIdentity->value)
            ->firstOrFail();

        $this->actingAs($reviewer)
            ->patch(route('admin.ppdb.documents.request-revision', $document), [
                'reviewer_note' => 'Dokumen tidak terbaca. Silakan unggah ulang dengan foto lebih terang.',
            ])
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(AdmissionDocumentVerificationStatus::NeedsRevision, $document->verification_status);
        $this->assertStringContainsString('tidak terbaca', (string) $document->reviewer_note);

        // The applicant sees the reason immediately on their own screens.
        $this->actingAs($user)->get(route('ppdb.portal.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('ppdb.portal.status'))->assertOk()->assertSee('Dokumen perlu diperbaiki');
        $this->actingAs($user)->get(route('ppdb.portal.documents'))->assertOk()->assertSee('tidak terbaca');

        // Only the returned category is replaced, and the application does NOT go
        // back into a revise-everything state: a document-level correction is a
        // single-document fix, which is the point of the revision loop.
        $this->assertSame(AdmissionApplicationStatus::UnderReview, $application->refresh()->status);

        $this->actingAs($user)->put(route('ppdb.portal.documents.replace', $document), [
            'document' => $this->fakePdf('ktp-wali.pdf'),
        ])->assertRedirect(route('ppdb.portal.documents'))->assertSessionHasNoErrors();

        $replacement = $application->documents()
            ->where('document_type', AdmissionDocumentType::GuardianIdentity->value)
            ->orderByDesc('version')
            ->firstOrFail();

        $this->assertSame(2, (int) $replacement->version);
        $this->assertSame(AdmissionDocumentVerificationStatus::Pending, $replacement->verification_status);
        $this->assertSame(
            AdmissionDocumentVerificationStatus::Superseded,
            $document->refresh()->verification_status,
            'The previous version is superseded, not deleted.'
        );
        $this->assertSame(AdmissionApplicationStatus::UnderReview, $application->refresh()->status);

        $this->assertSame(1, $document->reviews()->count(), 'The document review event is kept as history.');
    }

    public function test_requesting_a_revision_without_a_reason_is_refused(): void
    {
        [$user, $application] = $this->applicant();
        $this->completeApplication($application->refresh());

        $reviewer = User::factory()->administrator()->create();
        $application->forceFill(['assigned_reviewer_user_id' => $reviewer->getKey()])->save();
        $application->forceFill(['status' => AdmissionApplicationStatus::UnderReview])->save();

        $document = $application->documents()->firstOrFail();

        $this->actingAs($reviewer)
            ->patch(route('admin.ppdb.documents.request-revision', $document), ['reviewer_note' => ''])
            ->assertSessionHasErrors('reviewer_note');
    }

    /**
     * A minimal but structurally real PDF.
     *
     * PrivateDocumentService inspects the bytes, not the declared MIME type, so
     * an UploadedFile::fake()->create() blob of filler is correctly refused.
     */
    private function fakePdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n",
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Access isolation
    |--------------------------------------------------------------------------
    */

    public function test_a_candidate_cannot_reach_another_candidates_application_or_documents(): void
    {
        Storage::fake('local');

        [$user] = $this->applicant();
        [$otherUser, $otherApplication] = $this->applicant();
        $this->completeApplication($otherApplication->refresh());

        $otherDocument = $otherApplication->documents()->firstOrFail();

        $this->actingAs($user)->get(route('ppdb.portal.biodata'))->assertOk();
        $this->actingAs($user)->get(route('ppdb.portal.guardians'))->assertOk();
        $this->actingAs($user)->get(route('ppdb.portal.documents'))->assertOk();

        // The candidate's own application is the only one any of these URLs can
        // resolve to, because none of them carries an application id at all.
        $this->actingAs($user)->get(route('ppdb.portal.documents.download', $otherDocument))->assertForbidden();

        $this->actingAs($user)
            ->put(route('ppdb.portal.biodata.update', $otherApplication), [
                'applicant_name' => 'Diretas',
                'national_id' => '3273014501900001',
                'birth_place' => 'Bandung',
                'birth_date' => '2013-04-05',
                'gender' => 'P',
                'address' => 'Jl. Mawar No. 12',
                'regency' => 'Bandung',
                'province' => 'Jawa Barat',
                'contact_phone' => '081200000001',
            ])->assertForbidden();

        $this->actingAs($user)
            ->post(route('ppdb.portal.guardians.store', $otherApplication), [
                'legal_name' => 'Penyusup',
                'national_id' => '3273011201800009',
                'relationship_type' => 'father',
                'phone' => '081200000009',
            ])->assertForbidden();

        $this->assertSame('Nur Aisyah', $otherApplication->refresh()->applicant_name);
        $this->assertSame(1, $otherApplication->guardians()->count());
        $this->assertNotSame($user->getKey(), $otherUser->getKey());
    }

    public function test_a_candidate_cannot_reach_teacher_student_or_administrator_areas(): void
    {
        [$user] = $this->applicant();

        $this->actingAs($user)->get(route('dashboard.guru'))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard.siswa'))->assertForbidden();
        $this->actingAs($user)->get(route('dashboard.administrator'))->assertForbidden();
        $this->actingAs($user)->get(route('guru.profil'))->assertForbidden();
        $this->actingAs($user)->get(route('guru.absensi.index'))->assertForbidden();
        $this->actingAs($user)->get(route('siswa.profil'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.ppdb.applications.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.class-schedules.index'))->assertForbidden();
    }

    public function test_a_guest_cannot_reach_the_portal(): void
    {
        $this->get(route('ppdb.portal.dashboard'))->assertRedirect(route('login'));
        $this->get(route('ppdb.portal.documents'))->assertRedirect(route('login'));
    }

    public function test_the_portal_navigation_is_rendered_for_a_candidate(): void
    {
        [$user] = $this->applicant();

        $this->actingAs($user)->get(route('ppdb.portal.dashboard'))
            ->assertOk()
            ->assertSee('Biodata siswa')
            ->assertSee('Orang tua / wali')
            ->assertSee('Dokumen pendukung')
            ->assertSee('Review data')
            ->assertSee('Ajukan pendaftaran')
            ->assertSee('Status pendaftaran');
    }
}
