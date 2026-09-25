<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdmissionApplicationStatus;
use App\Enums\AdmissionReviewDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignAdmissionReviewerRequest;
use App\Http\Requests\DecideAdmissionApplicationRequest;
use App\Http\Requests\RequestAdmissionRevisionRequest;
use App\Http\Requests\ReviewAdmissionApplicationRequest;
use App\Models\AdmissionApplication;
use App\Models\ClassModel;
use App\Models\User;
use App\Services\AdmissionApplicationService;
use App\Services\AdmissionConversionService;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdmissionApplicationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AdmissionApplication::class);

        $applications = AdmissionApplication::query()
            ->select([
                'id',
                'admission_period_id',
                'assigned_reviewer_user_id',
                'registration_number',
                'status',
                'duplicate_flag',
                'submitted_at',
            ])
            ->with(['period:id,name', 'reviewer:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('period'), fn ($query) => $query->where('admission_period_id', $request->integer('period')))
            ->when($request->filled('reviewer'), fn ($query) => $query->where('assigned_reviewer_user_id', $request->integer('reviewer')))
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.ppdb.applications.index', [
            'applications' => $applications,
            'statuses' => AdmissionApplicationStatus::cases(),
        ]);
    }

    public function show(Request $request, AdmissionApplication $admissionApplication, AuditLogService $audit): View
    {
        Gate::authorize('view', $admissionApplication);
        $canReview = $request->user()?->can('review', $admissionApplication) ?? false;
        $relations = [
            'period:id,name,academic_year_id,opens_at,closes_at,quota',
            'reviewer:id,name',
        ];

        if ($canReview) {
            $relations = array_merge($relations, [
                'guardians',
                'documents.mediaAsset:id,mime_type,processing_status,scan_status,original_filename,deleted_at',
                'reviews.reviewer:id,name',
                'statusHistories.actor:id,name',
            ]);
            $audit->recordOrFail(
                $request->user(),
                'admission_application.viewed',
                'admission_application',
                $admissionApplication->getKey(),
                context: ['scope' => 'assigned_reviewer'],
                request: $request,
            );
        }

        $admissionApplication->load($relations);

        return view('admin.ppdb.applications.show', [
            'admissionApplication' => $admissionApplication,
            'canReview' => $canReview,
            'reviewers' => User::query()
                ->where('role', 'administrator')
                ->where('status', 'active')
                ->whereNotNull('email_verified_at')
                ->orderBy('name')
                ->get(['id', 'name']),
            'classes' => ClassModel::query()
                ->where('academic_year_id', $admissionApplication->period?->academic_year_id)
                ->orderBy('grade_level')
                ->orderBy('name')
                ->get(['id', 'grade_level', 'name', 'capacity', 'status']),
        ]);
    }

    public function assign(AssignAdmissionReviewerRequest $request, AdmissionApplication $admissionApplication, AdmissionApplicationService $applications): RedirectResponse
    {
        $reviewer = User::query()->findOrFail($request->integer('reviewer_user_id'));
        $applications->assignReviewer($request->user(), $admissionApplication, $reviewer);

        return back()->with('status', 'Reviewer ditugaskan.');
    }

    public function start(ReviewAdmissionApplicationRequest $request, AdmissionApplication $admissionApplication, AdmissionApplicationService $applications): RedirectResponse
    {
        $applications->startReview($request->user(), $admissionApplication);

        return back()->with('status', 'Pengajuan mulai ditinjau.');
    }

    public function requestRevision(RequestAdmissionRevisionRequest $request, AdmissionApplication $admissionApplication, AdmissionApplicationService $applications): RedirectResponse
    {
        $applications->requestRevision(
            $request->user(),
            $admissionApplication,
            $request->string('public_message')->toString(),
            $request->input('deadline'),
        );

        return back()->with('status', 'Permintaan revisi dikirim.');
    }

    public function decide(DecideAdmissionApplicationRequest $request, AdmissionApplication $admissionApplication, AdmissionApplicationService $applications): RedirectResponse
    {
        $targetClass = $request->filled('target_class_id')
            ? ClassModel::query()->findOrFail($request->integer('target_class_id'))
            : null;
        $applications->decide(
            $request->user(),
            $admissionApplication,
            AdmissionReviewDecision::from($request->string('decision')->toString()),
            $targetClass,
            $request->input('internal_note'),
        );

        return back()->with('status', 'Keputusan tersimpan.');
    }

    public function convert(AdmissionApplication $admissionApplication, AdmissionConversionService $conversion): RedirectResponse
    {
        $conversion->convert(request()->user(), $admissionApplication);

        return back()->with('status', 'Data siswa berhasil dibuat dari pengajuan.');
    }
}
