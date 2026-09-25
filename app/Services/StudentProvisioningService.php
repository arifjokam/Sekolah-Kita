<?php

namespace App\Services;

use App\Enums\GuardianLifecycleStatus;
use App\Enums\StudentLifecycleStatus;
use App\Models\AdmissionApplication;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentGuardian;

class StudentProvisioningService
{
    public function __construct(private readonly StudentNumberAllocator $studentNumbers) {}

    public function createStudent(AdmissionApplication $application): Student
    {
        $period = $application->period;
        $admissionDate = $period?->opens_at?->toDateString() ?? today()->toDateString();

        return Student::query()->create([
            'legal_name' => $application->applicant_name,
            'preferred_name' => $application->preferred_name,
            'birth_date' => $application->birth_date,
            'birth_place' => $application->birth_place,
            'gender' => $application->gender,
            'address' => $application->address,
            'phone' => $application->contact_phone,
            'email' => $application->contact_email,
            'student_number' => $this->studentNumbers->allocate(),
            'status' => StudentLifecycleStatus::Active,
            'admission_date' => $admissionDate,
        ]);
    }

    public function linkGuardians(AdmissionApplication $application, Student $student): void
    {
        $startsOn = $application->period?->opens_at?->toDateString() ?? today()->toDateString();

        foreach ($application->guardians()->orderBy('priority_order')->orderBy('id')->get() as $applicationGuardian) {
            $guardian = $applicationGuardian->canonicalGuardian;

            if ($guardian === null) {
                $guardian = Guardian::query()->create([
                    'legal_name' => $applicationGuardian->legal_name,
                    'phone' => $applicationGuardian->phone,
                    'email' => $applicationGuardian->email,
                    'address' => $applicationGuardian->address,
                    'status' => GuardianLifecycleStatus::Active,
                ]);
                $applicationGuardian->forceFill(['canonical_guardian_id' => $guardian->getKey()])->save();
            }

            StudentGuardian::query()->create([
                'student_id' => $student->getKey(),
                'guardian_id' => $guardian->getKey(),
                'relationship_type' => $applicationGuardian->relationship_type,
                'is_primary_contact' => $applicationGuardian->is_primary_contact,
                'is_emergency_contact' => $applicationGuardian->is_emergency_contact,
                'priority_order' => $applicationGuardian->priority_order,
                'starts_on' => $startsOn,
                'ends_on' => null,
                'relationship_note' => null,
            ]);
        }
    }
}
