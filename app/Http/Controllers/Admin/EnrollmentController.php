<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClassStatus;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEnrollmentRequest;
use App\Http\Requests\Admin\TransferEnrollmentRequest;
use App\Http\Requests\Admin\UpdateEnrollmentRequest;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\AuditLogService;
use App\Services\EnrollmentService;
use App\Services\StudentAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Enrollment::class);

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'class_id' => $request->integer('class_id') ?: null,
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
        ];

        $search = $filters['search'];
        $like = '%'.addcslashes($search, '%_\\').'%';

        $enrollments = Enrollment::query()
            ->with([
                'student:id,legal_name,preferred_name,student_number,status',
                'class:id,grade_level,name,academic_year_id',
                'academicYear:id,code,label',
            ])
            ->when($search !== '', function ($query) use ($like): void {
                $query->whereHas('student', fn ($query) => $query
                    ->where(fn ($query) => $query
                        ->where('legal_name', 'like', $like)
                        ->orWhere('preferred_name', 'like', $like)
                        ->orWhere('student_number', 'like', $like)));
            })
            ->when($filters['status'] !== null, fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['class_id'] !== null, fn ($query) => $query->where('class_id', $filters['class_id']))
            ->when($filters['academic_year_id'] !== null, fn ($query) => $query->where('academic_year_id', $filters['academic_year_id']))
            ->orderByDesc('academic_year_id')
            ->orderBy('class_id')
            ->orderBy('student_id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.enrollments.index', [
            'enrollments' => $enrollments,
            'statuses' => EnrollmentStatus::cases(),
            'classes' => $this->classes(),
            'academicYears' => $this->academicYears(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Enrollment::class);

        return view('admin.enrollments.form', [
            'enrollment' => null,
            'classes' => $this->classes(),
            'students' => Student::query()
                ->orderBy('legal_name')
                ->get(['id', 'legal_name', 'preferred_name', 'student_number', 'status']),
        ]);
    }

    public function store(StoreEnrollmentRequest $request, StudentAdministrationService $students): RedirectResponse
    {
        $student = Student::query()->findOrFail($request->integer('student_id'));
        $enrollment = $students->enroll($request->user(), $student, $request->validated());

        return redirect()
            ->route('admin.enrollments.show', $enrollment)
            ->with('status', 'Enrollment berhasil dibuat.');
    }

    public function show(Enrollment $enrollment): View
    {
        Gate::authorize('view', $enrollment);

        $enrollment->load(['student', 'class.academicYear', 'academicYear']);

        return view('admin.enrollments.show', [
            'enrollment' => $enrollment,
            'classes' => $this->classes(),
        ]);
    }

    public function edit(Enrollment $enrollment): View
    {
        Gate::authorize('update', $enrollment);

        return view('admin.enrollments.form', [
            'enrollment' => $enrollment,
            'classes' => $this->classes(),
            'students' => collect([$enrollment->student]),
        ]);
    }

    public function update(
        UpdateEnrollmentRequest $request,
        Enrollment $enrollment,
        EnrollmentService $enrollments,
    ): RedirectResponse {
        Gate::forUser($request->user())->authorize('update', $enrollment);

        DB::transaction(function () use ($request, $enrollment): void {
            $locked = Enrollment::query()->whereKey($enrollment->getKey())->lockForUpdate()->firstOrFail();
            $class = ClassModel::query()->findOrFail($request->integer('class_id'));

            if ((int) $class->academic_year_id !== (int) $locked->academic_year_id) {
                throw ValidationException::withMessages([
                    'class_id' => 'Kelas harus berada pada tahun ajaran yang sama dengan enrollment.',
                ]);
            }

            $locked->fill([
                'class_id' => $class->getKey(),
                'status' => EnrollmentStatus::from($request->string('status')->toString()),
                'enrolled_on' => $request->date('enrolled_on'),
                'effective_from' => $request->date('effective_from'),
                'effective_to' => $request->date('effective_to'),
                'completed_on' => $request->date('completed_on'),
                'transition_reason' => $request->input('transition_reason'),
            ])->save();
        }, attempts: 3);

        $this->auditEnrollmentUpdate($request, $enrollment);

        return redirect()
            ->route('admin.enrollments.show', $enrollment)
            ->with('status', 'Enrollment berhasil diperbarui.');
    }

    public function transfer(
        TransferEnrollmentRequest $request,
        Enrollment $enrollment,
        EnrollmentService $enrollments,
    ): RedirectResponse {
        $targetClass = ClassModel::query()->findOrFail($request->integer('class_id'));
        $enrollments->transfer(
            $request->user(),
            $enrollment,
            $targetClass,
            $request->date('effective_on'),
            $request->input('transition_reason'),
        );

        return back()->with('status', 'Siswa berhasil dipindahkan ke kelas tujuan.');
    }

    private function auditEnrollmentUpdate(Request $request, Enrollment $enrollment): void
    {
        app(AuditLogService::class)->recordOrFail(
            $request->user(),
            'enrollment.updated',
            'enrollment',
            $enrollment->getKey(),
            context: ['class_id' => $enrollment->class_id, 'status' => $enrollment->status?->value],
        );
    }

    /**
     * @return Collection<int, ClassModel>
     */
    private function classes()
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
    private function academicYears()
    {
        return AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'code', 'label', 'status']);
    }
}
