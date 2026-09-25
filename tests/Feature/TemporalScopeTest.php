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
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TemporalScopeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_future_homeroom_assignment_is_not_active(): void
    {
        $this->travelTo('2026-09-25 12:00:00');
        $teacher = Teacher::factory()->active()->create();
        $class = ClassModel::factory()->active()->create();

        $assignment = ClassTeacherAssignment::factory()
            ->active()
            ->forTeacher($teacher)
            ->forClass($class)
            ->create(['starts_on' => today()->addDay()->toDateString()]);

        $this->assertFalse($assignment->isActive());
        $this->assertFalse(
            $teacher->activeClassTeacherAssignments()->whereKey($assignment->id)->exists()
        );
    }

    public function test_ended_teaching_assignment_is_not_active(): void
    {
        $this->travelTo('2026-09-25 12:00:00');
        $teacher = Teacher::factory()->active()->create();
        $class = ClassModel::factory()->active()->create();
        $subject = Subject::factory()->active()->create();

        $assignment = TeachingAssignment::factory()
            ->active()
            ->forTeacher($teacher)
            ->forClass($class)
            ->create([
                'subject_id' => $subject->id,
                'starts_on' => today()->subDays(2)->toDateString(),
                'ends_on' => today()->subDay()->toDateString(),
            ]);

        $this->assertFalse($assignment->isActive());
        $this->assertFalse(
            $teacher->activeTeachingAssignments()->whereKey($assignment->id)->exists()
        );
    }

    public function test_future_enrollment_is_not_current(): void
    {
        $this->travelTo('2026-09-25 12:00:00');
        [$student, $enrollment] = $this->createEnrollment([
            'effective_from' => today()->addDay()->toDateString(),
            'effective_to' => null,
        ]);

        $this->assertFalse($enrollment->isActive());
        $this->assertFalse($enrollment->isCurrent());
        $this->assertNull($student->currentEnrollment());
        $this->assertFalse($student->activeEnrollments()->whereKey($enrollment->id)->exists());
    }

    public function test_enrollment_ending_today_remains_current(): void
    {
        $this->travelTo('2026-09-25 12:00:00');
        [$student, $enrollment] = $this->createEnrollment([
            'effective_from' => today()->subDay()->toDateString(),
            'effective_to' => today()->toDateString(),
        ]);

        $this->assertTrue($enrollment->isActive());
        $this->assertTrue($enrollment->isCurrent());
        $this->assertSame($enrollment->id, $student->currentEnrollment()?->id);
        $this->assertTrue($student->activeEnrollments()->whereKey($enrollment->id)->exists());
    }

    public function test_enrollment_ending_before_today_is_not_current(): void
    {
        $this->travelTo('2026-09-25 12:00:00');
        [$student, $enrollment] = $this->createEnrollment([
            'effective_from' => today()->subDays(2)->toDateString(),
            'effective_to' => today()->subDay()->toDateString(),
        ]);

        $this->assertFalse($enrollment->isActive());
        $this->assertFalse($enrollment->isCurrent());
        $this->assertNull($student->currentEnrollment());
        $this->assertFalse($student->activeEnrollments()->whereKey($enrollment->id)->exists());
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: Student, 1: Enrollment}
     */
    private function createEnrollment(array $attributes = []): array
    {
        $academicYear = AcademicYear::factory()->active()->create();
        $class = ClassModel::factory()->active()->create([
            'academic_year_id' => $academicYear->id,
        ]);
        $student = Student::factory()->active()->create();
        $enrollment = Enrollment::factory()
            ->active()
            ->forStudent($student)
            ->forClass($class)
            ->create(array_merge([
                'academic_year_id' => $academicYear->id,
                'enrolled_on' => today()->subDays(2)->toDateString(),
            ], $attributes));

        return [$student, $enrollment];
    }
}
