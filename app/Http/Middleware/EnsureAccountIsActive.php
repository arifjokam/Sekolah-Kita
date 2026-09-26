<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\UserSessionRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function __construct(private readonly UserSessionRegistry $sessionRegistry) {}

    /**
     * Ensure the authenticated account may use protected application areas.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->guest(route('login'));
        }

        if ($user->isActive() && $user->hasAssignedRole()) {
            $this->sessionRegistry->registerCurrentSession($user, $request->session());

            return $next($request);
        }

        Log::notice('security.authentication', [
            'event' => 'account_access_blocked',
            'user_id' => $user->getKey(),
            'ip_hash' => $this->requestIpHash($request),
        ]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => __('auth.failed'),
        ]);
    }

    private function requestIpHash(Request $request): ?string
    {
        $ip = $request->ip();

        return $ip === null ? null : hash('sha256', $ip);
    }
}
