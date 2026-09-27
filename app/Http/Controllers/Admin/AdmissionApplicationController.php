<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdmissionApplicationStatus;
use App\Enums\AdmissionConversionStatus;
use App\Enums\AdmissionReviewDecision;
use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\AnnounceAdmissionApplicationRequest;
use App\Http\Requests\AssignAdmissionReviewerRequest;
use App\Http\Requests\DecideAdmissionApplicationRequest;
use App\Http\Requests\RequestAdmissionRevisionRequest;
use App\Http\Requests\ReviewAdmissionApplicationRequest;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPeriod;
use App\Models\ClassModel;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\CandidatePortalNotification;
use App\Services\AdmissionApplicationService;
use App\Services\AdmissionConversionService;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdmissionApplicationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AdmissionApplication::class);

        return view('admin.ppdb.applications.index', $this->queue($request, 'admin.ppdb.applications.index'));
    }

    /**
     * The reviewer's working queue.
     *
     * Same records as the full list, ordered so that everything waiting for a
     * decision is reachable in one place and the conversion backlog is visible
     * without a separate screen.
     */
    public function review(Request $request): View
    {
        Gate::authorize('viewAny', AdmissionApplication::class);

        return view('admin.ppdb.applications.review', $this->queue($request, 'admin.ppdb.review.index'));
    }

    /**
     * @return array<string, mixed>
     */
    private function queue(Request $request, string $view): array
    {
        $statusFilter = $request->string('status')->toString();
        $onlyActionable = $request->boolean('actionable');
        $search = trim($request->string('search')->toString());

        $applications = AdmissionApplication::query()
            ->select([
                'id',
                'admission_period_id',
                'assigned_reviewer_user_id',
                'registration_number',
                'applicant_name',
                'status',
                'conversion_status',
                'duplicate_flag',
                'submitted_at',
            ])
            ->with(['period:id,name', 'reviewer:id,name'])
            ->when($statusFilter !== '', fn ($query) => $query->where('status', $statusFilter))
            ->when($onlyActionable, fn ($query) => $query->whereIn('status', [
                AdmissionApplicationStatus::Submitted->value,
                AdmissionApplicationStatus::UnderReview->value,
            ]))
            ->when($request->filled('period'), fn ($query) => $query->where('admission_period_id', $request->integer('period')))
            ->when($request->filled('reviewer'), fn ($query) => $query->where('assigned_reviewer_user_id', $request->integer('reviewer')))
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function ($query) use ($like): void {
                    $query->where('registration_number', 'like', $like)
                        ->orWhere('applicant_name', 'like', $like);
                });
            })
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return [
            'applications' => $applications,
            'statuses' => AdmissionApplicationStatus::cases(),
            'periods' => AdmissionPeriod::query()->orderByDesc('opens_at')->get(['id', 'name']),
            'reviewers' => User::query()
                ->where('role', 'administrator')
                ->where('status', 'active')
                ->whereNotNull('email_verified_at')
                ->orderBy('name')
                ->get(['id', 'name']),
            'summary' => [
                'total' => AdmissionApplication::query()->count(),
                'awaitingReview' => AdmissionApplication::query()
                    ->whereIn('status', [
                        AdmissionApplicationStatus::Submitted->value,
                        AdmissionApplicationStatus::UnderReview->value,
                    ])
                    ->count(),
                'acceptedPendingConversion' => AdmissionApplication::query()
                    ->where('status', AdmissionApplicationStatus::Accepted->value)
                    ->where('conversion_status', '!=', AdmissionConversionStatus::Completed->value)
                    ->count(),
                'duplicates' => AdmissionApplication::query()->where('duplicate_flag', true)->count(),
            ],
            'queueRoute' => $view,
        ];
    }

    public function show(
        Request $request,
        AdmissionApplication $admissionApplication,
        AuditLogService $audit,
        NotificationService $notifications,
    ): View {
        Gate::authorize('view', $admissionApplication);
        $canReview = $request->user()?->can('review', $admissionApplication) ?? false;
        $relations = [
            'period:id,name,academic_year_id,opens_at,closes_at,quota',
            'reviewer:id,name',
        ];

        if ($canReview) {
            $relations = array_merge($relations, [
                'guardians',
                // The per-document review trail is what makes a returned document
                // explainable: which reviewer asked for it, when, and why.
                'documents.reviews.reviewer:id,name',
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
            // The reviewer needs to know whether the second channel can actually
            // send, so the announce form does not imply a delivery that cannot
            // happen.
            'whatsapp' => $notifications->whatsappStatus(),
            /*
             * The delivery history is only available when the application has a
             * portal account. notification_deliveries is keyed on the notifiable,
             * and a public-form application has none — its announcements are
             * written with a null notifiable, which cannot be attributed back to
             * one application among many. Rather than render an always-empty
             * panel, the view says the history is not available and points at the
             * application log.
             */
            'deliveries' => $canReview && $admissionApplication->applicant_user_id !== null
                ? NotificationDelivery::query()
                    ->where('notifiable_type', $admissionApplication->applicantUser?->getMorphClass() ?? User::class)
                    ->where('notifiable_id', $admissionApplication->applicant_user_id)
                    ->where('channel', NotificationChannel::WhatsApp->value)
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()
                : collect(),
            'canShowDeliveries' => $admissionApplication->applicant_user_id !== null,
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

    /**
     * Send a targeted PPDB announcement to one applicant.
     *
     * The in-app copy only exists for a portal applicant, because a public-form
     * application has no account to receive one. The WhatsApp copy is queued for
     * whichever number the application carries, and its real outcome is recorded
     * on a notification delivery row — so an administrator can see whether the
     * message actually went out, and the reviewer screen can say so honestly.
     */
    public function announce(
        AnnounceAdmissionApplicationRequest $request,
        AdmissionApplication $admissionApplication,
        NotificationService $notifications,
    ): RedirectResponse {
        $validated = $request->validated();
        $recipient = $admissionApplication->applicantUser;

        $notifications->deliver(
            $recipient,
            new CandidatePortalNotification(
                (string) $validated['title'],
                [(string) $validated['message']],
                NotificationCategory::Admission,
                $recipient === null ? null : route('ppdb.portal.status'),
            ),
            NotificationCategory::Admission,
            $admissionApplication->contact_whatsapp ?? $admissionApplication->contact_phone,
        );

        $whatsapp = $notifications->whatsappStatus();

        return back()->with('status', $whatsapp['configured']
            ? 'Pengumuman dikirim ke '.($recipient === null ? 'nomor WhatsApp pemohon' : 'akun dan nomor pemohon').'.'
            : 'Pengumuman dicatat di dalam aplikasi. WhatsApp belum dikonfigurasi, jadi tidak ada pesan yang dikirim lewat WhatsApp.');
    }
}
