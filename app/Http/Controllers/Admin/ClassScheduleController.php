<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ScheduleStatus;
use App\Enums\WeekDay;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClassScheduleRequest;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\ClassSchedule;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Services\ScheduleAdministrationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The weekly class schedule, administrator side.
 *
 * Reads are a filtered, paginated list plus a weekly grid; writes go through
 * ScheduleAdministrationService, which owns the conflict rules and the
 * cross-scope integrity rules. Deletion is not offered: attendance history
 * references a schedule, so a lesson is deactivated instead.
 */
class ClassScheduleController extends Controller
{
    public function index(Request $request, ScheduleAdministrationService $schedules): View
    {
        Gate::authorize('viewAny', ClassSchedule::class);

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
            'term_id' => $request->integer('term_id') ?: null,
            'class_id' => $request->integer('class_id') ?: null,
            'teacher_id' => $request->integer('teacher_id') ?: null,
            'subject_id' => $request->integer('subject_id') ?: null,
            'day_of_week' => $request->integer('day_of_week') ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];

        $query = $schedules->schedules($filters);
        $days = $filters['day_of_week'] !== null
            ? array_values(array_filter(
                WeekDay::allowed(),
                static fn (WeekDay $day): bool => $day->value === $filters['day_of_week'],
            ))
            : WeekDay::allowed();

        return view('admin.class-schedules.index', [
            'schedules' => (clone $query)->paginate(25)->withQueryString(),
            'grid' => $schedules->weeklyGrid($query, $days),
            'filters' => $filters,
            'statuses' => ScheduleStatus::cases(),
            'days' => $days,
            'options' => $this->options($filters),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', ClassSchedule::class);

        $filters = [
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
            'term_id' => $request->integer('term_id') ?: null,
        ];

        return view('admin.class-schedules.form', [
            'schedule' => null,
            'filters' => $filters,
            'statuses' => ScheduleStatus::cases(),
            'options' => $this->options($filters),
            'days' => WeekDay::allowed(),
        ]);
    }

    public function store(
        ClassScheduleRequest $request,
        ScheduleAdministrationService $schedules,
    ): RedirectResponse {
        $schedule = $schedules->create($request->user(), $request->validated());

        return redirect()
            ->route('admin.class-schedules.show', $schedule)
            ->with('status', 'Jadwal pelajaran berhasil disimpan.');
    }

    public function show(ClassSchedule $classSchedule): View
    {
        Gate::authorize('view', $classSchedule);

        $classSchedule->load([
            'academicYear:id,code,label',
            'term:id,label,sequence,starts_on,ends_on,status',
            'class:id,grade_level,name,academic_year_id',
            'subject:id,code,name',
            'teacher:id,legal_name,preferred_name,user_id',
        ]);

        return view('admin.class-schedules.show', [
            'schedule' => $classSchedule,
            'statuses' => ScheduleStatus::cases(),
            'sessions' => $classSchedule->attendanceSessions()
                ->with('markedBy:id,name')
                ->orderByDesc('session_date')
                ->limit(10)
                ->get(),
        ]);
    }

    public function edit(ClassSchedule $classSchedule): View
    {
        Gate::authorize('update', $classSchedule);

        $filters = [
            'academic_year_id' => (int) $classSchedule->academic_year_id,
            'term_id' => (int) $classSchedule->term_id,
        ];

        return view('admin.class-schedules.form', [
            'schedule' => $classSchedule,
            'filters' => $filters,
            'statuses' => ScheduleStatus::cases(),
            'options' => $this->options($filters),
            'days' => WeekDay::allowed(),
        ]);
    }

    public function update(
        ClassScheduleRequest $request,
        ClassSchedule $classSchedule,
        ScheduleAdministrationService $schedules,
    ): RedirectResponse {
        $schedules->update($request->user(), $classSchedule, $request->validated());

        return redirect()
            ->route('admin.class-schedules.show', $classSchedule)
            ->with('status', 'Jadwal pelajaran berhasil diperbarui.');
    }

    public function deactivate(
        Request $request,
        ClassSchedule $classSchedule,
        ScheduleAdministrationService $schedules,
    ): RedirectResponse {
        $schedules->deactivate($request->user(), $classSchedule);

        return back()->with('status', 'Jadwal pelajaran dinonaktifkan.');
    }

    public function reactivate(
        Request $request,
        ClassSchedule $classSchedule,
        ScheduleAdministrationService $schedules,
    ): RedirectResponse {
        $schedules->reactivate($request->user(), $classSchedule);

        return back()->with('status', 'Jadwal pelajaran diaktifkan kembali.');
    }

    /**
     * The select options for the schedule form.
     *
     * Everything is narrowed to the current academic scope, so a form cannot
     * offer a class from a year the chosen semester does not belong to.
     *
     * @param  array{academic_year_id: int|null, term_id: int|null}  $filters
     * @return array<string, array<string, string>>
     */
    private function options(array $filters): array
    {
        $academicYears = AcademicYear::query()
            ->orderByDesc('starts_on')
            ->get(['id', 'code', 'label'])
            ->mapWithKeys(fn (AcademicYear $year): array => [$year->id => $year->label])
            ->all();

        $terms = Term::query()
            ->when($filters['academic_year_id'], fn (Builder $query): Builder => $query->where('academic_year_id', $filters['academic_year_id']))
            ->orderBy('academic_year_id')
            ->orderBy('sequence')
            ->get()
            ->mapWithKeys(fn (Term $term): array => [$term->id => $term->semesterLabel()])
            ->all();

        $classes = ClassModel::query()
            ->when($filters['academic_year_id'], fn (Builder $query): Builder => $query->where('academic_year_id', $filters['academic_year_id']))
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (ClassModel $class): array => [$class->id => $class->getFullName()])
            ->all();

        return [
            'academicYears' => $academicYears,
            'terms' => $terms,
            'classes' => $classes,
            'subjects' => Subject::query()->orderBy('code')->get()
                ->mapWithKeys(fn (Subject $subject): array => [$subject->id => $subject->code.' · '.$subject->name])
                ->all(),
            'teachers' => Teacher::query()->orderBy('legal_name')->get()
                ->mapWithKeys(fn (Teacher $teacher): array => [$teacher->id => $teacher->getDisplayName()])
                ->all(),
        ];
    }
}
