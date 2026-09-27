<?php

namespace App\Http\Controllers\Student;

use App\Enums\WeekDay;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Term;
use App\Services\ScheduleAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * A student's own weekly timetable.
 *
 * The class is resolved from the authenticated student's active enrolment, never
 * from a route parameter, so there is no id in the URL that could point at
 * another class. If a student has no enrolment the screen says so instead of
 * showing an empty grid.
 */
class StudentScheduleController extends Controller
{
    public function index(Request $request, ScheduleAdministrationService $schedules): View|RedirectResponse
    {
        $student = $request->user()->student;

        if ($student === null) {
            return redirect()->route('dashboard.siswa')
                ->with('status', 'Data siswa belum terhubung ke akun ini.');
        }

        $enrollment = $student->currentEnrollment();
        $class = $enrollment?->class;
        $activeYear = AcademicYear::getCurrent();
        $termId = $request->integer('term_id') ?: null;
        $date = $this->resolveDate($request);

        $lessons = $class instanceof ClassModel && $activeYear !== null
            ? $schedules->studentWeek($class, (int) $activeYear->getKey(), $termId)
            : collect();

        $todayLessons = $lessons->where('day_of_week', WeekDay::from($date->dayOfWeekIso))->values();
        $next = $schedules->nextLesson($lessons, $date->dayOfWeekIso, $date->format('H:i:s'));

        return view('siswa.jadwal.index', [
            'student' => $student,
            'enrollment' => $enrollment,
            'class' => $class,
            'lessons' => $lessons,
            'grid' => $schedules->weeklyGrid($lessons, WeekDay::allowed()),
            'todayLessons' => $todayLessons,
            'nextLesson' => $next,
            'date' => $date,
            'activeAcademicYear' => $activeYear,
            'terms' => $this->terms($activeYear, $termId),
            'teachers' => $lessons->pluck('teacher')->filter()->unique('id')->values(),
        ]);
    }

    /**
     * @return Collection<int, Term>
     */
    private function terms(?AcademicYear $year, ?int $selected)
    {
        if ($year === null) {
            return collect();
        }

        return Term::query()
            ->where('academic_year_id', $year->getKey())
            ->orderBy('sequence')
            ->get(['id', 'label', 'sequence', 'status']);
    }

    private function resolveDate(Request $request): Carbon
    {
        $raw = $request->string('date')->toString();

        if ($raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return Carbon::parse($raw);
        }

        return today();
    }
}
