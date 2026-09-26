<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeachingAssignmentRequest;
use App\Http\Requests\Admin\UpdateTeachingAssignmentRequest;
use App\Http\Requests\Admin\UpdateTeachingAssignmentStatusRequest;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Services\AssignmentAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TeachingAssignmentController extends Controller
{
    public function index(Request $request, AssignmentAdministrationService $assignments): View
    {
        Gate::authorize('viewAny', TeachingAssignment::class);

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'teacher_id' => $request->integer('teacher_id') ?: null,
            'class_id' => $request->integer('class_id') ?: null,
            'subject_id' => $request->integer('subject_id') ?: null,
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
        ];

        return view('admin.teaching-assignments.index', [
            'assignments' => $assignments->teachingAssignments($filters)->paginate(20)->withQueryString(),
            'statuses' => AssignmentStatus::cases(),
            'teachers' => Teacher::query()->orderBy('legal_name')->get(['id', 'legal_name', 'employment_status']),
            'classes' => ClassModel::query()->with('academicYear:id,code')->orderByDesc('academic_year_id')->orderBy('grade_level')->get(['id', 'academic_year_id', 'grade_level', 'name']),
            'subjects' => Subject::query()->orderBy('code')->get(['id', 'code', 'name']),
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'code', 'label']),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', TeachingAssignment::class);

        $selectedClassId = $request->integer('class_id') ?: null;
        $classes = ClassModel::query()->with('academicYear:id,code')->orderByDesc('academic_year_id')->orderBy('grade_level')->get(['id', 'academic_year_id', 'grade_level', 'name']);
        $selectedClass = $classes->firstWhere('id', $selectedClassId);

        return view('admin.teaching-assignments.form', [
            'assignment' => null,
            'statuses' => AssignmentStatus::cases(),
            'teachers' => Teacher::query()->orderBy('legal_name')->get(['id', 'legal_name', 'employment_status']),
            'classes' => $classes,
            'subjects' => Subject::query()->orderBy('code')->get(['id', 'code', 'name', 'status']),
            'terms' => $this->termsFor($selectedClass?->academic_year_id),
        ]);
    }

    public function store(StoreTeachingAssignmentRequest $request, AssignmentAdministrationService $assignments): RedirectResponse
    {
        $assignments->createTeachingAssignment($request->user(), $request->validated());

        return redirect()
            ->route('admin.teaching-assignments.index')
            ->with('status', 'Assignment mengajar berhasil disimpan.');
    }

    public function edit(TeachingAssignment $teachingAssignment): View
    {
        Gate::authorize('update', $teachingAssignment);

        return view('admin.teaching-assignments.form', [
            'assignment' => $teachingAssignment,
            'statuses' => AssignmentStatus::cases(),
            'teachers' => Teacher::query()->orderBy('legal_name')->get(['id', 'legal_name', 'employment_status']),
            'classes' => ClassModel::query()->with('academicYear:id,code')->orderByDesc('academic_year_id')->orderBy('grade_level')->get(['id', 'academic_year_id', 'grade_level', 'name']),
            'subjects' => Subject::query()->orderBy('code')->get(['id', 'code', 'name', 'status']),
            'terms' => $this->termsFor($teachingAssignment->academic_year_id),
        ]);
    }

    public function update(
        UpdateTeachingAssignmentRequest $request,
        TeachingAssignment $teachingAssignment,
        AssignmentAdministrationService $assignments,
    ): RedirectResponse {
        $assignments->updateTeachingAssignment($request->user(), $teachingAssignment, $request->validated());

        return redirect()
            ->route('admin.teaching-assignments.index')
            ->with('status', 'Assignment mengajar berhasil diperbarui.');
    }

    public function updateStatus(
        UpdateTeachingAssignmentStatusRequest $request,
        TeachingAssignment $teachingAssignment,
        AssignmentAdministrationService $assignments,
    ): RedirectResponse {
        $assignments->changeTeachingAssignmentStatus(
            $request->user(),
            $teachingAssignment,
            AssignmentStatus::from($request->string('status')->toString()),
        );

        return back()->with('status', 'Status assignment diperbarui.');
    }

    /**
     * @return Collection<int, Term>
     */
    private function termsFor(?int $academicYearId)
    {
        if ($academicYearId === null) {
            return Term::query()->orderByDesc('academic_year_id')->get(['id', 'academic_year_id', 'label', 'sequence', 'status']);
        }

        return Term::query()
            ->where('academic_year_id', $academicYearId)
            ->orderBy('sequence')
            ->get(['id', 'academic_year_id', 'label', 'sequence', 'status']);
    }
}
