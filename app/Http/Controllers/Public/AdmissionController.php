<?php

namespace App\Http\Controllers\Public;

use App\Enums\AdmissionAccessTokenPurpose;
use App\Enums\AdmissionDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdmissionApplicationRequest;
use App\Http\Requests\SubmitAdmissionCorrectionRequest;
use App\Http\Requests\UploadAdmissionDocumentRequest;
use App\Models\AdmissionPeriod;
use App\Services\AdmissionAccessService;
use App\Services\AdmissionApplicationService;
use App\Services\AdmissionDocumentService;
use App\Services\PublicContentReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AdmissionController extends Controller
{
    public function index(PublicContentReader $content): View
    {
        return view('public.ppdb.index', [
            'periods' => $content->admissionPeriods(),
            'openPeriod' => $content->openAdmissionPeriod(),
        ]);
    }

    public function create(PublicContentReader $content): View|RedirectResponse
    {
        $period = $content->openAdmissionPeriod();

        if ($period === null) {
            return redirect()->route('ppdb.index')->with('status', 'Pendaftaran PPDB belum sedang dibuka.');
        }

        return view('public.ppdb.application', [
            'period' => $period,
            'periodId' => $period['id'],
        ]);
    }

    public function store(StoreAdmissionApplicationRequest $request, AdmissionApplicationService $applications): RedirectResponse
    {
        $period = AdmissionPeriod::query()->findOrFail($request->integer('admission_period_id'));
        $data = $request->validated();
        unset($data['consent'], $data['website']);

        $result = $applications->submit($period, $data, $request);

        return redirect()
            ->route('ppdb.status.show', $result['token'])
            ->withHeaders([
                'Cache-Control' => 'private, no-store',
                'Pragma' => 'no-cache',
                'Referrer-Policy' => 'no-referrer',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
    }

    public function status(
        string $token,
        AdmissionAccessService $access,
        AdmissionApplicationService $applications,
    ): Response {
        $application = $access->resolve($token, AdmissionAccessTokenPurpose::Status);
        abort_if($application === null, 404);

        $canRevise = $application->canRevise();

        return response()->view('public.ppdb.status', [
            'status' => $applications->publicStatus($application),
            'statusToken' => $token,
            'revisionToken' => $canRevise
                ? $access->issue($application, AdmissionAccessTokenPurpose::Revision)
                : null,
            'documentToken' => $canRevise
                ? $access->issue($application, AdmissionAccessTokenPurpose::DocumentUpload)
                : null,
            'withdrawalToken' => $application->status->isTerminal()
                ? null
                : $access->issue($application, AdmissionAccessTokenPurpose::Withdrawal),
        ])->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function correction(
        string $token,
        SubmitAdmissionCorrectionRequest $request,
        AdmissionAccessService $access,
        AdmissionApplicationService $applications,
    ): RedirectResponse {
        $statusApplication = $access->resolve($token, AdmissionAccessTokenPurpose::Status);
        $revisionApplication = $access->resolve(
            $request->string('access_token')->toString(),
            AdmissionAccessTokenPurpose::Revision,
        );
        abort_if(
            $statusApplication === null
                || $revisionApplication === null
                || (int) $statusApplication->getKey() !== (int) $revisionApplication->getKey(),
            404,
        );

        $applications->resubmit($revisionApplication, $request->validated());

        return redirect()->route('ppdb.status.show', $token)->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Pragma' => 'no-cache',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function uploadDocument(
        string $token,
        UploadAdmissionDocumentRequest $request,
        AdmissionAccessService $access,
        AdmissionDocumentService $documents,
    ): RedirectResponse {
        $application = $access->resolve($token, AdmissionAccessTokenPurpose::Status);
        abort_if($application === null, 404);

        $documents->uploadForApplicant(
            $application,
            (string) $request->input('access_token'),
            $request->file('document'),
            AdmissionDocumentType::from($request->string('document_type')->toString()),
        );

        return redirect()->route('ppdb.status.show', $token)->with('status', 'Dokumen berhasil dikirim dan menunggu pemeriksaan.')->withHeaders(['Cache-Control' => 'private, no-store']);
    }

    public function withdraw(
        string $token,
        Request $request,
        AdmissionAccessService $access,
        AdmissionApplicationService $applications,
    ): RedirectResponse {
        $statusApplication = $access->resolve($token, AdmissionAccessTokenPurpose::Status);
        $withdrawalApplication = $access->resolve(
            $request->string('access_token')->toString(),
            AdmissionAccessTokenPurpose::Withdrawal,
        );
        abort_if(
            $statusApplication === null
                || $withdrawalApplication === null
                || (int) $statusApplication->getKey() !== (int) $withdrawalApplication->getKey(),
            404,
        );

        $applications->withdraw($withdrawalApplication);

        return redirect()->route('ppdb.status.show', $token)
            ->with('status', 'Pengajuan telah dihentikan.')
            ->withHeaders([
                'Cache-Control' => 'private, no-store',
                'Pragma' => 'no-cache',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]);
    }
}
