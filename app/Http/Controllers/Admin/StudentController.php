<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClassStatus;
use App\Enums\StudentLifecycleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStudentGuardianRequest;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentLifecycleStatusRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Services\StudentAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request, StudentAdministrationService $students): View
    {
        Gate::authorize('viewAny', Student::class);

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'class_id' => $request->integer('class_id') ?: null,
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
        ];

        return view('admin.students.index', [
            'students' => $students->listing($filters)->paginate(20)->withQueryString(),
            'statuses' => StudentLifecycleStatus::cases(),
            'classes' => $this->classOptions(),
            'academicYears' => $this->academicYearOptions(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Student::class);

        return view('admin.students.form', [
            'student' => null,
            'statuses' => StudentLifecycleStatus::cases(),
            'classes' => $this->classOptions(),
        ]);
    }

    public function store(StoreStudentRequest $request, StudentAdministrationService $students): RedirectResponse
    {
        $student = $students->create(
            $request->user(),
            $request->validated(),
            $request->enrollmentPayload(),
            $request->guardianPayload(),
        );

        return redirect()
            ->route('admin.students.show', $student)
            ->with('status', 'Data siswa berhasil disimpan.');
    }

    public function show(Student $student): View
    {
        Gate::authorize('view', $student);

        $student->load([
            'user:id,name,email,status',
            'guardians' => fn ($query) => $query->orderByDesc('student_guardians.is_primary_contact')->orderBy('student_guardians.priority_order'),
            'enrollments' => fn ($query) => $query
                ->with(['class:id,grade_level,name', 'academicYear:id,code,label'])
                ->orderByDesc('enrolled_on'),
        ]);

        return view('admin.students.show', [
            'student' => $student,
            'statuses' => StudentLifecycleStatus::cases(),
            'classes' => $this->classOptions(),
            'activeLink' => $student->guardians->first(
                fn ($link): bool => $link->pivot->ends_on === null && $link->pivot->is_primary_contact,
            ),
        ]);
    }

    public function edit(Student $student): View
    {
        Gate::authorize('update', $student);

        return view('admin.students.form', [
            'student' => $student,
            'statuses' => StudentLifecycleStatus::cases(),
            'classes' => $this->classOptions(),
        ]);
    }

    public function update(
        UpdateStudentRequest $request,
        Student $student,
        StudentAdministrationService $students,
    ): RedirectResponse {
        $students->update($request->user(), $student, $request->validated());

        return redirect()
            ->route('admin.students.show', $student)
            ->with('status', 'Data siswa berhasil diperbarui.');
    }

    public function updateLifecycleStatus(
        UpdateStudentLifecycleStatusRequest $request,
        Student $student,
        StudentAdministrationService $students,
    ): RedirectResponse {
        $status = StudentLifecycleStatus::from($request->string('status')->toString());
        $students->changeLifecycleStatus($request->user(), $student, $status);

        return back()->with('status', 'Status siswa diperbarui.');
    }

    public function storeGuardian(
        StoreStudentGuardianRequest $request,
        Student $student,
        StudentAdministrationService $students,
    ): RedirectResponse {
        $students->attachGuardian($request->user(), $student, $request->validated());

        return back()->with('status', 'Wali siswa berhasil ditambahkan.');
    }

    public function destroyGuardian(
        Student $student,
        StudentGuardian $studentGuardian,
        StudentAdministrationService $students,
    ): RedirectResponse {
        $students->detachGuardian(request()->user(), $student, $studentGuardian);

        return back()->with('status', 'Wali siswa tidak lagi ditautkan.');
    }

    /**
     * @return Collection<int, ClassModel>
     */
    private function classOptions()
    {
        return ClassModel::query()
            ->with('academicYear:id,code,label')
            ->where('status', '!=', ClassStatus::Closed->value)
            ->orderByDesc('academic_year_id')
            ->orderBy('grade_level')
            ->orderBy('name')
            ->get(['id', 'academic_year_id', 'grade_level', 'name', 'capacity', 'status']);
    }

    /**
     * @return Collection<int, AcademicYear>
     */
    private function academicYearOptions()
    {
        return AcademicYear::query()
            ->orderByDesc('starts_on')
            ->get(['id', 'code', 'label', 'status']);
    }
}
