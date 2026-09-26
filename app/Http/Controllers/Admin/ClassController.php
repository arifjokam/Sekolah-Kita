<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClassRequest;
use App\Http\Requests\Admin\UpdateClassRequest;
use App\Http\Requests\Admin\UpdateClassStatusRequest;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Services\AcademicAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClassController extends Controller
{
    public function index(Request $request, AcademicAdministrationService $academic): View
    {
        Gate::authorize('viewAny', ClassModel::class);

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
        ];

        return view('admin.classes.index', [
            'classes' => $academic->classes($filters)->paginate(20)->withQueryString(),
            'statuses' => ClassStatus::cases(),
            'academicYears' => $this->academicYears(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', ClassModel::class);

        return view('admin.classes.form', [
            'class' => null,
            'statuses' => ClassStatus::cases(),
            'academicYears' => $this->academicYears(),
        ]);
    }

    public function store(StoreClassRequest $request, AcademicAdministrationService $academic): RedirectResponse
    {
        $class = $academic->createClass($request->user(), $request->validated());

        return redirect()
            ->route('admin.classes.show', $class)
            ->with('status', 'Kelas berhasil disimpan.');
    }

    public function show(ClassModel $class): View
    {
        Gate::authorize('view', $class);

        $class->load([
            'academicYear:id,code,label',
            'classTeacherAssignments' => fn ($query) => $query
                ->with('teacher:id,legal_name,preferred_name')
                ->orderByDesc('starts_on'),
        ]);

        return view('admin.classes.show', [
            'class' => $class,
            'statuses' => ClassStatus::cases(),
            'students' => Enrollment::query()
                ->with('student:id,legal_name,preferred_name,student_number')
                ->where('class_id', $class->getKey())
                ->where('status', EnrollmentStatus::Active->value)
                ->orderBy('enrolled_on')
                ->get(),
            'teachingAssignments' => $class->teachingAssignments()
                ->with(['teacher:id,legal_name,preferred_name', 'subject:id,code,name'])
                ->orderBy('subject_id')
                ->get(),
        ]);
    }

    public function edit(ClassModel $class): View
    {
        Gate::authorize('update', $class);

        return view('admin.classes.form', [
            'class' => $class,
            'statuses' => ClassStatus::cases(),
            'academicYears' => $this->academicYears(),
        ]);
    }

    public function update(
        UpdateClassRequest $request,
        ClassModel $class,
        AcademicAdministrationService $academic,
    ): RedirectResponse {
        $academic->updateClass($request->user(), $class, $request->validated());

        return redirect()
            ->route('admin.classes.show', $class)
            ->with('status', 'Kelas berhasil diperbarui.');
    }

    public function updateStatus(
        UpdateClassStatusRequest $request,
        ClassModel $class,
        AcademicAdministrationService $academic,
    ): RedirectResponse {
        $academic->changeClassStatus($request->user(), $class, ClassStatus::from($request->string('status')->toString()));

        return back()->with('status', 'Status kelas diperbarui.');
    }

    /**
     * @return Collection<int, AcademicYear>
     */
    private function academicYears()
    {
        return AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'code', 'label', 'status']);
    }
}
