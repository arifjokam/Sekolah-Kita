<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class AccountLifecycleService
{
    public function __construct(
        private readonly UserSessionInvalidator $sessionInvalidator,
        private readonly AuditLogService $audit,
    ) {}

    public function activate(User $actor, User $user, UserRole $role): User
    {
        Gate::forUser($actor)->authorize('manage-users');
        Gate::forUser($actor)->authorize('activate', $user);

        return $this->transition($actor, $user, [
            'role' => $role,
            'status' => AccountStatus::Active,
        ], 'account_activated');
    }

    public function suspend(User $actor, User $user): User
    {
        Gate::forUser($actor)->authorize('manage-users');
        Gate::forUser($actor)->authorize('suspend', $user);

        return $this->transition($actor, $user, [
            'status' => AccountStatus::Suspended,
        ], 'account_suspended');
    }

    public function deactivate(User $actor, User $user): User
    {
        Gate::forUser($actor)->authorize('manage-users');
        Gate::forUser($actor)->authorize('deactivate', $user);

        return $this->transition($actor, $user, [
            'status' => AccountStatus::Inactive,
        ], 'account_deactivated');
    }

    public function changeRole(User $actor, User $user, UserRole $role): User
    {
        Gate::forUser($actor)->authorize('manage-users');
        Gate::forUser($actor)->authorize('assignRole', $user);

        return $this->transition($actor, $user, [
            'role' => $role,
        ], 'account_role_changed');
    }

    /**
     * @param  array<string, UserRole|AccountStatus>  $attributes
     */
    private function transition(User $actor, User $user, array $attributes, string $event): User
    {
        $updatedUser = DB::transaction(function () use ($actor, $user, $attributes, $event): User {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedUser->forceFill($attributes);
            $this->sessionInvalidator->invalidate($lockedUser);
            $this->audit->recordOrFail($actor, 'account.'.$event, 'user', $lockedUser->getKey(), context: [
                'role' => $lockedUser->role?->value,
                'status' => $lockedUser->status?->value,
            ]);

            return $lockedUser;
        });

        Log::notice('security.account_lifecycle', [
            'event' => $event,
            'actor_id' => $actor->getKey(),
            'target_id' => $updatedUser->getKey(),
            'role' => $updatedUser->role?->value,
            'status' => $updatedUser->status?->value,
        ]);

        return $updatedUser;
    }
}
