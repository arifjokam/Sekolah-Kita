<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DashboardPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Role specific dashboard.
 *
 * The route itself is shared, but every branch is a separate view so an
 * administrator never receives a teacher payload or a student payload, and so
 * each role only ever receives the data its policies allow.
 */
class DashboardController extends Controller
{
    /**
     * Send the account to the landing page for its role.
     *
     * The login controller redirects here, so the destination is resolved from
     * the session rather than hard coded.
     */
    public function redirectToRoleDashboard(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return redirect()->route(match (true) {
            $user->isAdministrator() => 'dashboard.administrator',
            $user->isGuru() => 'dashboard.guru',
            default => 'dashboard.siswa',
        });
    }

    public function index(Request $request, DashboardPresenter $dashboard): View
    {
        /** @var User $user */
        $user = $request->user();

        return match (true) {
            $user->isAdministrator() => view('dashboard.administrator', $dashboard->forAdministrator($user)),
            $user->isGuru() => view('dashboard.guru', $dashboard->forTeacher($user)),
            $user->isSiswa() => view('dashboard.siswa', $dashboard->forStudent($user)),
            default => view('dashboard.unavailable'),
        };
    }
}
