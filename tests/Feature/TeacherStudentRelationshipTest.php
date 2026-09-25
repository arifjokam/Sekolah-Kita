<?php

namespace Tests\Feature;

use App\Enums\AcademicYearStatus;
use App\Enums\AssignmentStatus;
use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\StudentLifecycleStatus;
use App\Enums\TeacherEmploymentStatus;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\ClassTeacherAssignment;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TeacherStudentRelationshipTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_teacher_can_have_user_account(): void
    {
        $user = User::factory()->guru()->create();
        $teacher = Teacher::factory()->withUser()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $teacher->user_id);
        $this->assertEquals($teacher->id, $user->teacher->id);
    }

    public function test_student_can_have_user_account(): void
    {
        $user = User::factory()->create();
        $student = Student::factory()->withUser()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $student->user_id);
        $this->assertEquals($student->id, $user->student->id);
    }

    public function test_user_cannot_have_two_teacher_profiles(): void
    {
        $user = User::factory()->guru()->create();
        Teacher::factory()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);
        Teacher::factory()->create(['user_id' => $user->id]);
    }

    public function test_user_cannot_have_two_student_profiles(): void
    {
        $user = User::factory()->create();
        Student::factory()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);
        Student::factory()->create(['user_id' => $user->id]);
    }

    public function test_teacher_employment_status_is_cast(): void
    {
        $teacher = Teacher::factory()->active()->create();
        $this->assertInstanceOf(TeacherEmploymentStatus::class, $teacher->employment_status);
        $this->assertEquals(TeacherEmploymentStatus::Active, $teacher->employment_status);
    }

    public function test_student_lifecycle_status_is_cast(): void
    {
        $student = Student::factory()->active()->create();
        $this->assertInstanceOf(StudentLifecycleStatus::class, $student->status);
        $this->assertEquals(StudentLifecycleStatus::Active, $student->status);
    }

    public function test_academic_year_status_is_cast(): void
    {
        $year = AcademicYear::factory()->active()->create();
        $this->assertInstanceOf(AcademicYearStatus::class, $year->status);
        $this->assertEquals(AcademicYearStatus::Active, $year->status);
    }

    public function test_class_status_is_cast(): void
    {
        $class = ClassModel::factory()->active()->create();
        $this->assertInstanceOf(ClassStatus::class, $class->status);
        $this->assertEquals(ClassStatus::Active, $class->status);
    }

    public function test_enrollment_status_is_cast(): void
    {
        $enrollment = Enrollment::factory()->active()->create();
        $this->assertInstanceOf(EnrollmentStatus::class, $enrollment->status);
        $this->assertEquals(EnrollmentStatus::Active, $enrollment->status);
    }

    public function test_assignment_status_is_cast(): void
    {
        $assignment = ClassTeacherAssignment::factory()->active()->create();
        $this->assertInstanceOf(AssignmentStatus::class, $assignment->status);
        $this->assertEquals(AssignmentStatus::Active, $assignment->status);
    }
}
