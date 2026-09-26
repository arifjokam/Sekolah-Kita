<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TermStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTermRequest;
use App\Http\Requests\Admin\UpdateTermRequest;
use App\Http\Requests\Admin\UpdateTermStatusRequest;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Services\AcademicAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TermController extends Controller
{
    public function index(Request $request, AcademicAdministrationService $academic): View
    {
        Gate::authorize('viewAny', Term::class);

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
        ];

        return view('admin.terms.index', [
            'terms' => $academic->terms($filters)->paginate(20)->withQueryString(),
            'statuses' => TermStatus::cases(),
            'academicYears' => $this->academicYears(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Term::class);

        return view('admin.terms.form', [
            'term' => null,
            'statuses' => TermStatus::cases(),
            'academicYears' => $this->academicYears(),
        ]);
    }

    public function store(StoreTermRequest $request, AcademicAdministrationService $academic): RedirectResponse
    {
        $academic->createTerm($request->user(), $request->validated());

        return redirect()
            ->route('admin.terms.index')
            ->with('status', 'Term berhasil disimpan.');
    }

    public function edit(Term $term): View
    {
        Gate::authorize('update', $term);

        return view('admin.terms.form', [
            'term' => $term,
            'statuses' => TermStatus::cases(),
            'academicYears' => $this->academicYears(),
        ]);
    }

    public function update(
        UpdateTermRequest $request,
        Term $term,
        AcademicAdministrationService $academic,
    ): RedirectResponse {
        $academic->updateTerm($request->user(), $term, $request->validated());

        return redirect()
            ->route('admin.terms.index')
            ->with('status', 'Term berhasil diperbarui.');
    }

    public function updateStatus(
        UpdateTermStatusRequest $request,
        Term $term,
        AcademicAdministrationService $academic,
    ): RedirectResponse {
        $academic->changeTermStatus($request->user(), $term, TermStatus::from($request->string('status')->toString()));

        return back()->with('status', 'Status term diperbarui.');
    }

    /**
     * @return Collection<int, AcademicYear>
     */
    private function academicYears()
    {
        return AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'code', 'label', 'status']);
    }
}
