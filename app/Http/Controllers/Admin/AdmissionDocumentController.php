<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdmissionDocumentType;
use App\Enums\AdmissionDocumentVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RequestAdmissionDocumentRevisionRequest;
use App\Http\Requests\UpdateAdmissionDocumentStatusRequest;
use App\Http\Requests\UploadAdmissionDocumentRequest;
use App\Models\AdmissionApplication;
use App\Models\ApplicationDocument;
use App\Services\AdmissionDocumentService;
use App\Services\ApplicantPortalService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reviewer actions on a single supporting document.
 *
 * Three decisions, not two: accept, send back for a replacement, or reject. The
 * middle one is what makes a revision a one-document fix, and it requires a
 * written reason so the applicant is never told only that "something is wrong".
 */
class AdmissionDocumentController extends Controller
{
    public function store(
        UploadAdmissionDocumentRequest $request,
        AdmissionApplication $admissionApplication,
        AdmissionDocumentService $documents,
    ): RedirectResponse {
        $documents->uploadForReviewer(
            $request->user(),
            $admissionApplication,
            $request->file('document'),
            AdmissionDocumentType::from($request->string('document_type')->toString()),
        );

        return back()->with('status', 'Dokumen privat berhasil diunggah dan dikarantina.');
    }

    public function updateStatus(
        UpdateAdmissionDocumentStatusRequest $request,
        ApplicationDocument $document,
        AdmissionDocumentService $documents,
        ApplicantPortalService $portal,
    ): RedirectResponse {
        $status = AdmissionDocumentVerificationStatus::from($request->string('status')->toString());

        if ($status === AdmissionDocumentVerificationStatus::Verified) {
            $documents->verify($request->user(), $document);
        } elseif ($status === AdmissionDocumentVerificationStatus::NeedsRevision) {
            $documents->requestRevision($request->user(), $document, $request->input('reviewer_note'));

            $portal->recordDocumentDecision($document->refresh(), $status, $request->input('reviewer_note'));

            return back()->with('status', 'Dokumen diminta diperbaiki. Pemohon sudah diberi tahu alasannya.');
        } else {
            $documents->reject($request->user(), $document, $request->input('reviewer_note'));
        }

        $portal->recordDocumentDecision($document->refresh(), $status, $request->input('reviewer_note'));

        return back()->with('status', 'Status dokumen diperbarui.');
    }

    /**
     * The explicit "needs revision" route, for a reviewer who wants the reason
     * field to be mandatory rather than optional.
     */
    public function requestRevision(
        RequestAdmissionDocumentRevisionRequest $request,
        ApplicationDocument $document,
        AdmissionDocumentService $documents,
        ApplicantPortalService $portal,
    ): RedirectResponse {
        $documents->requestRevision($request->user(), $document, $request->string('reviewer_note')->toString());

        $portal->recordDocumentDecision(
            $document->refresh(),
            AdmissionDocumentVerificationStatus::NeedsRevision,
            $request->string('reviewer_note')->toString(),
        );

        return back()->with('status', 'Dokumen diminta diperbaiki. Pemohon sudah diberi tahu alasannya.');
    }

    public function download(ApplicationDocument $document, AdmissionDocumentService $documents): StreamedResponse
    {
        return $documents->download(request()->user(), $document);
    }
}
