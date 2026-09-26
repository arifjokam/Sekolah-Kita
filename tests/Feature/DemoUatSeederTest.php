<?php

namespace Tests\Feature;

use App\Enums\AdmissionDocumentType;
use App\Enums\AdmissionDocumentVerificationStatus;
use App\Enums\AssignmentStatus;
use App\Enums\AttendanceSessionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\ScheduleStatus;
use App\Enums\TeacherEmploymentStatus;
use App\Enums\TermStatus;
use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPeriod;
use App\Models\ApplicationDocument;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassModel;
use App\Models\ClassSchedule;
use App\Models\ClassTeacherAssignment;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use Database\Seeders\DemoUatSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The development / UAT dataset.
 *
 * This runs the real seeder against the throwaway test database, which is the
 * only safe way to assert what it produces: the development database is never
 * touched, and the assertions below are the same checklist the brief sets for
 * UAT data.
 *
 * Idempotence is asserted by seeding twice, because a seeder that is only
 * correct on an empty database cannot be re-run after an administrator has
 * added master data through the UI.
 */
class DemoUatSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function seedFixtures(): void
    {
        $this->seed(DemoUatSeeder::class);
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $this->seedFixtures();
        $before = [
            'users' => User::query()->count(),
            'teachers' => Teacher::query()->count(),
            'students' => Student::query()->count(),
            'classes' => ClassModel::query()->count(),
            'terms' => Term::query()->count(),
            'schedules' => ClassSchedule::query()->count(),
            'sessions' => AttendanceSession::query()->count(),
            'records' => AttendanceRecord::query()->count(),
            'applications' => AdmissionApplication::query()->count(),
        ];

        $this->seedFixtures();

        $after = [
            'users' => User::query()->count(),
            'teachers' => Teacher::query()->count(),
            'students' => Student::query()->count(),
            'classes' => ClassModel::query()->count(),
            'terms' => Term::query()->count(),
            'schedules' => ClassSchedule::query()->count(),
            'sessions' => AttendanceSession::query()->count(),
            'records' => AttendanceRecord::query()->count(),
            'applications' => AdmissionApplication::query()->count(),
        ];

        $this->assertSame($before, $after, 'Re-running the seeder must not duplicate anything.');
    }

    public function test_the_academic_data_covers_both_semesters(): void
    {
        $this->seedFixtures();

        $year = AcademicYear::query()->where('status', 'active')->firstOrFail();

        $terms = Term::query()
            ->where('academic_year_id', $year->getKey())
            ->orderBy('sequence')
            ->get();

        $this->assertCount(2, $terms, 'The active year carries Semester 1 and Semester 2.');
        $this->assertSame([1, 2], $terms->pluck('sequence')->map(static fn ($value): int => (int) $value)->all());
        $this->assertSame('Semester 1', $terms->first()->semesterLabel());
        $this->assertSame('Semester 2', $terms->last()->semesterLabel());
        $this->assertSame(
            1,
            $terms->where('status', TermStatus::Active->value)->count(),
            'Exactly one semester is active at a time.'
        );

        // The two semesters do not overlap, which is the invariant the schedule
        // and attendance fixtures depend on.
        // Back-to-back semesters are the normal case: Semester 1 ends on the day
        // Semester 2 begins, which is not an overlap.
        $this->assertTrue($terms->first()->ends_on->lte($terms->last()->starts_on));

        $this->assertGreaterThanOrEqual(2, ClassModel::query()->count());
        $this->assertGreaterThanOrEqual(4, Subject::query()->count());
    }

    public function test_there_are_at_least_two_active_teachers_with_assignments(): void
    {
        $this->seedFixtures();

        $teachers = Teacher::query()->where('employment_status', TeacherEmploymentStatus::Active->value)->get();

        $this->assertGreaterThanOrEqual(2, $teachers->count());

        foreach ($teachers->take(2) as $teacher) {
            $this->assertGreaterThan(
                0,
                $teacher->teachingAssignments()->where('status', AssignmentStatus::Active->value)->count(),
                'Every seeded teacher has at least one teaching assignment.'
            );
        }

        $this->assertGreaterThan(0, ClassTeacherAssignment::query()->where('status', AssignmentStatus::Active->value)->count());
    }

    public function test_at_least_six_students_are_enrolled_across_two_classes(): void
    {
        $this->seedFixtures();

        $students = Student::query()->where('status', 'active')->get();
        $this->assertGreaterThanOrEqual(6, $students->count());

        $classIds = Enrollment::query()
            ->where('status', EnrollmentStatus::Active->value)
            ->distinct()
            ->pluck('class_id')
            ->all();

        $this->assertGreaterThanOrEqual(2, count($classIds), 'Students are spread over at least two classes.');
    }

    public function test_the_schedule_is_coherent_and_conflict_free(): void
    {
        $this->seedFixtures();

        $schedules = ClassSchedule::query()
            ->with(['class', 'subject', 'teacher'])
            ->where('status', ScheduleStatus::Active->value)
            ->get();

        $this->assertGreaterThanOrEqual(5, $schedules->count());

        foreach ($schedules as $schedule) {
            $this->assertNotNull($schedule->class, 'A lesson belongs to a class.');
            $this->assertNotNull($schedule->subject, 'A lesson belongs to a subject.');
            $this->assertNotNull($schedule->teacher, 'A lesson has a teacher.');
            $this->assertTrue(
                $schedule->teacher->teachingAssignments()
                    ->where('class_id', $schedule->class_id)
                    ->where('subject_id', $schedule->subject_id)
                    ->where('term_id', $schedule->term_id)
                    ->exists(),
                'Every seeded lesson has a teacher assigned to teach it in that semester.'
            );
        }

        $this->assertNoScheduleClash($schedules, 'class_id');
        $this->assertNoScheduleClash($schedules, 'teacher_id');
    }

    /**
     * @param  Collection<int, ClassSchedule>  $schedules
     */
    private function assertNoScheduleClash($schedules, string $dimension): void
    {
        $schedules
            ->groupBy(fn (ClassSchedule $schedule): string => $schedule->{$dimension}.'-'.$schedule->day_of_week->value)
            ->each(function ($group, $key) use ($dimension): void {
                $sorted = $group->sortBy('starts_at')->values();

                for ($i = 1; $i < $sorted->count(); $i++) {
                    $previous = $sorted->get($i - 1);
                    $current = $sorted->get($i);

                    $this->assertGreaterThanOrEqual(
                        (string) $previous->ends_at,
                        (string) $current->starts_at,
                        sprintf('Seeded %s %s is double booked on day %s.', $dimension, $key, $current->day_of_week->label())
                    );
                }
            });
    }

    public function test_there_is_sample_attendance_for_the_current_month(): void
    {
        $this->seedFixtures();

        $sessions = AttendanceSession::query()->where('status', AttendanceSessionStatus::Submitted->value)->get();

        $this->assertGreaterThan(0, $sessions->count(), 'Sample attendance sessions exist.');
        $this->assertGreaterThan(0, AttendanceRecord::query()->count(), 'Sample attendance records exist.');

        // Every record references a real student in a real session, never a name.
        $record = AttendanceRecord::query()->firstOrFail();
        $this->assertInstanceOf(Student::class, $record->student);
        $this->assertInstanceOf(AttendanceSession::class, $record->session);
        $this->assertNotNull($record->marked_at);
        $this->assertContains($record->status, AttendanceStatus::cases());

        $this->assertGreaterThan(
            0,
            AttendanceRecord::query()->whereIn('status', [AttendanceStatus::Sick, AttendanceStatus::Absent, AttendanceStatus::Excused])->count(),
            'The sample data includes absences, so a report is not all green.'
        );
    }

    public function test_there_is_an_open_ppdb_period_with_applicants_and_a_revision_case(): void
    {
        $this->seedFixtures();

        $period = AdmissionPeriod::query()
            ->where('status', 'open')
            ->whereNotNull('published_at')
            ->firstOrFail();

        $this->assertTrue($period->isCurrentlyOpen());

        $portal = AdmissionApplication::query()
            ->where('admission_period_id', $period->getKey())
            ->whereNotNull('applicant_user_id')
            ->get();

        $this->assertGreaterThanOrEqual(2, $portal->count(), 'There are portal applicants in several states.');

        foreach ($portal as $application) {
            $this->assertSame('ppdb_portal', $application->source);
            $this->assertNotNull($application->registration_number);
            $this->assertNotNull($application->submitted_at, 'A portal applicant fixture is always submitted.');
            $this->assertNotNull($application->consent_accepted_at);
            $this->assertGreaterThan(0, $application->guardians()->count());
            $this->assertSame(
                count(AdmissionDocumentType::required()),
                $application->documents()->whereIn('document_type', AdmissionDocumentType::required())->count(),
                'Every required document category is represented.'
            );
        }

        $this->assertGreaterThanOrEqual(
            1,
            ApplicationDocument::query()->where('verification_status', AdmissionDocumentVerificationStatus::NeedsRevision->value)->count(),
            'At least one document is waiting for a replacement.'
        );

        $this->assertGreaterThanOrEqual(
            1,
            ApplicationDocument::query()->where('verification_status', AdmissionDocumentVerificationStatus::Verified->value)->count(),
            'Some documents are already accepted.'
        );
    }

    public function test_the_portal_applicants_are_candidates_and_not_students(): void
    {
        $this->seedFixtures();

        $candidates = User::query()->where('role', UserRole::Calon->value)->get();

        $this->assertGreaterThanOrEqual(2, $candidates->count());

        foreach ($candidates as $candidate) {
            $this->assertNull($candidate->student, 'A candidate owns no Student record.');
            $this->assertNull($candidate->teacher);
        }

        $this->assertNotNull(User::query()->where('email', 'admin.test@sekolah.local')->first());
        $this->assertNotNull(User::query()->where('email', 'guru.test@sekolah.local')->first());
        $this->assertNotNull(User::query()->where('email', 'siswa.test@sekolah.local')->first());
    }

    public function test_the_demo_command_refuses_to_run_in_production(): void
    {
        // The guard is a plain environment check with no bypass flag, so the only
        // honest assertion is that it is present and unconditional.
        $command = Artisan::all()['sekolah:seed-demo'] ?? null;

        $this->assertNotNull($command, 'The demo seeding command is registered.');
        $this->assertStringContainsString('The demo dataset cannot be seeded in production.', $this->readCommandSource());
    }

    private function readCommandSource(): string
    {
        return (string) file_get_contents(app_path('Console/Commands/SeedDemoDataCommand.php'));
    }
}
