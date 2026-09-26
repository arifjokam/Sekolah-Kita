<?php

namespace App\Http\Controllers\Ppdb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ppdb\RegisterApplicantRequest;
use App\Models\AdmissionPeriod;
use App\Services\ApplicantPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Candidate account registration.
 *
 * The account is an applicant account, not a student account: no Student row, no
 * Enrollment and no internal login are created here. Becoming a student is an
 * explicit administrator decision made later, through the existing PPDB
 * conversion path.
 */
class RegisteredApplicantController extends Controller
{
    public function create(ApplicantPortalService $portal): View|RedirectResponse
    {
        if (! config('sekolah-kita.ppdb.portal_enabled')) {
            return redirect()->route('ppdb.index')->with('status', 'Portal pendaftar PPDB sedang tidak diaktifkan.');
        }

        $period = AdmissionPeriod::query()->open()->orderByDesc('opens_at')->first();

        if (! $period instanceof AdmissionPeriod) {
            return redirect()->route('ppdb.index')->with('status', 'Pendaftaran PPDB belum sedang dibuka.');
        }

        return view('ppdb.portal.register', [
            'period' => $period,
        ]);
    }

    public function store(
        RegisterApplicantRequest $request,
        ApplicantPortalService $portal,
    ): RedirectResponse {
        if (! config('sekolah-kita.ppdb.portal_enabled')) {
            return redirect()->route('ppdb.index')->with('status', 'Portal pendaftar PPDB sedang tidak diaktifkan.');
        }

        // The period can close between rendering the form and posting it, so the
        // check is repeated here and answered exactly as the GET does, instead of
        // surfacing as a field error the applicant cannot act on.
        if (! AdmissionPeriod::query()->open()->exists()) {
            return redirect()->route('ppdb.index')->with('status', 'Pendaftaran PPDB belum sedang dibuka.');
        }

        $validated = $request->validated();

        $portal->register([
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'phone' => (string) $validated['phone'],
            'whatsapp' => $validated['whatsapp'] ?? null,
            'password' => (string) $validated['password'],
        ], $request);

        return redirect()
            ->route('verification.notice')
            ->with('status', 'Akun pendaftar berhasil dibuat. Verifikasi alamat email untuk masuk ke portal PPDB.');
    }
}
