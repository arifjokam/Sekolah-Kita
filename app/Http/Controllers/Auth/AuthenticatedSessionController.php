<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    private static ?string $dummyPasswordHash = null;

    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * @throws ValidationException
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $user = User::query()->where('email', $credentials['email'])->first();
        $passwordMatches = $user instanceof User
            ? Hash::check($credentials['password'], $user->password)
            : Hash::check($credentials['password'], self::$dummyPasswordHash ??= Hash::make(Str::random(64)));

        if (! $user instanceof User || ! $passwordMatches || ! $user->isActive() || ! $user->hasAssignedRole()) {
            $this->logRejectedLogin($request, $user);

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('auth.logged_out'));
    }

    private function logRejectedLogin(LoginRequest $request, ?User $user): void
    {
        $ip = $request->ip();

        Log::notice('security.authentication', [
            'event' => 'login_rejected',
            'user_id' => $user?->getKey(),
            'ip_hash' => $ip === null ? null : hash('sha256', $ip),
        ]);
    }
}
