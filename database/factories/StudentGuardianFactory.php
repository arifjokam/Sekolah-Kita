<?php

namespace Database\Factories;

use App\Enums\GuardianRelationshipType;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentGuardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentGuardian>
 */
class StudentGuardianFactory extends Factory
{
    protected $model = StudentGuardian::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'guardian_id' => Guardian::factory(),
            'relationship_type' => GuardianRelationshipType::Father,
            'is_primary_contact' => true,
            'is_emergency_contact' => true,
            'priority_order' => 1,
            'starts_on' => fake()->dateTimeBetween('-5 years', 'now'),
            'ends_on' => null,
            'relationship_note' => fake()->optional()->sentence(),
        ];
    }

    public function mother(): static
    {
        return $this->state(fn (array $attributes): array => [
            'relationship_type' => GuardianRelationshipType::Mother,
        ]);
    }

    public function father(): static
    {
        return $this->state(fn (array $attributes): array => [
            'relationship_type' => GuardianRelationshipType::Father,
        ]);
    }

    public function emergencyOnly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_primary_contact' => false,
            'is_emergency_contact' => true,
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ends_on' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function forStudent(Student $student): static
    {
        return $this->state(fn (array $attributes): array => [
            'student_id' => $student->id,
        ]);
    }

    public function forGuardian(Guardian $guardian): static
    {
        return $this->state(fn (array $attributes): array => [
            'guardian_id' => $guardian->id,
        ]);
    }
}
