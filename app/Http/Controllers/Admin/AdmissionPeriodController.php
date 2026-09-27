<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdmissionPeriodRequest;
use App\Http\Requests\UpdateAdmissionPeriodRequest;
use App\Models\AcademicYear;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPeriod;
use App\Services\AdmissionPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdmissionPeriodController extends Controller
{
    public function index(Request $request): View
    {
        $periods = AdmissionPeriod::query()
            ->with('academicYear:id,code,label')
            ->withCount('applications')
            ->latest('opens_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.ppdb.periods.index', [
            'periods' => $periods,
            'hasAcademicYear' => AcademicYear::query()->exists(),
        ]);
    }

    public function create(): View
    {
        return view('admin.ppdb.periods.form', [
            'admissionPeriod' => null,
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'code', 'label']),
        ]);
    }

    public function show(AdmissionPeriod $admissionPeriod): View
    {
        Gate::authorize('view', $admissionPeriod);

        $admissionPeriod->load('academicYear');
        $admissionPeriod->loadCount('applications');

        return view('admin.ppdb.periods.show', [
            'admissionPeriod' => $admissionPeriod,
            'statusCounts' => AdmissionApplication::query()
                ->where('admission_period_id', $admissionPeriod->getKey())
                ->select('status', DB::raw('count(*) as aggregate'))
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(static fn (int|string $count): int => (int) $count),
        ]);
    }

    public function store(StoreAdmissionPeriodRequest $request, AdmissionPeriodService $periods): RedirectResponse
    {
        $periods->create($request->user(), $request->validated());

        return redirect()->route('admin.ppdb.periods.index')->with('status', 'Periode PPDB dibuat.');
    }

    public function edit(AdmissionPeriod $admissionPeriod): View
    {
        return view('admin.ppdb.periods.form', [
            'admissionPeriod' => $admissionPeriod,
            'academicYears' => AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'code', 'label']),
        ]);
    }

    public function update(UpdateAdmissionPeriodRequest $request, AdmissionPeriod $admissionPeriod, AdmissionPeriodService $periods): RedirectResponse
    {
        $periods->update($request->user(), $admissionPeriod, $request->validated());

        return redirect()->route('admin.ppdb.periods.index')->with('status', 'Periode PPDB diperbarui.');
    }

    public function open(AdmissionPeriod $admissionPeriod, AdmissionPeriodService $periods): RedirectResponse
    {
        $periods->open(request()->user(), $admissionPeriod);

        return back()->with('status', 'Periode PPDB dibuka.');
    }

    public function close(AdmissionPeriod $admissionPeriod, AdmissionPeriodService $periods): RedirectResponse
    {
        $periods->close(request()->user(), $admissionPeriod);

        return back()->with('status', 'Periode PPDB ditutup.');
    }

    public function archive(AdmissionPeriod $admissionPeriod, AdmissionPeriodService $periods): RedirectResponse
    {
        $periods->archive(request()->user(), $admissionPeriod);

        return back()->with('status', 'Periode PPDB diarsipkan.');
    }
}
