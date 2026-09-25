<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdmissionDocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAdmissionDocumentStatusRequest;
use App\Http\Requests\UploadAdmissionDocumentRequest;
use App\Models\AdmissionApplication;
use App\Models\ApplicationDocument;
use App\Services\AdmissionDocumentService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    ): RedirectResponse {
        if ($request->string('status')->toString() === 'verified') {
            $documents->verify($request->user(), $document);
        } else {
            $documents->reject($request->user(), $document, $request->input('reviewer_note'));
        }

        return back()->with('status', 'Status dokumen diperbarui.');
    }

    public function download(ApplicationDocument $document, AdmissionDocumentService $documents): StreamedResponse
    {
        return $documents->download(request()->user(), $document);
    }
}
