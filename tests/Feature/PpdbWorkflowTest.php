<?php

namespace Tests\Feature;

use App\Enums\AdmissionApplicationStatus;
use App\Enums\AdmissionConversionStatus;
use App\Enums\AdmissionDocumentVerificationStatus;
use App\Enums\AdmissionPeriodStatus;
use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\MediaAssetKind;
use App\Enums\MediaProcessingStatus;
use App\Enums\MediaScanStatus;
use App\Enums\MediaVisibility;
use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPeriod;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Services\AdmissionApplicationService;
use App\Services\AdmissionPeriodService;
use App\Services\PrivateDocumentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The administrator PPDB workflow end to end: an open period, applications,
 * the review queue, decisions and conversion into a student record.
 */
class PpdbWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    private function academicYear(): AcademicYear
    {
        return AcademicYear::factory()->active()->create();
    }

    private function openPeriod(?AcademicYear $year = null): AdmissionPeriod
    {
        $year ??= $this->academicYear();

        return AdmissionPeriod::query()->create([
            'academic_year_id' => $year->getKey(),
            'name' => 'PPDB Uji',
            'slug' => 'ppdb-uji-'.$year->code,
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addMonth(),
            'status' => AdmissionPeriodStatus::Open,
            'quota' => 10,
            'published_at' => now()->subDay(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $guardians
     * @return array{application: AdmissionApplication, token: string}
     */
    private function submit(AdmissionPeriod $period, array $overrides = [], array $guardians = []): array
    {
        return app(AdmissionApplicationService::class)->submit($period, array_merge([
            'applicant_name' => 'Calon Siswa Uji',
            'birth_date' => now()->subYears(13)->toDateString(),
            'birth_place' => 'Bandung',
            'gender' => 'P',
            'contact_email' => 'calon@contoh.test',
            'contact_phone' => '0812-0000-0001',
            'address' => 'Jl. Uji No. 1',
            'current_school' => 'SDN Uji',
            'requested_grade_level' => 7,
            'consent' => ['accepted' => true],
            'guardians' => $guardians === [] ? [[
                'legal_name' => 'Wali Calon',
                'relationship_type' => 'father',
                'phone' => '0812-0000-0002',
                'is_primary_contact' => true,
            ]] : $guardians,
        ], $overrides));
    }

    /*
    |--------------------------------------------------------------------------
    | The empty dropdown on the period form
    |--------------------------------------------------------------------------
    */

    public function test_the_period_form_explains_the_empty_dropdown_when_no_academic_year_exists(): void
    {
        $this->actingAs($this->admin())->get(route('admin.ppdb.periods.create'))
            ->assertOk()
            ->assertSee('Tahun ajaran belum tersedia')
            ->assertSee(route('admin.academic-years.create'));

        $this->actingAs($this->admin())->get(route('admin.ppdb.periods.index'))
            ->assertOk()
            ->assertSee('Belum ada periode PPDB');
    }

    public function test_the_period_form_offers_academic_years_once_one_exists(): void
    {
        $year = $this->academicYear();

        $this->actingAs($this->admin())->get(route('admin.ppdb.periods.create'))
            ->assertOk()
            ->assertDontSee('Tahun ajaran belum tersedia')
            ->assertSee($year->label);
    }

    public function test_administrator_creates_a_period_and_opens_and_closes_it(): void
    {
        $admin = $this->admin();
        $year = $this->academicYear();

        $this->actingAs($admin)->post(route('admin.ppdb.periods.store'), [
            'academic_year_id' => $year->getKey(),
            'name' => 'PPDB 2026/2027',
            'slug' => 'ppdb-2026-2027',
            'opens_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'closes_at' => now()->addMonths(2)->format('Y-m-d\TH:i'),
            'quota' => 100,
        ])->assertRedirect(route('admin.ppdb.periods.index'))->assertSessionHasNoErrors();

        $period = AdmissionPeriod::query()->where('slug', 'ppdb-2026-2027')->firstOrFail();

        $this->assertSame(AdmissionPeriodStatus::Draft, $period->status);
        $this->assertSame($year->getKey(), $period->academic_year_id);

        $this->actingAs($admin)->get(route('admin.ppdb.periods.index'))->assertOk()->assertSee('PPDB 2026/2027');
        $this->actingAs($admin)->get(route('admin.ppdb.periods.show', $period))->assertOk()->assertSee('PPDB 2026/2027');

        $this->actingAs($admin)->post(route('admin.ppdb.periods.open', $period))->assertRedirect();
        $this->assertSame(AdmissionPeriodStatus::Open, $period->refresh()->status);

        $this->actingAs($admin)->post(route('admin.ppdb.periods.close', $period))->assertRedirect();
        $this->assertSame(AdmissionPeriodStatus::Closed, $period->refresh()->status);

        $this->actingAs($admin)->post(route('admin.ppdb.periods.archive', $period))->assertRedirect();
        $this->assertSame(AdmissionPeriodStatus::Archived, $period->refresh()->status);
    }

    public function test_a_period_rejects_a_closing_date_before_the_opening_date(): void
    {
        $year = $this->academicYear();

        $this->actingAs($this->admin())->post(route('admin.ppdb.periods.store'), [
            'academic_year_id' => $year->getKey(),
            'name' => 'Salah',
            'slug' => 'ppdb-salah',
            'opens_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'closes_at' => now()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('closes_at');
    }

    public function test_a_period_cannot_be_saved_without_an_academic_year(): void
    {
        $this->actingAs($this->admin())->post(route('admin.ppdb.periods.store'), [
            'academic_year_id' => '',
            'name' => 'Tanpa Tahun Ajaran',
            'slug' => 'tanpa-tahun',
            'opens_at' => now()->format('Y-m-d\TH:i'),
            'closes_at' => now()->addMonth()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('academic_year_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Applications, review and conversion
    |--------------------------------------------------------------------------
    */

    public function test_the_applications_list_searches_and_filters(): void
    {
        $admin = $this->admin();
        $period = $this->openPeriod();
        ['application' => $target] = $this->submit($period, ['applicant_name' => 'Bunga Kacun']);
        $this->submit($period, ['applicant_name' => 'Laras Wangi', 'contact_email' => 'laras@contoh.test']);

        $this->actingAs($admin)->get(route('admin.ppdb.applications.index'))
            ->assertOk()
            ->assertSee($target->registration_number);

        $this->actingAs($admin)->get(route('admin.ppdb.applications.index', ['search' => $target->registration_number]))
            ->assertOk()
            ->assertSee($target->registration_number);

        $this->actingAs($admin)->get(route('admin.ppdb.applications.index', ['status' => AdmissionApplicationStatus::Submitted->value]))
            ->assertOk()
            ->assertSee($target->registration_number);
    }

    public function test_the_review_queue_is_reachable_and_summarised(): void
    {
        $admin = $this->admin();
        $period = $this->openPeriod();
        ['application' => $application] = $this->submit($period);

        $this->actingAs($admin)->get(route('admin.ppdb.review.index'))
            ->assertOk()
            ->assertSee('Antrean review')
            ->assertSee($application->registration_number)
            ->assertSee('Menunggu keputusan');
    }

    public function test_the_full_review_and_conversion_lifecycle(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = $this->admin();
        $year = $this->academicYear();
        $period = $this->openPeriod($year);
        $class = ClassModel::factory()->create([
            'academic_year_id' => $year->getKey(),
            'status' => ClassStatus::Active,
        ]);

        ['application' => $application] = $this->submit($period);

        $this->assertSame(AdmissionApplicationStatus::Submitted, $application->status);
        $this->assertSame(AdmissionConversionStatus::NotStarted, $application->conversion_status);

        // Before a reviewer is assigned the applicant data is withheld, which is
        // itself part of the privacy contract.
        $this->actingAs($admin)->get(route('admin.ppdb.applications.show', $application))
            ->assertOk()
            ->assertSee($application->registration_number)
            ->assertDontSee('Calon Siswa Uji');

        $this->actingAs($admin)->post(route('admin.ppdb.applications.reviewer.assign', $application), [
            'reviewer_user_id' => $admin->getKey(),
        ])->assertRedirect();
        $this->assertSame($admin->getKey(), $application->refresh()->assigned_reviewer_user_id);

        $this->actingAs($admin)->get(route('admin.ppdb.applications.show', $application))
            ->assertOk()
            ->assertSee('Calon Siswa Uji')
            ->assertSee('Wali Calon');

        $this->actingAs($admin)->post(route('admin.ppdb.applications.start-review', $application))->assertRedirect();
        $this->assertSame(AdmissionApplicationStatus::UnderReview, $application->refresh()->status);

        // A verified document is required before conversion, and a document can
        // only be added while the application is under review.
        $this->actingAs($admin)->post(route('admin.ppdb.applications.documents.store', $application), [
            'document_type' => 'identity',
            // A real minimal PDF, because PrivateDocumentService inspects the
            // bytes rather than trusting the declared type.
            'document' => UploadedFile::fake()->createWithContent('identitas.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF\n"),
        ])->assertRedirect();

        $stored = $application->documents()->firstOrFail();
        $this->assertSame(AdmissionDocumentVerificationStatus::Pending, $stored->verification_status);
        $media = $stored->mediaAsset;

        $this->assertTrue(app(PrivateDocumentService::class)->inspect($media));

        $media->forceFill([
            'media_kind' => MediaAssetKind::Document,
            'processing_status' => MediaProcessingStatus::Ready,
            'scan_status' => MediaScanStatus::Clean,
            'visibility' => MediaVisibility::Private,
        ])->save();

        $this->actingAs($admin)->patch(route('admin.ppdb.documents.update-status', $stored), [
            'status' => AdmissionDocumentVerificationStatus::Verified->value,
        ])->assertRedirect();
        $this->assertSame(AdmissionDocumentVerificationStatus::Verified, $stored->refresh()->verification_status);
        $this->assertNotNull($media->refresh()->private_object_key);

        $this->actingAs($admin)->post(route('admin.ppdb.applications.decide', $application), [
            'decision' => 'accept',
            'target_class_id' => $class->getKey(),
        ])->assertRedirect();
        $this->assertSame(AdmissionApplicationStatus::Accepted, $application->refresh()->status);

        // Conversion creates the student, the guardian link and the enrollment.
        $this->actingAs($admin)->post(route('admin.ppdb.applications.convert', $application))->assertRedirect();

        $application->refresh();
        $this->assertSame(AdmissionConversionStatus::Completed, $application->conversion_status);
        $this->assertNotNull($application->converted_student_id);
        $this->assertNotNull($application->converted_enrollment_id);

        $student = $application->convertedStudent;
        $this->assertSame('Calon Siswa Uji', $student->legal_name);
        $this->assertNotNull($student->student_number);
        $this->assertNull($student->user_id, 'Conversion must not create an internal login account.');

        $enrollment = $application->convertedEnrollment;
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertTrue($enrollment->class->is($class));
        $this->assertSame($year->getKey(), $enrollment->academic_year_id);

        $this->assertSame(1, StudentGuardian::query()->where('student_id', $student->getKey())->count());
        $this->assertSame(1, Enrollment::query()->where('student_id', $student->getKey())->count());

        // The applicant history survives the conversion.
        $this->assertSame(AdmissionApplicationStatus::Accepted, $application->status);
        $this->assertGreaterThanOrEqual(3, $application->statusHistories()->count());

        $this->actingAs($admin)->get(route('admin.ppdb.applications.show', $application))
            ->assertOk()
            ->assertSee('Konversi selesai');
    }

    public function test_a_revision_request_moves_the_application_back_to_the_applicant(): void
    {
        $admin = $this->admin();
        $period = $this->openPeriod();
        ['application' => $application] = $this->submit($period);

        $this->actingAs($admin)->post(route('admin.ppdb.applications.reviewer.assign', $application), [
            'reviewer_user_id' => $admin->getKey(),
        ]);
        $this->actingAs($admin)->post(route('admin.ppdb.applications.start-review', $application));

        $this->actingAs($admin)->post(route('admin.ppdb.applications.request-revision', $application), [
            'public_message' => 'Mohon lengkapi dokumen identitas.',
        ])->assertRedirect();

        $this->assertSame(AdmissionApplicationStatus::RevisionRequested, $application->refresh()->status);
    }

    public function test_conversion_requires_at_least_one_verified_document(): void
    {
        $admin = $this->admin();
        $year = $this->academicYear();
        $period = $this->openPeriod($year);
        $class = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active]);
        ['application' => $application] = $this->submit($period);

        $this->actingAs($admin)->post(route('admin.ppdb.applications.reviewer.assign', $application), [
            'reviewer_user_id' => $admin->getKey(),
        ]);
        $this->actingAs($admin)->post(route('admin.ppdb.applications.start-review', $application));
        $this->actingAs($admin)->post(route('admin.ppdb.applications.decide', $application), [
            'decision' => 'accept',
            'target_class_id' => $class->getKey(),
        ]);

        $this->assertSame(AdmissionApplicationStatus::Accepted, $application->refresh()->status);

        $this->actingAs($admin)
            ->from(route('admin.ppdb.applications.show', $application))
            ->post(route('admin.ppdb.applications.convert', $application))
            ->assertSessionHasErrors('documents');

        $this->assertNull($application->refresh()->converted_student_id);
        $this->assertSame(0, Student::query()->count());
    }

    public function test_conversion_is_refused_for_a_non_accepted_application(): void
    {
        $admin = $this->admin();
        $period = $this->openPeriod();
        ['application' => $application] = $this->submit($period);

        // The policy alone already refuses an application with no reviewer.
        $this->actingAs($admin)
            ->from(route('admin.ppdb.applications.show', $application))
            ->post(route('admin.ppdb.applications.convert', $application))
            ->assertForbidden();

        $this->actingAs($admin)->post(route('admin.ppdb.applications.reviewer.assign', $application), [
            'reviewer_user_id' => $admin->getKey(),
        ]);

        // With a reviewer assigned the workflow rule is what refuses it.
        $this->actingAs($admin)
            ->from(route('admin.ppdb.applications.show', $application))
            ->post(route('admin.ppdb.applications.convert', $application))
            ->assertSessionHasErrors('status');

        $this->assertNull($application->refresh()->converted_student_id);
        $this->assertSame(0, Student::query()->count());
    }

    public function test_a_teacher_or_student_cannot_touch_the_ppdb_admin_area(): void
    {
        $period = $this->openPeriod();
        ['application' => $application] = $this->submit($period);

        foreach ([User::factory()->guru()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.ppdb.periods.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.ppdb.periods.create'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.ppdb.periods.show', $period))->assertForbidden();
            $this->actingAs($user)->get(route('admin.ppdb.applications.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.ppdb.applications.show', $application))->assertForbidden();
            $this->actingAs($user)->get(route('admin.ppdb.review.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.ppdb.periods.store'), [])->assertForbidden();
            $this->actingAs($user)->post(route('admin.ppdb.applications.convert', $application))->assertForbidden();
        }
    }

    public function test_the_published_ppdb_page_reflects_the_open_period(): void
    {
        $period = $this->openPeriod();

        $this->get(route('ppdb.index'))
            ->assertOk()
            ->assertSee($period->name)
            ->assertSee('Dibuka');

        $service = app(AdmissionPeriodService::class);
        $service->close(User::factory()->administrator()->create(), $period);

        // A closed period stays published as an announcement, but it is no
        // longer the open window.
        $this->get(route('ppdb.index'))
            ->assertOk()
            ->assertSee($period->name)
            ->assertSee('Ditutup');

        $service->archive(User::factory()->administrator()->create(), $period);

        $this->get(route('ppdb.index'))->assertOk()->assertDontSee($period->name);
    }
}
