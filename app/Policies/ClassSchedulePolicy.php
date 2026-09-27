<?php

namespace App\Policies;

use App\Enums\ScheduleStatus;
use App\Models\ClassSchedule;
use App\Models\Teacher;
use App\Models\User;

/**
 * The weekly class schedule.
 *
 * Reading scope mirrors ClassPolicy exactly: an administrator sees everything, a
 * teacher sees the lessons attached to a class they actually teach or are the
 * homeroom teacher of, and a student sees only their own class's lessons. A
 * schedule id in a URL is therefore never enough to widen what an account can
 * read, because the row's own class_id is what is checked.
 */
class ClassSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user) || $this->isStudent($user);
    }

    public function view(User $user, ClassSchedule $schedule): bool
    {
        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->isGuru()) {
            return $this->teacherOwnsClass($user, $schedule);
        }

        if ($user->isSiswa()) {
            $enrollment = $user->student?->currentEnrollment();

            return $enrollment !== null && (int) $enrollment->class_id === (int) $schedule->class_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    public function update(User $user, ClassSchedule $schedule): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, ClassSchedule $schedule): bool
    {
        // Attendance history references the schedule, so removal is a
        // deactivation rather than a delete.
        return false;
    }

    public function deactivate(User $user, ClassSchedule $schedule): bool
    {
        return $this->create($user) && $schedule->status === ScheduleStatus::Active;
    }

    public function manage(User $user): bool
    {
        return $this->create($user);
    }

    private function isStaff(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && ($user->isAdministrator() || $user->isGuru());
    }

    private function isStudent(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isSiswa();
    }

    private function teacherOwnsClass(User $user, ClassSchedule $schedule): bool
    {
        $teacher = $user->teacher;

        if (! $teacher instanceof Teacher || ! $teacher->isActive()) {
            return false;
        }

        // A teacher may only see the lessons they are personally timetabled for,
        // not merely every lesson of a class they teach somewhere in it.
        if ((int) $schedule->teacher_id === (int) $teacher->getKey()) {
            return true;
        }

        return $teacher->activeClassTeacherAssignments()
            ->where('class_id', $schedule->class_id)
            ->exists();
    }
}
