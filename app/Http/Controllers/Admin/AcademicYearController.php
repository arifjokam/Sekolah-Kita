<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicYearStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAcademicYearRequest;
use App\Http\Requests\Admin\UpdateAcademicYearRequest;
use App\Http\Requests\Admin\UpdateAcademicYearStatusRequest;
use App\Models\AcademicYear;
use App\Services\AcademicAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AcademicYearController extends Controller
{
    public function index(Request $request, AcademicAdministrationService $academic): View
    {
        Gate::authorize('viewAny', AcademicYear::class);

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];

        return view('admin.academic-years.index', [
            'academicYears' => $academic->years($filters)->paginate(20)->withQueryString(),
            'statuses' => AcademicYearStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', AcademicYear::class);

        return view('admin.academic-years.form', [
            'academicYear' => null,
            'statuses' => AcademicYearStatus::cases(),
        ]);
    }

    public function store(StoreAcademicYearRequest $request, AcademicAdministrationService $academic): RedirectResponse
    {
        $academic->createYear($request->user(), $request->validated());

        return redirect()
            ->route('admin.academic-years.index')
            ->with('status', 'Tahun ajaran berhasil disimpan.');
    }

    public function show(AcademicYear $academicYear): View
    {
        Gate::authorize('view', $academicYear);

        $academicYear->loadCount(['terms', 'classes', 'enrollments', 'admissionPeriods']);
        $academicYear->load([
            'terms' => fn ($query) => $query->orderBy('sequence'),
            'classes' => fn ($query) => $query->orderBy('grade_level')->orderBy('name'),
        ]);

        return view('admin.academic-years.show', [
            'academicYear' => $academicYear,
            'statuses' => AcademicYearStatus::cases(),
        ]);
    }

    public function edit(AcademicYear $academicYear): View
    {
        Gate::authorize('update', $academicYear);

        return view('admin.academic-years.form', [
            'academicYear' => $academicYear,
            'statuses' => AcademicYearStatus::cases(),
        ]);
    }

    public function update(
        UpdateAcademicYearRequest $request,
        AcademicYear $academicYear,
        AcademicAdministrationService $academic,
    ): RedirectResponse {
        $academic->updateYear($request->user(), $academicYear, $request->validated());

        return redirect()
            ->route('admin.academic-years.show', $academicYear)
            ->with('status', 'Tahun ajaran berhasil diperbarui.');
    }

    public function updateStatus(
        UpdateAcademicYearStatusRequest $request,
        AcademicYear $academicYear,
        AcademicAdministrationService $academic,
    ): RedirectResponse {
        $academic->changeYearStatus(
            $request->user(),
            $academicYear,
            AcademicYearStatus::from($request->string('status')->toString()),
        );

        return back()->with('status', 'Status tahun ajaran diperbarui.');
    }
}
