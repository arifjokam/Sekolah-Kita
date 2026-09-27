<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\WeekDay;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Teacher;
use App\Models\Term;
use App\Services\ScheduleAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * A teacher's own weekly timetable.
 *
 * Every query is scoped to the authenticated teacher's own teacher id. There is
 * no class or teacher parameter on the route, so there is nothing to tamper
 * with: the only way to change what is shown is to be a different user.
 */
class TeacherScheduleController extends Controller
{
    public function index(Request $request, ScheduleAdministrationService $schedules): View|RedirectResponse
    {
        $teacher = $request->user()->teacher;

        if (! $teacher instanceof Teacher) {
            return redirect()->route('dashboard.guru')
                ->with('status', 'Data guru belum terhubung ke akun ini.');
        }

        $activeYear = AcademicYear::getCurrent();
        $termId = $request->integer('term_id') ?: null;
        $date = $this->resolveDate($request);

        $lessons = $schedules->teacherWeek(
            (int) $teacher->getKey(),
            (int) ($activeYear?->getKey() ?? 0),
            $termId,
        );

        $todayLessons = $lessons
            ->where('day_of_week', WeekDay::from($date->dayOfWeekIso))
            ->values();

        $next = $schedules->nextLesson($lessons, $date->dayOfWeekIso, $date->format('H:i:s'));

        return view('guru.jadwal.index', [
            'teacher' => $teacher,
            'lessons' => $lessons,
            'grid' => $schedules->weeklyGrid($lessons, WeekDay::allowed()),
            'todayLessons' => $todayLessons,
            'nextLesson' => $next,
            'date' => $date,
            'activeAcademicYear' => $activeYear,
            'terms' => $this->terms($activeYear, $termId),
            'homeroomClasses' => $teacher->activeClassTeacherAssignments()
                ->with('class:id,grade_level,name,academic_year_id')
                ->get(),
        ]);
    }

    /**
     * @return Collection<int, Term>
     */
    private function terms(?AcademicYear $year, ?int $selected)
    {
        if (! $year instanceof AcademicYear) {
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
