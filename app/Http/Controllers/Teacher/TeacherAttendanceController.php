<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\SaveAttendanceSessionRequest;
use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\ClassModel;
use App\Models\ClassSchedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Term;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Digital attendance, teacher side.
 *
 * Three screens, all scoped to the authenticated teacher:
 *
 *  - the register ("Absensi"): today's lessons and recent sessions;
 *  - the sheet: one session, filled from that class's current enrolment;
 *  - the monthly report, aggregated per student for a month.
 *
 * A session id in the URL never grants access: AttendanceSessionPolicy re-checks
 * that the session names this teacher or a class they are the homeroom teacher
 * of, and the save request additionally refuses any student who is not on the
 * class's roster.
 */
class TeacherAttendanceController extends Controller
{
    public function index(Request $request, AttendanceService $attendance): View|RedirectResponse
    {
        $teacher = $this->teacher($request);

        if (! $teacher instanceof Teacher) {
            return redirect()->route('dashboard.guru')->with('status', 'Data guru belum terhubung ke akun ini.');
        }

        $date = $this->resolveDate($request);
        $termId = $request->integer('term_id') ?: null;
        $lessons = $attendance->todaysLessons($teacher, $termId, $date);

        /*
         * Everything this page renders is fetched here rather than in the Blade.
         * The previous version ran one AttendanceSession query per lesson inside
         * a @foreach and a full Term scan with no eager load for the year label —
         * a teacher with six lessons paid six extra round trips on every page
         * view, and the semester dropdown read every term of every academic year.
         */
        $sessionBySchedule = $lessons->isEmpty()
            ? collect()
            : AttendanceSession::query()
                ->whereIn('schedule_id', $lessons->modelKeys())
                ->where('session_date', $date->toDateString())
                ->get()
                ->keyBy('schedule_id');

        $terms = Term::query()
            ->with('academicYear:id,code,label,starts_on,ends_on,status')
            ->orderByDesc('academic_year_id')
            ->orderBy('sequence')
            ->get();

        return view('guru.absensi.index', [
            'teacher' => $teacher,
            'date' => $date,
            'lessons' => $lessons,
            'sessionBySchedule' => $sessionBySchedule,
            'terms' => $terms,
            'selectedTermId' => $termId,
            'sessions' => $attendance->sessionsForTeacher($teacher, $termId)
                ->limit(20)
                ->get(),
            'activeAcademicYear' => AcademicYear::getCurrent(),
        ]);
    }

    public function show(
        Request $request,
        AttendanceSession $session,
        AttendanceService $attendance,
    ): View|RedirectResponse {
        $teacher = $this->teacher($request);

        if (! $teacher instanceof Teacher) {
            return redirect()->route('dashboard.guru')->with('status', 'Data guru belum terhubung ke akun ini.');
        }

        $this->authorize('view', $session);

        // markedBy is read on the sheet header, so it is loaded here rather than lazily
        // dereferenced once per render.
        $session->loadMissing([
            'class:id,grade_level,name,academic_year_id',
            'subject:id,code,name',
            'term:id,label,sequence',
            'markedBy:id,name',
        ]);

        return view('guru.absensi.sheet', [
            'teacher' => $teacher,
            'session' => $session,
            'roster' => $attendance->roster($session),
            'existing' => $attendance->existingRecords($session),
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    /**
     * Open today's lesson and go straight to its sheet.
     *
     * This is the "Mulai Absensi" action. The session is created inside a locked
     * transaction keyed on (schedule, date), so a double click cannot produce two
     * sessions for the same lesson.
     */
    public function open(
        Request $request,
        ClassSchedule $classSchedule,
        AttendanceService $attendance,
    ): RedirectResponse {
        $teacher = $this->teacher($request);

        if (! $teacher instanceof Teacher) {
            return redirect()->route('dashboard.guru')->with('status', 'Data guru belum terhubung ke akun ini.');
        }

        $session = $attendance->openSession($request->user(), $classSchedule, $this->resolveDate($request));

        return redirect()->route('guru.absensi.show', $session);
    }

    public function store(
        SaveAttendanceSessionRequest $request,
        AttendanceSession $session,
        AttendanceService $attendance,
    ): RedirectResponse {
        $attendance->save(
            $request->user(),
            $session,
            (array) $request->validated()['records'],
            false,
        );

        return redirect()
            ->route('guru.absensi.index')
            ->with('status', 'Absensi '.$session->class?->getFullName().' tersimpan.');
    }

    /**
     * Correct a sheet that was already submitted.
     *
     * A separate route with a separate policy ability, so a correction is always
     * an explicit action and always lands in the audit log as one.
     */
    public function correct(
        SaveAttendanceSessionRequest $request,
        AttendanceSession $session,
        AttendanceService $attendance,
    ): RedirectResponse {
        $attendance->save(
            $request->user(),
            $session,
            (array) $request->validated()['records'],
            true,
        );

        return redirect()
            ->route('guru.absensi.show', $session)
            ->with('status', 'Koreksi absensi tersimpan dan tercatat pada audit log.');
    }

    public function report(Request $request, AttendanceService $attendance): View|RedirectResponse
    {
        $teacher = $this->teacher($request);

        if (! $teacher instanceof Teacher) {
            return redirect()->route('dashboard.guru')->with('status', 'Data guru belum terhubung ke akun ini.');
        }

        $classIds = $attendance->teacherClassIds($teacher);
        $classes = $classIds === []
            ? collect()
            : ClassModel::query()
                ->whereIn('id', $classIds)
                ->orderBy('grade_level')
                ->orderBy('name')
                ->get(['id', 'grade_level', 'name']);

        $month = $this->resolveMonth($request);
        $classId = $request->integer('class_id') ?: null;
        $subjectId = $request->integer('subject_id') ?: null;
        $class = $classId !== null ? $classes->firstWhere('id', $classId) : null;

        $result = $class instanceof ClassModel
            ? $attendance->monthlyClassReport($teacher, $class, $month, $subjectId)
            : ['students' => [], 'totals' => [], 'session_count' => 0];

        return view('guru.absensi.laporan', [
            'teacher' => $teacher,
            'classes' => $classes,
            'class' => $class,
            'month' => $month,
            'subjectId' => $subjectId,
            'subjects' => $teacher->activeTeachingAssignments()
                ->with('subject:id,code,name')
                ->get()
                ->pluck('subject')
                ->filter()
                ->unique('id')
                ->values(),
            'result' => $result,
            'statuses' => AttendanceStatus::cases(),
        ]);
    }

    /**
     * A print-friendly rendering of the same report.
     *
     * The report is not exported to a binary format: a school's monthly attendance
     * sheet is a printed page, and a print stylesheet keeps that a one-click
     * operation instead of a new dependency.
     */
    public function printReport(Request $request, AttendanceService $attendance): View|RedirectResponse
    {
        return $this->report($request, $attendance);
    }

    private function teacher(Request $request): ?Teacher
    {
        $teacher = $request->user()->teacher;

        return $teacher instanceof Teacher && $teacher->isActive() ? $teacher : null;
    }

    private function authorize(string $ability, AttendanceSession $session): void
    {
        abort_unless(request()->user()->can($ability, $session), 403);
    }

    private function resolveDate(Request $request): Carbon
    {
        $raw = $request->string('date')->toString();

        if ($raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return Carbon::parse($raw);
        }

        return today();
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
