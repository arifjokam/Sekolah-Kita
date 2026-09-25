<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;

class LogAuthenticationEvents
{
    public function __construct(private readonly AuditLogService $audit) {}

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
        $actor = $user instanceof User ? $user : null;
        Log::notice('security.authentication', [
            'event' => $event,
            'guard' => $guard,
            'user_id' => $actor?->getKey(),
            'ip_hash' => $this->requestIpHash(),
        ]);
        $this->audit->record(
            actor: $actor,
            action: 'authentication.'.$event,
            targetType: 'user',
            targetId: $actor?->getKey(),
            result: $event === 'login_failed' ? 'denied' : 'success',
            context: ['event' => $event, 'guard' => $guard ?? 'web'],
            request: app()->bound('request') ? request() : null,
        );
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
