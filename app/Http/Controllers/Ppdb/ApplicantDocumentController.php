<?php

namespace App\Http\Controllers\Ppdb;

use App\Enums\AdmissionDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ppdb\UploadApplicantDocumentRequest;
use App\Models\AdmissionApplication;
use App\Models\ApplicationDocument;
use App\Services\AdmissionDocumentService;
use App\Services\ApplicantPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Supporting documents.
 *
 * Files are private end to end. Nothing here ever produces a public URL: an
 * upload goes to the private disk with a random object key, and a download is
 * streamed through the controller after the policy confirms the document belongs
 * to the requesting applicant's own application. The MediaAsset row keeps the
 * quarantine/scan state, so a download is refused until scanning succeeded.
 */
class ApplicantDocumentController extends Controller
{
    public function index(Request $request, ApplicantPortalService $portal): View|RedirectResponse
    {
        $application = $portal->currentApplication($request->user());

        if (! $application instanceof AdmissionApplication) {
            return redirect()->route('ppdb.index')->with('status', 'Belum ada pengajuan PPDB pada akun ini.');
        }

        $progress = $portal->progress($request->user(), $application);

        return view('ppdb.portal.documents', [
            'application' => $application,
            'slots' => $progress['document_slots'],
            'canUpload' => $application->canAcceptDocument(),
            'progress' => $progress,
        ]);
    }

    public function store(
        UploadApplicantDocumentRequest $request,
        AdmissionApplication $application,
        AdmissionDocumentService $documents,
    ): RedirectResponse {
        $documents->uploadForPortal(
            $application,
            $request->file('document'),
            AdmissionDocumentType::from($request->string('document_type')->toString()),
        );

        return redirect()
            ->route('ppdb.portal.documents')
            ->with('status', 'Dokumen berhasil diunggah dan menunggu verifikasi administrator.');
    }

    /**
     * Replace a document the reviewer sent back.
     *
     * Only that one category is replaceable, so a revision is a single-document
     * fix rather than a restart of the whole application.
     */
    public function replace(
        UploadApplicantDocumentRequest $request,
        ApplicationDocument $document,
        AdmissionDocumentService $documents,
    ): RedirectResponse {
        /** @var ApplicationDocument $document */
        $document = $request->route('document');

        $documents->uploadForPortal(
            $document->application,
            $request->file('document'),
            $document->document_type,
        );

        return redirect()
            ->route('ppdb.portal.documents')
            ->with('status', $document->document_type->label().' berhasil diunggah ulang dan menunggu verifikasi.');
    }

    /**
     * Stream the applicant's own document.
     *
     * The `downloadOwn` ability is checked here, and the document is reached only
     * through the route-bound id — there is no application id in the URL to
     * tamper with, and the policy compares the document's own owning application.
     */
    public function download(
        Request $request,
        ApplicationDocument $document,
        AdmissionDocumentService $documents,
    ): StreamedResponse {
        return $documents->download($request->user(), $document, 'downloadOwn');
    }
}
