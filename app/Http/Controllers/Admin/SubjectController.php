<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubjectRequest;
use App\Http\Requests\Admin\UpdateSubjectRequest;
use App\Http\Requests\Admin\UpdateSubjectStatusRequest;
use App\Models\Subject;
use App\Services\AcademicAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SubjectController extends Controller
{
    /**
     * @var list<string>
     */
    private const STATUSES = ['active', 'inactive'];

    public function index(Request $request, AcademicAdministrationService $academic): View
    {
        Gate::authorize('viewAny', Subject::class);

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];

        return view('admin.subjects.index', [
            'subjects' => $academic->subjects($filters)->paginate(20)->withQueryString(),
            'statuses' => self::STATUSES,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Subject::class);

        return view('admin.subjects.form', [
            'subject' => null,
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(StoreSubjectRequest $request, AcademicAdministrationService $academic): RedirectResponse
    {
        $academic->createSubject($request->user(), $request->validated());

        return redirect()
            ->route('admin.subjects.index')
            ->with('status', 'Mata pelajaran berhasil disimpan.');
    }

    public function show(Subject $subject): View
    {
        Gate::authorize('view', $subject);

        $subject->loadCount('teachingAssignments');
        $subject->load([
            'teachingAssignments' => fn ($query) => $query
                ->with(['teacher', 'class'])
                ->where('status', AssignmentStatus::Active->value)
                ->orderByDesc('academic_year_id')
                ->limit(25),
        ]);

        return view('admin.subjects.show', [
            'subject' => $subject,
            'statuses' => self::STATUSES,
        ]);
    }

    public function edit(Subject $subject): View
    {
        Gate::authorize('update', $subject);

        return view('admin.subjects.form', [
            'subject' => $subject,
            'statuses' => self::STATUSES,
        ]);
    }

    public function update(
        UpdateSubjectRequest $request,
        Subject $subject,
        AcademicAdministrationService $academic,
    ): RedirectResponse {
        $academic->updateSubject($request->user(), $subject, $request->validated());

        return redirect()
            ->route('admin.subjects.index')
            ->with('status', 'Mata pelajaran berhasil diperbarui.');
    }

    public function updateStatus(
        UpdateSubjectStatusRequest $request,
        Subject $subject,
        AcademicAdministrationService $academic,
    ): RedirectResponse {
        $academic->changeSubjectStatus($request->user(), $subject, $request->string('status')->toString());

        return back()->with('status', 'Status mata pelajaran diperbarui.');
    }
}
