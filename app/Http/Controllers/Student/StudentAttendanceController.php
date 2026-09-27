<?php

namespace App\Http\Controllers\Student;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Term;
use App\Services\AttendanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * A student's own attendance summary.
 *
 * Every read is filtered by the authenticated student's own id, taken from the
 * session — never from a query parameter — so a student cannot read another
 * student's register by changing a URL. Month and term are the only inputs, and
 * both only narrow the range.
 */
class StudentAttendanceController extends Controller
{
    public function index(Request $request, AttendanceService $attendance): View|RedirectResponse
    {
        $student = $request->user()->student;

        if (! $student instanceof Student) {
            return redirect()->route('dashboard.siswa')
                ->with('status', 'Data siswa belum terhubung ke akun ini.');
        }

        $termId = $request->integer('term_id') ?: null;
        $summary = $attendance->studentSummary($student, $termId);
        $enrollment = $student->currentEnrollment();

        return view('siswa.absensi', [
            'student' => $student,
            'enrollment' => $enrollment,
            'class' => $enrollment?->class,
            'summary' => $summary,
            'statuses' => AttendanceStatus::cases(),
            'terms' => $this->terms($enrollment),
            'termId' => $termId,
            'month' => $this->resolveMonth($request),
            'monthlyRows' => $this->monthlyRows($student, $termId),
        ]);
    }

    /**
     * Per-month totals for this student only.
     *
     * @return array<string, array{total: int, attended: int, percentage: float}>
     */
    private function monthlyRows(Student $student, ?int $termId): array
    {
        /*
         * Bounded to the most recent school months. Reading every record the
         * student has ever had, hydrated with its session, produced a per-month
         * table that grew for as long as they stayed at the school and then threw
         * all but the top few rows away in the view.
         *
         * COUNT(*) is a literal with nothing interpolated into it; the month is
         * derived from a model column, never from request input.
         */
        $monthCount = 12;

        $rows = AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->where('attendance_records.student_id', $student->getKey())
            ->when($termId !== null, fn (Builder $query): Builder => $query->where('attendance_sessions.term_id', $termId))
            // Grouped by the month, not the date: a month with six sessions has to
            // come out as one row, and grouping by session_date would produce six
            // rows that then collide on the month key.
            ->selectRaw("DATE_FORMAT(attendance_sessions.session_date, '%Y-%m') AS attendance_month")
            ->selectRaw('COUNT(*) AS aggregate')
            ->selectRaw('SUM(CASE WHEN attendance_records.status IN (?, ?) THEN 1 ELSE 0 END) AS attended', [
                AttendanceStatus::Present->value,
                AttendanceStatus::Late->value,
            ])
            ->groupBy('attendance_month')
            ->orderByDesc('attendance_month')
            ->limit($monthCount)
            ->get();

        return $rows
            ->mapWithKeys(function ($row): array {
                $total = (int) $row->aggregate;
                $attended = (int) $row->attended;

                return [(string) $row->attendance_month => [
                    'total' => $total,
                    'attended' => $attended,
                    'percentage' => $total === 0 ? 0.0 : round(($attended / $total) * 100, 1),
                ]];
            })
            ->all();
    }

    /**
     * @return Collection<int, Term>
     */
    private function terms(?Enrollment $enrollment): Collection
    {
        $academicYear = $enrollment?->academicYear;

        if ($academicYear === null) {
            return new Collection;
        }

        return Term::query()
            ->where('academic_year_id', $academicYear->getKey())
            ->orderBy('sequence')
            ->get(['id', 'label', 'sequence', 'status']);
    }

    private function resolveMonth(Request $request): Carbon
    {
        $raw = $request->string('month')->toString();

        if ($raw !== '' && preg_match('/^\d{4}-\d{2}$/', $raw) === 1) {
            return Carbon::createFromFormat('Y-m', $raw)->startOfMonth();
        }

        return today()->startOfMonth();
    }
}
