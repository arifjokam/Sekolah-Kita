<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClassTeacherAssignmentRequest;
use App\Http\Requests\Admin\UpdateClassTeacherAssignmentRequest;
use App\Http\Requests\Admin\UpdateClassTeacherAssignmentStatusRequest;
use App\Models\ClassModel;
use App\Models\ClassTeacherAssignment;
use App\Models\Teacher;
use App\Services\AssignmentAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ClassTeacherAssignmentController extends Controller
{
    public function index(Request $request, AssignmentAdministrationService $assignments): View
    {
        Gate::authorize('viewAny', ClassTeacherAssignment::class);

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'teacher_id' => $request->integer('teacher_id') ?: null,
            'class_id' => $request->integer('class_id') ?: null,
        ];

        return view('admin.class-teacher-assignments.index', [
            'assignments' => $assignments->classTeacherAssignments($filters)->paginate(20)->withQueryString(),
            'statuses' => AssignmentStatus::cases(),
            'teachers' => Teacher::query()->orderBy('legal_name')->get(['id', 'legal_name', 'employment_status']),
            'classes' => ClassModel::query()->with('academicYear:id,code')->orderByDesc('academic_year_id')->orderBy('grade_level')->get(['id', 'academic_year_id', 'grade_level', 'name']),
            'filters' => $filters,
        ]);
    }

    public function show(ClassTeacherAssignment $classTeacherAssignment): View
    {
        Gate::authorize('view', $classTeacherAssignment);

        $classTeacherAssignment->load(['teacher', 'class.academicYear']);

        return view('admin.class-teacher-assignments.show', [
            'assignment' => $classTeacherAssignment,
            'statuses' => AssignmentStatus::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', ClassTeacherAssignment::class);

        return view('admin.class-teacher-assignments.form', [
            'assignment' => null,
            'statuses' => AssignmentStatus::cases(),
            'teachers' => Teacher::query()->orderBy('legal_name')->get(['id', 'legal_name', 'employment_status']),
            'classes' => ClassModel::query()->with('academicYear:id,code')->orderByDesc('academic_year_id')->orderBy('grade_level')->get(['id', 'academic_year_id', 'grade_level', 'name']),
        ]);
    }

    public function store(StoreClassTeacherAssignmentRequest $request, AssignmentAdministrationService $assignments): RedirectResponse
    {
        $assignments->createClassTeacherAssignment($request->user(), $request->validated());

        return redirect()
            ->route('admin.class-teacher-assignments.index')
            ->with('status', 'Penugasan wali kelas berhasil disimpan.');
    }

    public function edit(ClassTeacherAssignment $classTeacherAssignment): View
    {
        Gate::authorize('update', $classTeacherAssignment);

        return view('admin.class-teacher-assignments.form', [
            'assignment' => $classTeacherAssignment,
            'statuses' => AssignmentStatus::cases(),
            'teachers' => Teacher::query()->orderBy('legal_name')->get(['id', 'legal_name', 'employment_status']),
            'classes' => ClassModel::query()->with('academicYear:id,code')->orderByDesc('academic_year_id')->orderBy('grade_level')->get(['id', 'academic_year_id', 'grade_level', 'name']),
        ]);
    }

    public function update(
        UpdateClassTeacherAssignmentRequest $request,
        ClassTeacherAssignment $classTeacherAssignment,
        AssignmentAdministrationService $assignments,
    ): RedirectResponse {
        $assignments->updateClassTeacherAssignment($request->user(), $classTeacherAssignment, $request->validated());

        return redirect()
            ->route('admin.class-teacher-assignments.index')
            ->with('status', 'Penugasan wali kelas berhasil diperbarui.');
    }

    public function updateStatus(
        UpdateClassTeacherAssignmentStatusRequest $request,
        ClassTeacherAssignment $classTeacherAssignment,
        AssignmentAdministrationService $assignments,
    ): RedirectResponse {
        $assignments->changeClassTeacherAssignmentStatus(
            $request->user(),
            $classTeacherAssignment,
            AssignmentStatus::from($request->string('status')->toString()),
        );

        return back()->with('status', 'Status penugasan diperbarui.');
    }
}
