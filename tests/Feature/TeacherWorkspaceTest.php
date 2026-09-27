<?php

namespace Tests\Feature;

use App\Enums\AcademicYearStatus;
use App\Enums\AssignmentStatus;
use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\ClassTeacherAssignment;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The teacher workspace: the classes a teacher is assigned to, and the roster
 * of those classes.
 *
 * The scoping is the point of these cases. A teacher must not be able to reach
 * an administrator screen, and must not be able to read a class they are not
 * assigned to, even with a direct identifier.
 */
class TeacherWorkspaceTest extends TestCase
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

    private function assignTeaching(Teacher $teacher, ClassModel $class, AcademicYear $year): void
    {
        TeachingAssignment::factory()->create([
            'teacher_id' => $teacher->getKey(),
            'class_id' => $class->getKey(),
            'subject_id' => Subject::factory()->create()->getKey(),
            'academic_year_id' => $year->getKey(),
            'status' => AssignmentStatus::Active,
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

    public function test_a_teacher_sees_only_the_classes_they_are_assigned_to(): void
    {
        $year = $this->academicYear();
        $user = User::factory()->guru()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->getKey()]);

        $taught = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active, 'name' => 'A']);
        $homeroom = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active, 'name' => 'B']);
        $unassigned = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active, 'name' => 'Z']);

        $this->assignTeaching($teacher, $taught, $year);
        ClassTeacherAssignment::factory()->create([
            'teacher_id' => $teacher->getKey(),
            'class_id' => $homeroom->getKey(),
            'status' => AssignmentStatus::Active,
        ]);

        $this->actingAs($user)->get(route('guru.classes.index'))
            ->assertOk()
            ->assertSee('Kelas yang saya ampu')
            ->assertSee($taught->getFullName())
            ->assertSee($homeroom->getFullName())
            ->assertSee('Wali kelas')
            ->assertDontSee($unassigned->getFullName());
    }

    public function test_a_teacher_can_open_the_roster_of_an_assigned_class(): void
    {
        $year = $this->academicYear();
        $user = User::factory()->guru()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->getKey()]);
        $class = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active]);

        $this->assignTeaching($teacher, $class, $year);
        // The roster renders the display name, which prefers `preferred_name`.
        // Both columns are pinned so the assertions do not depend on faker.
        $student = Student::factory()->create([
            'legal_name' => 'Roster Legal Name',
            'preferred_name' => 'Siswa roster',
            'student_number' => 'NIS-ROSTER-1',
        ]);
        $this->enroll($student, $class);

        $this->actingAs($user)->get(route('guru.classes.show', $class))
            ->assertOk()
            ->assertSee($class->getFullName())
            ->assertSee('Siswa roster')
            ->assertDontSee('Roster Legal Name')
            ->assertSee('NIS-ROSTER-1');

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $user->id,
            'action' => 'teacher.class_roster.viewed',
            'target_type' => 'class',
            'target_id' => $class->id,
        ]);
    }

    public function test_a_teacher_is_forbidden_on_a_class_they_are_not_assigned_to(): void
    {
        $year = $this->academicYear();
        $user = User::factory()->guru()->create();
        $other = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active]);

        $this->actingAs($user)->get(route('guru.classes.show', $other))->assertForbidden();
    }

    public function test_the_teacher_workspace_is_closed_to_other_roles_and_guests(): void
    {
        $year = $this->academicYear();
        $class = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active]);

        foreach ([User::factory()->administrator()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('guru.classes.index'))->assertForbidden();
            $this->actingAs($user)->get(route('guru.classes.show', $class))->assertForbidden();
        }
    }

    public function test_guests_are_redirected_from_the_teacher_workspace(): void
    {
        $year = $this->academicYear();
        $class = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active]);

        $this->get(route('guru.classes.index'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('guru.classes.show', $class))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_workspace_explains_a_missing_profile_and_a_missing_assignment(): void
    {
        $withoutProfile = User::factory()->guru()->create();
        $this->actingAs($withoutProfile)->get(route('guru.classes.index'))
            ->assertOk()
            ->assertSee('Data guru belum terhubung');

        $withProfile = User::factory()->guru()->create();
        Teacher::factory()->create(['user_id' => $withProfile->getKey()]);

        $this->actingAs($withProfile)->get(route('guru.classes.index'))
            ->assertOk()
            ->assertSee('Belum ada kelas');
    }

    public function test_the_guru_dashboard_only_links_to_routes_the_role_can_open(): void
    {
        $year = $this->academicYear();
        $user = User::factory()->guru()->create();
        $teacher = Teacher::factory()->create(['user_id' => $user->getKey()]);
        $class = ClassModel::factory()->create(['academic_year_id' => $year->getKey(), 'status' => ClassStatus::Active]);
        $this->assignTeaching($teacher, $class, $year);

        $dashboard = $this->actingAs($user)->get(route('dashboard.guru'))->assertOk();
        $content = $dashboard->getContent();

        $this->assertStringNotContainsString('/admin/', $content);

        // Every internal link on the dashboard must actually open.
        preg_match_all('/href="([^"]+)"/', $content, $matches);

        foreach ($matches[1] as $href) {
            if (! str_starts_with($href, '/')) {
                continue;
            }

            $this->actingAs($user)->get($href)->assertOk();
        }
    }
}
