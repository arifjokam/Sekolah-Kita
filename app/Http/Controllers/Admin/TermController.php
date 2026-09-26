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

/**
 * Semesters inside an academic year.
 *
 * The class, the table and the route names stay `Term`: a term is the existing
 * domain concept, and renaming it would mean a schema migration and a
 * re-authorisation pass for no user benefit. Only the display copy says
 * "Semester", because that is what a school says.
 */
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

    public function create(Request $request): View
    {
        Gate::authorize('create', Term::class);

        $academicYearId = $request->integer('academic_year_id')
            ?: (int) (AcademicYear::getCurrent()?->getKey() ?? 0);

        return view('admin.terms.form', [
            'term' => null,
            'statuses' => TermStatus::cases(),
            'academicYears' => $this->academicYears(),
            ...$this->formDefaults($academicYearId),
        ]);
    }

    public function store(StoreTermRequest $request, AcademicAdministrationService $academic): RedirectResponse
    {
        $academic->createTerm($request->user(), $request->validated());

        return redirect()
            ->route('admin.terms.index')
            ->with('status', 'Semester berhasil disimpan.');
    }

    public function show(Term $term): View
    {
        Gate::authorize('view', $term);

        $term->load('academicYear');
        $term->loadCount('teachingAssignments');

        return view('admin.terms.show', [
            'term' => $term,
            'statuses' => TermStatus::cases(),
            'siblings' => Term::query()
                ->where('academic_year_id', $term->academic_year_id)
                ->whereKeyNot($term->getKey())
                ->orderBy('sequence')
                ->get(),
        ]);
    }

    public function edit(Term $term): View
    {
        Gate::authorize('update', $term);

        return view('admin.terms.form', [
            'term' => $term,
            'statuses' => TermStatus::cases(),
            'academicYears' => $this->academicYears(),
            ...$this->formDefaults((int) $term->academic_year_id),
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
            ->with('status', 'Semester berhasil diperbarui.');
    }

    public function updateStatus(
        UpdateTermStatusRequest $request,
        Term $term,
        AcademicAdministrationService $academic,
    ): RedirectResponse {
        $academic->changeTermStatus($request->user(), $term, TermStatus::from($request->string('status')->toString()));

        return back()->with('status', 'Status semester diperbarui.');
    }

    /**
     * @return Collection<int, AcademicYear>
     */
    private function academicYears()
    {
        return AcademicYear::query()->orderByDesc('starts_on')->get(['id', 'code', 'label', 'status', 'starts_on', 'ends_on']);
    }

    /**
     * Pre-fill the create/edit form with the next free semester number and a
     * date range that continues the previous semester rather than defaulting to
     * the whole calendar year — which used to produce a range that always
     * overlapped the sibling semester and was rejected on save.
     *
     * @return array{nextSequence: int, suggestedStart: string, suggestedEnd: string}
     */
    private function formDefaults(int $academicYearId): array
    {
        $siblings = $academicYearId > 0
            ? Term::query()->where('academic_year_id', $academicYearId)->orderBy('sequence')->get()
            : new Collection;

        $nextSequence = ((int) $siblings->max('sequence')) + 1;
        $previous = $siblings->sortByDesc('sequence')->first();
        $year = $academicYearId > 0 ? AcademicYear::query()->find($academicYearId) : null;

        $suggestedStart = $previous instanceof Term
            ? $previous->ends_on->copy()->addDay()->toDateString()
            : ($year?->starts_on?->toDateString() ?? today()->toDateString());

        $suggestedEnd = $previous instanceof Term
            ? min(
                $previous->ends_on->copy()->addMonths(6)->toDateString(),
                $year?->ends_on?->toDateString() ?? '9999-12-31',
            )
            : ($year?->starts_on?->copy()->addMonths(6)->toDateString() ?? today()->addMonths(6)->toDateString());

        return [
            'nextSequence' => max(1, $nextSequence),
            'suggestedStart' => $suggestedStart,
            'suggestedEnd' => $suggestedEnd,
        ];
    }
}
