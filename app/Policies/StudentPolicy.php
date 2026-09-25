<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && ($user->isAdministrator() || $user->isGuru());
    }

    public function view(User $user, Student $student): bool
    {
        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->isSiswa() && $user->student?->id === $student->id) {
            return true;
        }

        if ($user->isGuru() && $this->guruCanAccessStudent($user, $student)) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    public function update(User $user, Student $student): bool
    {
        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->isSiswa() && $user->student?->id === $student->id) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Student $student): bool
    {
        return false;
    }

    public function viewEnrollments(User $user, Student $student): bool
    {
        return $this->view($user, $student);
    }

    public function manageEnrollments(User $user, Student $student): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    private function guruCanAccessStudent(User $guru, Student $student): bool
    {
        $teacher = $guru->teacher;
        if (! $teacher || ! $teacher->isActive()) {
            return false;
        }

        $currentEnrollment = $student->currentEnrollment();
        if (! $currentEnrollment || ! $currentEnrollment->class) {
            return false;
        }

        $class = $currentEnrollment->class;

        $hasHomeroom = $teacher->activeClassTeacherAssignments()
            ->where('class_id', $class->id)
            ->exists();

        $hasTeachingAssignment = $teacher->activeTeachingAssignments()
            ->where('class_id', $class->id)
            ->exists();

        return $hasHomeroom || $hasTeachingAssignment;
    }
}
