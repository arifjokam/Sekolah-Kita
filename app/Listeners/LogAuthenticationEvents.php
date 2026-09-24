<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;

class LogAuthenticationEvents
{
    public function handleLogin(Login $event): void
    {
        $this->log('login_succeeded', $event->guard, $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        $this->log('logout', $event->guard, $event->user);
    }

    public function handleFailed(Failed $event): void
    {
        $this->log('login_failed', $event->guard, $event->user);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->log('password_reset_completed', 'web', $event->user);
    }

    public function handlePasswordResetLinkSent(PasswordResetLinkSent $event): void
    {
        $this->log('password_reset_requested', 'web', $event->user);
    }

    public function handleVerified(Verified $event): void
    {
        $this->log('email_verified', 'web', $event->user);
    }

    private function log(string $event, ?string $guard, mixed $user): void
    {
        Log::notice('security.authentication', [
            'event' => $event,
            'guard' => $guard,
            'user_id' => $user instanceof User ? $user->getKey() : null,
            'ip_hash' => $this->requestIpHash(),
        ]);
    }

    private function requestIpHash(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        $ip = request()->ip();

        return $ip === null ? null : hash('sha256', $ip);
    }
}
