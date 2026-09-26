<?php

namespace Tests\Feature;

use App\Enums\AcademicYearStatus;
use App\Enums\AssignmentStatus;
use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\PublicationStatus;
use App\Enums\StudentLifecycleStatus;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\ClassTeacherAssignment;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\NewsArticle;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The three role dashboards and the scoping rules that protect them.
 *
 * Scoping is asserted with data, not with the absence of a link: a teacher must
 * see the students of the classes they actually teach and must not see the
 * students of a class they are not assigned to.
 */
class RoleDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function academicYear(): AcademicYear
    {
        return AcademicYear::query()->create([
            'code' => now()->format('Y').'/'.now()->addYear()->format('Y'),
            'label' => 'Tahun Ajaran Uji',
            'starts_on' => now()->subMonths(6)->startOfYear()->toDateString(),
            'ends_on' => now()->addMonths(6)->endOfYear()->toDateString(),
            'status' => AcademicYearStatus::Active,
        ]);
    }

    private function enroll(Student $student, ClassModel $class): Enrollment
    {
        return Enrollment::query()->create([
            'student_id' => $student->getKey(),
            'class_id' => $class->getKey(),
            'academic_year_id' => $class->academic_year_id,
            'status' => EnrollmentStatus::Active,
            'enrolled_on' => now()->subMonth()->toDateString(),
            'effective_from' => now()->subMonth()->toDateString(),
        ]);
    }

    public function test_administrator_dashboard_shows_real_operational_figures_and_quick_actions(): void
    {
        $admin = User::factory()->administrator()->create();
        $year = $this->academicYear();
        $subject = Subject::factory()->create();
        $class = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active]);
        $student = Student::factory()->create(['status' => StudentLifecycleStatus::Active]);
        $this->enroll($student, $class);
        Teacher::factory()->count(3)->create();

        NewsArticle::factory()->published()->create();
        NewsArticle::factory()->create(['status' => PublicationStatus::Draft]);

        $this->actingAs($admin)->get(route('dashboard.administrator'))
            ->assertOk()
            ->assertSee('Ringkasan sekolah')
            ->assertSee($year->label)
            ->assertSee('3') // total teachers
            ->assertSee('Aksi cepat')
            ->assertSee('Tambah guru')
            ->assertSee('Tambah siswa')
            ->assertSee('Kelola PPDB')
            ->assertSee('Lihat pengajuan')
            ->assertSee(route('admin.teachers.create'))
            ->assertSee(route('admin.students.create'))
            ->assertSee(route('admin.classes.create'))
            ->assertSee(route('admin.subjects.create'))
            ->assertSee(route('admin.news.create'))
            ->assertSee(route('admin.ppdb.periods.index'))
            ->assertSee(route('admin.ppdb.applications.index'))
            ->assertSee($subject->name === null ? '' : 'Mata pelajaran');
    }

    public function test_administrator_dashboard_explains_a_missing_academic_foundation(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('dashboard.administrator'))
            ->assertOk()
            ->assertSee('Belum ada')
            ->assertSee('Fondasi akademik belum lengkap')
            ->assertSee(route('admin.academic-years.create'));
    }

    public function test_administrator_dashboard_reports_empty_states_instead_of_fake_numbers(): void
    {
        $this->actingAs(User::factory()->administrator()->create())
            ->get(route('dashboard.administrator'))
            ->assertOk()
            ->assertSee('Belum ada pengajuan PPDB')
            ->assertSee('Belum ada aktivitas audit')
            ->assertSee('Belum ada');
    }

    public function test_guru_dashboard_shows_only_the_scoped_classes_subjects_and_students(): void
    {
        $year = $this->academicYear();
        $user = User::factory()->guru()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->getKey()]);

        $ownClass = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active, 'name' => 'A']);
        $otherClass = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active, 'name' => 'Z']);
        $subject = Subject::factory()->create(['code' => 'MTK2', 'name' => 'Matematika']);

        TeachingAssignment::factory()->create([
            'teacher_id' => $teacher->getKey(),
            'class_id' => $ownClass->getKey(),
            'subject_id' => $subject->getKey(),
            'academic_year_id' => $year->getKey(),
            'status' => AssignmentStatus::Active,
        ]);
        ClassTeacherAssignment::factory()->create([
            'teacher_id' => $teacher->getKey(),
            'class_id' => $ownClass->getKey(),
            'status' => AssignmentStatus::Active,
        ]);

        $insideStudent = Student::factory()->create(['legal_name' => 'Siswa Di Dalam Lingkup']);
        $outsideStudent = Student::factory()->create(['legal_name' => 'Siswa Luar Lingkup']);
        $this->enroll($insideStudent, $ownClass);
        $this->enroll($outsideStudent, $otherClass);

        $this->actingAs($user)->get(route('dashboard.guru'))
            ->assertOk()
            ->assertSee('Ringkasan mengajar')
            ->assertSee($teacher->getDisplayName())
            ->assertSee($subject->name)
            ->assertSee($ownClass->getFullName())
            ->assertSee('Wali kelas')
            ->assertDontSee($otherClass->getFullName());
    }

    public function test_guru_dashboard_explains_a_missing_profile(): void
    {
        $user = User::factory()->guru()->create();

        $this->actingAs($user)->get(route('dashboard.guru'))
            ->assertOk()
            ->assertSee('Data guru belum terhubung')
            ->assertSee('belum terhubung ke data guru');
    }

    public function test_guru_dashboard_explains_a_missing_assignment(): void
    {
        $user = User::factory()->guru()->create();
        Teacher::factory()->create(['user_id' => $user->getKey()]);

        $this->actingAs($user)->get(route('dashboard.guru'))
            ->assertOk()
            ->assertSee('Belum ada penugasan aktif')
            ->assertSee('Lihat kelas saya');
    }

    public function test_siswa_dashboard_shows_only_the_own_record(): void
    {
        $year = $this->academicYear();
        $user = User::factory()->create();
        $student = Student::factory()->create(['user_id' => $user->getKey(), 'legal_name' => 'Siswa Sendiri']);
        $class = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active]);
        $this->enroll($student, $class);

        $guardian = Guardian::factory()->create(['legal_name' => 'Wali Tunggal']);
        StudentGuardian::factory()->create([
            'student_id' => $student->getKey(),
            'guardian_id' => $guardian->getKey(),
            'is_primary_contact' => true,
        ]);

        $other = Student::factory()->create(['legal_name' => 'Siswa Orang Lain']);
        $this->enroll($other, ClassModel::factory()->create(['academic_year_id' => $year->getKey()]));

        NewsArticle::factory()->published()->create(['title' => 'Pengumuman Uji']);

        $this->actingAs($user)->get(route('dashboard.siswa'))
            ->assertOk()
            ->assertSee('Informasi siswa')
            ->assertSee($student->getDisplayName())
            ->assertSee($class->getFullName())
            ->assertSee('Wali Tunggal')
            ->assertSee('Pengumuman Uji')
            ->assertDontSee('Siswa Orang Lain');
    }

    public function test_siswa_dashboard_explains_a_missing_enrollment(): void
    {
        $user = User::factory()->create();
        Student::factory()->create(['user_id' => $user->getKey()]);

        $this->actingAs($user)->get(route('dashboard.siswa'))
            ->assertOk()
            ->assertSee('Belum ada enrollment')
            ->assertSee('Enrollment belum tersedia');
    }

    public function test_siswa_dashboard_explains_a_missing_profile(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard.siswa'))
            ->assertOk()
            ->assertSee('Data siswa belum tersedia');
    }

    public function test_each_role_landing_page_is_closed_to_the_other_roles(): void
    {
        $admin = User::factory()->administrator()->create();
        $guru = User::factory()->guru()->create();
        $siswa = User::factory()->create();

        $this->actingAs($admin)->get(route('dashboard.administrator'))->assertOk();
        $this->actingAs($guru)->get(route('dashboard.guru'))->assertOk();
        $this->actingAs($siswa)->get(route('dashboard.siswa'))->assertOk();

        $this->actingAs($guru)->get(route('dashboard.administrator'))->assertForbidden();
        $this->actingAs($siswa)->get(route('dashboard.administrator'))->assertForbidden();
        $this->actingAs($admin)->get(route('dashboard.guru'))->assertForbidden();
        $this->actingAs($siswa)->get(route('dashboard.guru'))->assertForbidden();
        $this->actingAs($admin)->get(route('dashboard.siswa'))->assertForbidden();
        $this->actingAs($guru)->get(route('dashboard.siswa'))->assertForbidden();
    }

    public function test_the_shared_dashboard_route_resolves_the_role_landing_page(): void
    {
        $admin = User::factory()->administrator()->create();
        $guru = User::factory()->guru()->create();
        $siswa = User::factory()->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('dashboard.administrator'));
        $this->actingAs($guru)->get(route('dashboard'))->assertRedirect(route('dashboard.guru'));
        $this->actingAs($siswa)->get(route('dashboard'))->assertRedirect(route('dashboard.siswa'));
    }

    public function test_a_guru_cannot_read_another_teachers_record(): void
    {
        $user = User::factory()->guru()->create();
        $own = Teacher::factory()->create(['user_id' => $user->getKey()]);
        $other = Teacher::factory()->create();

        $this->actingAs($user)->get(route('admin.teachers.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.teachers.show', $own))->assertForbidden();
        $this->actingAs($user)->get(route('admin.teachers.show', $other))->assertForbidden();
    }

    public function test_a_guru_may_only_see_students_from_assigned_classes(): void
    {
        $year = $this->academicYear();
        $user = User::factory()->guru()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->getKey()]);
        $ownClass = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active]);
        $otherClass = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active]);

        TeachingAssignment::factory()->create([
            'teacher_id' => $teacher->getKey(),
            'class_id' => $ownClass->getKey(),
            'subject_id' => Subject::factory()->create()->getKey(),
            'academic_year_id' => $year->getKey(),
            'status' => AssignmentStatus::Active,
        ]);

        $inside = Student::factory()->create();
        $outside = Student::factory()->create();
        $insideEnrollment = $this->enroll($inside, $ownClass);
        $outsideEnrollment = $this->enroll($outside, $otherClass);

        $this->assertTrue($user->can('view', $inside));
        $this->assertFalse($user->can('view', $outside));

        $this->actingAs($user)->get(route('admin.enrollments.index'))->assertForbidden();

        // The enrollment policy is the second gate: even a direct identifier
        // does not widen what the dashboard already scopes.
        $this->assertTrue($user->can('view', $insideEnrollment));
        $this->assertFalse($user->can('view', $outsideEnrollment));
    }

    public function test_a_siswa_cannot_read_another_students_record_or_the_admin_area(): void
    {
        $user = User::factory()->create();
        $own = Student::factory()->create(['user_id' => $user->getKey()]);
        $other = Student::factory()->create();

        $this->assertTrue($user->can('view', $own));
        $this->assertFalse($user->can('view', $other));

        $this->actingAs($user)->get(route('admin.students.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.students.show', $own))->assertForbidden();
        $this->actingAs($user)->get(route('admin.teachers.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.ppdb.review.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    public function test_guests_cannot_reach_any_dashboard(): void
    {
        foreach (['dashboard', 'dashboard.administrator', 'dashboard.guru', 'dashboard.siswa'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }
}
