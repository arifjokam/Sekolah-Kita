<?php

namespace App\Http\Controllers\Ppdb;

use App\Enums\GuardianRelationshipType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ppdb\SaveApplicantGuardianRequest;
use App\Models\AdmissionApplication;
use App\Models\ApplicationGuardian;
use App\Services\ApplicantPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The guardians section: father, mother, or a legal guardian.
 *
 * One or more guardians are supported, exactly as the application_guardians
 * schema already allows. Which of them receives the WhatsApp notices is an
 * explicit per-guardian field rather than an implicit assumption, because a
 * school routinely wants a different number for messaging.
 */
class ApplicantGuardianController extends Controller
{
    public function index(Request $request, ApplicantPortalService $portal): View|RedirectResponse
    {
        $application = $portal->currentApplication($request->user());

        if (! $application instanceof AdmissionApplication) {
            return redirect()->route('ppdb.index')->with('status', 'Belum ada pengajuan PPDB pada akun ini.');
        }

        return view('ppdb.portal.guardians', [
            'application' => $application,
            'guardians' => $application->guardians()->orderBy('priority_order')->orderBy('id')->get(),
            'relationships' => GuardianRelationshipType::cases(),
            'progress' => $portal->progress($request->user(), $application),
        ]);
    }

    public function store(
        SaveApplicantGuardianRequest $request,
        AdmissionApplication $application,
        ApplicantPortalService $portal,
    ): RedirectResponse {
        $portal->addGuardian($request->user(), $application, $request->validated(), $request);

        return redirect()
            ->route('ppdb.portal.guardians')
            ->with('status', 'Data orang tua / wali tersimpan.');
    }

    public function update(
        SaveApplicantGuardianRequest $request,
        AdmissionApplication $application,
        ApplicationGuardian $guardian,
        ApplicantPortalService $portal,
    ): RedirectResponse {
        // The guardian must belong to the application in the URL. The two
        // parameters are checked together here so a valid guardian id cannot be
        // attached to somebody else's application.
        abort_unless((int) $guardian->admission_application_id === (int) $application->getKey(), 404);

        $portal->updateGuardian($request->user(), $guardian, $request->validated(), $request);

        return redirect()
            ->route('ppdb.portal.guardians')
            ->with('status', 'Perubahan data wali tersimpan.');
    }
}
