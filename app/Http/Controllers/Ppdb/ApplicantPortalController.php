<?php

namespace App\Http\Controllers\Ppdb;

use App\Enums\NotificationChannel;
use App\Http\Controllers\Controller;
use App\Models\AdmissionApplication;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\ApplicantPortalService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The applicant portal shell: dashboard, review, submit, status and the
 * notification inbox.
 *
 * Every method resolves the caller's own application through
 * ApplicantPortalService::currentApplication() — never through a route parameter
 * — so there is no id in any URL that could address somebody else's application.
 */
class ApplicantPortalController extends Controller
{
    public function dashboard(Request $request, ApplicantPortalService $portal): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $application = $portal->currentApplication($user, true);

        if (! $application instanceof AdmissionApplication) {
            return redirect()->route('ppdb.index')->with('status', 'Tidak ada periode PPDB yang sedang dibuka.');
        }

        $progress = $portal->progress($user, $application);

        return view('ppdb.portal.dashboard', array_merge($progress, [
            'user' => $user,
            'notifications' => $user->notifications()->latest()->limit(8)->get(),
            'unreadNotificationCount' => $user->unreadNotifications()->count(),
        ]));
    }

    public function review(Request $request, ApplicantPortalService $portal): View|RedirectResponse
    {
        $resolved = $this->resolve($request, $portal);

        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }

        [$user, $application, $progress] = $resolved;

        return view('ppdb.portal.review', array_merge($progress, [
            'user' => $user,
            'biodata' => $this->biodataRows($application),
            'guardianRows' => $application->guardians,
        ]));
    }

    public function submit(Request $request, ApplicantPortalService $portal): RedirectResponse
    {
        $resolved = $this->resolve($request, $portal);

        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }

        [$user, $application] = $resolved;

        $portal->submit($user, $application, $request);

        return redirect()
            ->route('ppdb.portal.status')
            ->with('status', 'Pendaftaran berhasil diajukan dan menunggu peninjauan administrator.');
    }

    public function status(Request $request, ApplicantPortalService $portal): View|RedirectResponse
    {
        $resolved = $this->resolve($request, $portal);

        if ($resolved instanceof RedirectResponse) {
            return $resolved;
        }

        [$user, $application, $progress] = $resolved;

        return view('ppdb.portal.status', array_merge($progress, [
            'user' => $user,
            // Bounded, and newest first: the full history of a long-running
            // application is unbounded, and a status page is a summary, not an
            // audit export.
            'history' => $application->statusHistories()
                ->orderByDesc('workflow_version')
                ->orderByDesc('id')
                ->limit(25)
                ->get(),
        ]), $this->noStoreHeaders());
    }

    public function notifications(Request $request, NotificationService $notifications): Response
    {
        /** @var User $user */
        $user = $request->user();

        $deliveries = $user->notificationDeliveries()
            ->where('channel', NotificationChannel::WhatsApp->value)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return response()->view('ppdb.portal.notifications', [
            'user' => $user,
            'notifications' => $user->notifications()->latest()->paginate(20),
            'whatsapp' => $notifications->whatsappStatus(),
            'deliveries' => $deliveries,
            // The most recent number actually dialled, newest first. This used to
            // be value('recipient') with no ordering, which returned an arbitrary
            // row's number while the card claimed it was the latest.
            'lastRecipient' => $deliveries->firstWhere(
                static fn (NotificationDelivery $delivery): bool => $delivery->recipient !== null,
            )?->recipient,
            // response()->view() takes the status code before the headers, so
            // the private-cache headers are passed as the fourth argument.
        ], 200, $this->noStoreHeaders());
    }

    /**
     * The caller's own application, or a redirect when there is none.
     *
     * @return array{0: User, 1: AdmissionApplication, 2: array<string, mixed>}|RedirectResponse
     */
    private function resolve(Request $request, ApplicantPortalService $portal): array|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $application = $portal->currentApplication($user);

        if (! $application instanceof AdmissionApplication) {
            return redirect()->route('ppdb.index')->with('status', 'Belum ada pengajuan PPDB pada akun ini.');
        }

        return [$user, $application, $portal->progress($user, $application)];
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function biodataRows(AdmissionApplication $application): array
    {
        $gender = match ($application->gender) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => '—',
        };

        $rows = [
            ['label' => 'Nama lengkap', 'value' => (string) $application->applicant_name],
            ['label' => 'Nama panggilan', 'value' => (string) ($application->preferred_name ?? '—')],
            ['label' => 'NIK', 'value' => (string) ($application->national_id ?? '—')],
            ['label' => 'NISN', 'value' => (string) ($application->nisn ?? '—')],
            ['label' => 'Tempat, tanggal lahir', 'value' => trim(
                ($application->birth_place ?? '—').', '.($application->birth_date?->format('d M Y') ?? '—'),
            )],
            ['label' => 'Jenis kelamin', 'value' => $gender],
            ['label' => 'Alamat', 'value' => (string) ($application->address ?? '—')],
        ];

        $region = Collection::make([
            $application->village,
            $application->district,
            $application->regency,
            $application->province,
            $application->postal_code,
        ])->filter()->implode(', ');

        $rows[] = ['label' => 'Kelurahan / kecamatan / kota', 'value' => $region !== '' ? $region : '—'];
        $rows[] = ['label' => 'RT / RW', 'value' => trim(($application->address_rt ?? '').'/'.($application->address_rw ?? '')) ?: '—'];
        $rows[] = ['label' => 'Sekolah asal', 'value' => (string) ($application->current_school ?? '—')];
        $rows[] = ['label' => 'Tahun lulus', 'value' => (string) ($application->graduation_year ?? '—')];
        $rows[] = ['label' => 'Telepon', 'value' => (string) ($application->contact_phone ?? '—')];
        $rows[] = ['label' => 'WhatsApp', 'value' => (string) ($application->contact_whatsapp ?? '—')];

        return $rows;
    }

    /**
     * Portal pages are private: no shared cache, no referrer leakage.
     *
     * @return array<string, string>
     */
    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];
    }
}
