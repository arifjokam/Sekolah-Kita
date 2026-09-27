<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition(): array
    {
        return [
            'attendance_session_id' => AttendanceSession::factory(),
            'student_id' => Student::factory(),
            'status' => AttendanceStatus::Present,
            'note' => null,
            'marked_by_user_id' => null,
            'marked_at' => now(),
            'revision' => 0,
        ];
    }

    public function status(AttendanceStatus $status, ?string $note = null): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'note' => $note ?? ($status->requiresNote() ? 'Keterangan dari wali kelas' : null),
        ]);
    }
}
