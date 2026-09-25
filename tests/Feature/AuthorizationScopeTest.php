<?php

namespace Tests\Feature;

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

class AuthorizationScopeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createTestData(): array
    {
        $year = AcademicYear::factory()->active()->create();
        $class1 = ClassModel::factory()->active()->grade(10)->name('A')->create(['academic_year_id' => $year->id]);
        $class2 = ClassModel::factory()->active()->grade(10)->name('B')->create(['academic_year_id' => $year->id]);

        $teacher = Teacher::factory()->active()->create();
        $teacherUser = User::factory()->guru()->create();
        $teacher->user_id = $teacherUser->id;
        $teacher->save();

        $student1 = Student::factory()->active()->create();
        $student1User = User::factory()->create();
        $student1->user_id = $student1User->id;
        $student1->save();

        $student2 = Student::factory()->active()->create();
        $student2User = User::factory()->create();
        $student2->user_id = $student2User->id;
        $student2->save();

        $enrollment1 = Enrollment::factory()->active()->forStudent($student1)->forClass($class1)->create([
            'academic_year_id' => $year->id,
        ]);

        $enrollment2 = Enrollment::factory()->active()->forStudent($student2)->forClass($class2)->create([
            'academic_year_id' => $year->id,
        ]);

        $homeroomAssignment = ClassTeacherAssignment::factory()->active()
            ->forTeacher($teacher)
            ->forClass($class1)
            ->create();

        return [
            'year' => $year,
            'class1' => $class1,
            'class2' => $class2,
            'teacher' => $teacher,
            'teacherUser' => $teacherUser,
            'student1' => $student1,
            'student1User' => $student1User,
            'student2' => $student2,
            'student2User' => $student2User,
            'enrollment1' => $enrollment1,
            'enrollment2' => $enrollment2,
            'homeroomAssignment' => $homeroomAssignment,
        ];
    }

    public function test_guru_can_access_student_in_homeroom_class(): void
    {
        $data = $this->createTestData();

        $this->assertTrue(
            $data['teacherUser']->can('view', $data['student1']),
            'Guru with homeroom assignment should access student in their class'
        );
    }

    public function test_guru_cannot_access_student_in_other_class(): void
    {
        $data = $this->createTestData();

        $this->assertFalse(
            $data['teacherUser']->can('view', $data['student2']),
            'Guru should not access student in class they are not assigned to'
        );
    }

    public function test_guru_with_teaching_assignment_can_access_student(): void
    {
        $year = AcademicYear::factory()->active()->create();
        $class = ClassModel::factory()->active()->create(['academic_year_id' => $year->id]);
        $subject = Subject::factory()->active()->create();

        $teacher = Teacher::factory()->active()->create();
        $teacherUser = User::factory()->guru()->create();
        $teacher->user_id = $teacherUser->id;
        $teacher->save();

        $student = Student::factory()->active()->create();
        $studentUser = User::factory()->create();
        $student->user_id = $studentUser->id;
        $student->save();

        Enrollment::factory()->active()->forStudent($student)->forClass($class)->create([
            'academic_year_id' => $year->id,
        ]);

        TeachingAssignment::factory()->active()
            ->forTeacher($teacher)
            ->forClass($class)
            ->create(['subject_id' => $subject->id, 'academic_year_id' => $year->id]);

        $this->assertTrue(
            $teacherUser->can('view', $student),
            'Guru with teaching assignment should access student in their class'
        );
    }

    public function test_guru_without_assignments_cannot_access_any_student(): void
    {
        $teacher = Teacher::factory()->active()->create();
        $teacherUser = User::factory()->guru()->create();
        $teacher->user_id = $teacherUser->id;
        $teacher->save();

        $student = Student::factory()->active()->create();

        $this->assertFalse(
            $teacherUser->can('view', $student),
            'Guru without assignments should not access any student'
        );
    }

    public function test_guru_with_inactive_assignment_cannot_access_student(): void
    {
        $year = AcademicYear::factory()->active()->create();
        $class = ClassModel::factory()->active()->create(['academic_year_id' => $year->id]);

        $teacher = Teacher::factory()->active()->create();
        $teacherUser = User::factory()->guru()->create();
        $teacher->user_id = $teacherUser->id;
        $teacher->save();

        $student = Student::factory()->active()->create();
        $studentUser = User::factory()->create();
        $student->user_id = $studentUser->id;
        $student->save();

        Enrollment::factory()->active()->forStudent($student)->forClass($class)->create([
            'academic_year_id' => $year->id,
        ]);

        ClassTeacherAssignment::factory()->ended()
            ->forTeacher($teacher)
            ->forClass($class)
            ->create();

        $this->assertFalse(
            $teacherUser->can('view', $student),
            'Guru with ended assignment should not access student'
        );
    }

    public function test_guru_on_leave_cannot_access_student(): void
    {
        $year = AcademicYear::factory()->active()->create();
        $class = ClassModel::factory()->active()->create(['academic_year_id' => $year->id]);

        $teacher = Teacher::factory()->onLeave()->create();
        $teacherUser = User::factory()->guru()->create();
        $teacher->user_id = $teacherUser->id;
        $teacher->save();

        $student = Student::factory()->active()->create();
        $studentUser = User::factory()->create();
        $student->user_id = $studentUser->id;
        $student->save();

        Enrollment::factory()->active()->forStudent($student)->forClass($class)->create([
            'academic_year_id' => $year->id,
        ]);

        ClassTeacherAssignment::factory()->active()
            ->forTeacher($teacher)
            ->forClass($class)
            ->create();

        $this->assertFalse(
            $teacherUser->can('view', $student),
            'Guru on leave should not access student even with active assignment'
        );
    }

    public function test_siswa_can_access_own_profile(): void
    {
        $student = Student::factory()->active()->create();
        $studentUser = User::factory()->create();
        $student->user_id = $studentUser->id;
        $student->save();

        $this->assertTrue(
            $studentUser->can('view', $student),
            'Siswa should access their own profile'
        );
    }

    public function test_siswa_cannot_access_another_student(): void
    {
        $student1 = Student::factory()->active()->create();
        $student1User = User::factory()->create();
        $student1->user_id = $student1User->id;
        $student1->save();

        $student2 = Student::factory()->active()->create();
        $student2User = User::factory()->create();
        $student2->user_id = $student2User->id;
        $student2->save();

        $this->assertFalse(
            $student1User->can('view', $student2),
            'Siswa should not access another student'
        );
    }

    public function test_siswa_cannot_access_student_via_direct_id(): void
    {
        $student1 = Student::factory()->active()->create();
        $student1User = User::factory()->create();
        $student1->user_id = $student1User->id;
        $student1->save();

        $student2 = Student::factory()->active()->create();

        $this->assertFalse(
            $student1User->can('view', $student2),
            'Siswa should not access another student via direct ID'
        );
    }

    public function test_administrator_can_access_all_students(): void
    {
        $admin = User::factory()->administrator()->create();
        $student1 = Student::factory()->active()->create();
        $student2 = Student::factory()->active()->create();

        $this->assertTrue($admin->can('view', $student1));
        $this->assertTrue($admin->can('view', $student2));
        $this->assertTrue($admin->can('viewAny', Student::class));
    }

    public function test_guru_can_view_own_teacher_profile(): void
    {
        $teacher = Teacher::factory()->active()->create();
        $teacherUser = User::factory()->guru()->create();
        $teacher->user_id = $teacherUser->id;
        $teacher->save();

        $this->assertTrue($teacherUser->can('view', $teacher));
    }

    public function test_guru_cannot_view_other_teacher_profile(): void
    {
        $teacher1 = Teacher::factory()->active()->create();
        $teacher1User = User::factory()->guru()->create();
        $teacher1->user_id = $teacher1User->id;
        $teacher1->save();

        $teacher2 = Teacher::factory()->active()->create();

        $this->assertFalse($teacher1User->can('view', $teacher2));
    }

    public function test_guru_can_view_own_assignments(): void
    {
        $year = AcademicYear::factory()->active()->create();
        $class = ClassModel::factory()->active()->create(['academic_year_id' => $year->id]);

        $teacher = Teacher::factory()->active()->create();
        $teacherUser = User::factory()->guru()->create();
        $teacher->user_id = $teacherUser->id;
        $teacher->save();

        $assignment = ClassTeacherAssignment::factory()->active()
            ->forTeacher($teacher)
            ->forClass($class)
            ->create();

        $this->assertTrue($teacherUser->can('view', $assignment));
    }

    public function test_guru_cannot_view_other_teacher_assignments(): void
    {
        $year = AcademicYear::factory()->active()->create();
        $class = ClassModel::factory()->active()->create(['academic_year_id' => $year->id]);

        $teacher1 = Teacher::factory()->active()->create();
        $teacher1User = User::factory()->guru()->create();
        $teacher1->user_id = $teacher1User->id;
        $teacher1->save();

        $teacher2 = Teacher::factory()->active()->create();

        $assignment = ClassTeacherAssignment::factory()->active()
            ->forTeacher($teacher2)
            ->forClass($class)
            ->create();

        $this->assertFalse($teacher1User->can('view', $assignment));
    }
}
