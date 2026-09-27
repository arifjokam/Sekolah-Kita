<?php

namespace App\Http\Controllers\Ppdb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ppdb\SaveApplicantBiodataRequest;
use App\Models\AdmissionApplication;
use App\Services\ApplicantPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student biodata section.
 *
 * Saved as a draft section, not as a submission: a candidate fills this in
 * progressively, so a successful save never changes the application's status and
 * never notifies a reviewer.
 */
class ApplicantBiodataController extends Controller
{
    public function edit(ApplicantPortalService $portal, Request $request): View|RedirectResponse
    {
        $application = $portal->currentApplication($request->user());

        if (! $application instanceof AdmissionApplication) {
            return redirect()->route('ppdb.index')->with('status', 'Belum ada pengajuan PPDB pada akun ini.');
        }

        return view('ppdb.portal.biodata', [
            'application' => $application,
            'progress' => $portal->progress($request->user(), $application),
        ]);
    }

    public function update(
        SaveApplicantBiodataRequest $request,
        AdmissionApplication $application,
        ApplicantPortalService $portal,
    ): RedirectResponse {
        $portal->saveBiodata($request->user(), $application, $request->validated(), $request);

        return redirect()
            ->route('ppdb.portal.biodata')
            ->with('status', 'Biodata siswa tersimpan sebagai draf.');
    }
}
