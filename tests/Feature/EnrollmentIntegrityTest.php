<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EnrollmentIntegrityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_create_rejects_a_second_active_enrollment_for_the_same_student_and_year(): void
    {
        $data = $this->createTestData();
        $service = app(EnrollmentService::class);

        $service->create(
            $data['administrator'],
            $data['student'],
            $data['classA'],
            today()->toDateString(),
        );

        try {
            $service->create(
                $data['administrator'],
                $data['student'],
                $data['classB'],
                today()->toDateString(),
            );
            $this->fail('A duplicate active enrollment was created.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('enrollment', $exception->errors());
        }

        $this->assertDatabaseCount('enrollments', 1);
    }

    public function test_transfer_closes_the_previous_enrollment_and_creates_one_active_enrollment(): void
    {
        $data = $this->createTestData();
        $service = app(EnrollmentService::class);
        $effectiveOn = today()->toDateString();

        $original = $service->create(
            $data['administrator'],
            $data['student'],
            $data['classA'],
            $effectiveOn,
        );

        $replacement = $service->transfer(
            $data['administrator'],
            $original,
            $data['classB'],
            $effectiveOn,
            'Pindah kelas',
        );

        $original->refresh();
        $this->assertSame(EnrollmentStatus::Transferred, $original->status);
        $this->assertSame($effectiveOn, $original->effective_to->toDateString());
        $this->assertSame(EnrollmentStatus::Active, $replacement->status);
        $this->assertTrue($replacement->isActive());
        $this->assertDatabaseCount('enrollments', 2);
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $data['student']->id,
            'status' => EnrollmentStatus::Active->value,
        ]);
    }

    public function test_non_administrator_cannot_create_an_enrollment(): void
    {
        $data = $this->createTestData();
        $service = app(EnrollmentService::class);
        $nonAdministrator = User::factory()->guru()->create();

        try {
            $service->create(
                $nonAdministrator,
                $data['student'],
                $data['classA'],
                today()->toDateString(),
            );
            $this->fail('A non-administrator created an enrollment.');
        } catch (AuthorizationException) {
        }

        $this->assertDatabaseCount('enrollments', 0);
    }

    /**
     * @return array{academicYear: AcademicYear, classA: ClassModel, classB: ClassModel, student: Student, administrator: User}
     */
    private function createTestData(): array
    {
        $academicYear = AcademicYear::factory()->active()->create();
        $classA = ClassModel::factory()->active()->create([
            'academic_year_id' => $academicYear->id,
            'name' => 'A',
        ]);
        $classB = ClassModel::factory()->active()->create([
            'academic_year_id' => $academicYear->id,
            'name' => 'B',
        ]);

        return [
            'academicYear' => $academicYear,
            'classA' => $classA,
            'classB' => $classB,
            'student' => Student::factory()->active()->create(),
            'administrator' => User::factory()->administrator()->create(),
        ];
    }
}
