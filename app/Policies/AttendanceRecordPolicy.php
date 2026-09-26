<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\User;

/**
 * Individual attendance records.
 *
 * A record is never addressed directly: the route always carries the session and
 * the record together, and the session is what decides. A teacher therefore
 * cannot reach a record from a lesson they have no authority over by swapping the
 * record id, and a student can only ever read their own row.
 */
class AttendanceRecordPolicy
{
    public function view(User $user, AttendanceRecord $record): bool
    {
        $session = $record->session;

        if (! $user->isActive() || ! $user->hasVerifiedEmail() || ! $session instanceof AttendanceSession) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->isSiswa()) {
            return $user->student !== null
                && (int) $record->student_id === (int) $user->student->getKey();
        }

        return $user->can('view', $session);
    }

    public function update(User $user, AttendanceRecord $record): bool
    {
        $session = $record->session;

        return $session instanceof AttendanceSession && $user->can('mark', $session);
    }

    public function delete(User $user, AttendanceRecord $record): bool
    {
        return false;
    }
}
