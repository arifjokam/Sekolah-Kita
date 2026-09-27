<?php

namespace App\Policies;

use App\Enums\AttendanceSessionStatus;
use App\Models\AttendanceSession;
use App\Models\Teacher;
use App\Models\User;

/**
 * Who may mark attendance for a lesson.
 *
 * The rule a teacher must never break: they may only touch a session for a class
 * they are assigned to teach, for a subject they are assigned in, and — for a
 * scheduled lesson — a lesson that is actually timetabled to them. A session id
 * from another teacher's timetable is rejected here, in the policy, before any
 * query on its records runs.
 */
class AttendanceSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && ($user->isAdministrator() || $user->isGuru());
    }

    public function view(User $user, AttendanceSession $session): bool
    {
        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->isGuru()) {
            return $this->teacherMayMark($user, $session);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isGuru();
    }

    /**
     * Filling in the sheet. Only the assigned teacher of the session.
     */
    public function mark(User $user, AttendanceSession $session): bool
    {
        return $this->view($user, $session)
            && $session->teacher !== null
            && $user->teacher !== null
            && (int) $session->teacher_id === (int) $user->teacher->getKey();
    }

    public function submit(User $user, AttendanceSession $session): bool
    {
        return $this->mark($user, $session) && $session->status === AttendanceSessionStatus::Open;
    }

    /**
     * Correcting a submitted sheet.
     *
     * A correction is an explicit, separately audited action, so it additionally
     * requires the session to already be submitted — an open sheet is corrected
     * by saving it, not by the correction path.
     */
    public function correct(User $user, AttendanceSession $session): bool
    {
        return $this->mark($user, $session) && $session->status !== AttendanceSessionStatus::Open;
    }

    public function report(User $user, AttendanceSession $session): bool
    {
        return $this->view($user, $session);
    }

    public function update(User $user, AttendanceSession $session): bool
    {
        return $this->mark($user, $session);
    }

    public function delete(User $user, AttendanceSession $session): bool
    {
        return false;
    }

    /**
     * The shared scope check.
     *
     * A homeroom teacher may take attendance for any lesson of their own class,
     * because they are accountable for the register. A subject teacher may only
     * take attendance for the lessons that name them. A teacher with neither
     * relationship to the class cannot mark anything for it.
     */
    public function teacherMayMark(User $user, AttendanceSession $session): bool
    {
        $teacher = $user->teacher;

        if (! $teacher instanceof Teacher || ! $teacher->isActive()) {
            return false;
        }

        if ((int) $session->teacher_id === (int) $teacher->getKey()) {
            return true;
        }

        return $teacher->activeClassTeacherAssignments()
            ->where('class_id', $session->class_id)
            ->exists();
    }
}
