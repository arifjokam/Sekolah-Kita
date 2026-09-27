<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TeacherEmploymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Requests\Admin\UpdateTeacherEmploymentStatusRequest;
use App\Http\Requests\Admin\UpdateTeacherRequest;
use App\Models\Teacher;
use App\Services\TeacherAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Administrator CRUD for the teacher master record.
 *
 * The public directory keeps its own narrow form (Admin\TeacherDirectoryController);
 * this controller owns the staff record: employment, contact and assignment
 * linkage, none of which the public profile may touch.
 */
class TeacherController extends Controller
{
    public function index(Request $request, TeacherAdministrationService $teachers): View
    {
        Gate::authorize('viewAny', Teacher::class);

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];

        return view('admin.teachers.index', [
            'teachers' => $teachers->listing($filters)->paginate(20)->withQueryString(),
            'statuses' => TeacherEmploymentStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Teacher::class);

        return view('admin.teachers.form', [
            'teacher' => null,
            'statuses' => TeacherEmploymentStatus::cases(),
        ]);
    }

    public function store(StoreTeacherRequest $request, TeacherAdministrationService $teachers): RedirectResponse
    {
        $teacher = $teachers->create($request->user(), $request->validated());

        return redirect()
            ->route('admin.teachers.show', $teacher)
            ->with('status', 'Data guru berhasil disimpan.');
    }

    public function show(Request $request, Teacher $teacher): View
    {
        Gate::authorize('view', $teacher);

        $teacher->load([
            'user:id,name,email,status',
            'activeClassTeacherAssignments.class:id,grade_level,name,academic_year_id',
            'activeClassTeacherAssignments.class.academicYear:id,code,label',
            'activeTeachingAssignments.class:id,grade_level,name',
            'activeTeachingAssignments.subject:id,code,name',
        ]);

        return view('admin.teachers.show', [
            'teacher' => $teacher,
            'statuses' => TeacherEmploymentStatus::cases(),
            'classCount' => $teacher->activeTeachingAssignments->pluck('class_id')->unique()->count(),
        ]);
    }

    public function edit(Teacher $teacher): View
    {
        Gate::authorize('update', $teacher);

        return view('admin.teachers.form', [
            'teacher' => $teacher,
            'statuses' => TeacherEmploymentStatus::cases(),
        ]);
    }

    public function update(
        UpdateTeacherRequest $request,
        Teacher $teacher,
        TeacherAdministrationService $teachers,
    ): RedirectResponse {
        $teachers->update($request->user(), $teacher, $request->validated());

        return redirect()
            ->route('admin.teachers.show', $teacher)
            ->with('status', 'Data guru berhasil diperbarui.');
    }

    public function updateEmploymentStatus(
        UpdateTeacherEmploymentStatusRequest $request,
        Teacher $teacher,
        TeacherAdministrationService $teachers,
    ): RedirectResponse {
        $status = TeacherEmploymentStatus::from($request->string('employment_status')->toString());
        $teachers->changeEmploymentStatus($request->user(), $teacher, $status);

        return back()->with('status', 'Status kepegawaian guru diperbarui.');
    }
}
