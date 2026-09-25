<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PrivacyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_student_employee_number_not_in_arrayable(): void
    {
        $student = Student::factory()->create();
        $array = $student->toArray();

        $this->assertArrayNotHasKey('student_number', $array);
        $this->assertArrayNotHasKey('national_id', $array);
        $this->assertArrayNotHasKey('nisn', $array);
        $this->assertArrayNotHasKey('address', $array);
        $this->assertArrayNotHasKey('phone', $array);
        $this->assertArrayNotHasKey('email', $array);
        $this->assertArrayNotHasKey('birth_date', $array);
        $this->assertArrayNotHasKey('birth_place', $array);
        $this->assertArrayNotHasKey('gender', $array);
    }

    public function test_teacher_employee_number_not_in_arrayable(): void
    {
        $teacher = Teacher::factory()->create();
        $array = $teacher->toArray();

        $this->assertArrayNotHasKey('employee_number', $array);
        $this->assertArrayNotHasKey('national_id', $array);
        $this->assertArrayNotHasKey('address', $array);
        $this->assertArrayNotHasKey('phone', $array);
        $this->assertArrayNotHasKey('email', $array);
        $this->assertArrayNotHasKey('birth_date', $array);
        $this->assertArrayNotHasKey('birth_place', $array);
        $this->assertArrayNotHasKey('gender', $array);
    }

    public function test_guardian_data_not_in_arrayable(): void
    {
        $guardian = Guardian::factory()->create();
        $array = $guardian->toArray();

        $this->assertArrayNotHasKey('national_id', $array);
        $this->assertArrayNotHasKey('address', $array);
        $this->assertArrayNotHasKey('phone', $array);
        $this->assertArrayNotHasKey('email', $array);
    }

    public function test_student_guardian_relationship_not_in_arrayable(): void
    {
        $student = Student::factory()->create();
        $guardian = Guardian::factory()->create();
        $relationship = StudentGuardian::factory()->forStudent($student)->forGuardian($guardian)->create();
        $array = $relationship->toArray();

        $this->assertArrayNotHasKey('relationship_note', $array);
    }

    public function test_user_password_not_in_arrayable(): void
    {
        $user = User::factory()->create();
        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }

    public function test_student_model_hidden_attributes(): void
    {
        $student = Student::factory()->create();
        $hidden = $student->getHidden();

        $this->assertContains('student_number', $hidden);
        $this->assertContains('national_id', $hidden);
        $this->assertContains('nisn', $hidden);
        $this->assertContains('address', $hidden);
        $this->assertContains('phone', $hidden);
        $this->assertContains('email', $hidden);
        $this->assertContains('leaving_reason', $hidden);
        $this->assertContains('birth_date', $hidden);
        $this->assertContains('birth_place', $hidden);
        $this->assertContains('gender', $hidden);
    }

    public function test_teacher_model_hidden_attributes(): void
    {
        $teacher = Teacher::factory()->create();
        $hidden = $teacher->getHidden();

        $this->assertContains('employee_number', $hidden);
        $this->assertContains('national_id', $hidden);
        $this->assertContains('address', $hidden);
        $this->assertContains('phone', $hidden);
        $this->assertContains('email', $hidden);
        $this->assertContains('birth_date', $hidden);
        $this->assertContains('birth_place', $hidden);
        $this->assertContains('gender', $hidden);
    }

    public function test_guardian_model_hidden_attributes(): void
    {
        $guardian = Guardian::factory()->create();
        $hidden = $guardian->getHidden();

        $this->assertContains('national_id', $hidden);
        $this->assertContains('address', $hidden);
        $this->assertContains('phone', $hidden);
        $this->assertContains('email', $hidden);
    }
}
