<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserSessionInvalidator
{
    public function __construct(private readonly UserSessionRegistry $registry) {}

    /**
     * Revoke every authenticated session belonging to the user.
     *
     * Rotating the remember token invalidates persistent "remember me"
     * cookies. The database driver additionally supports enumerating sessions
     * by `user_id`; every other driver relies on the Redis-backed session
     * registry, because those drivers store sessions as opaque values that
     * cannot be queried by user.
     */
    public function invalidate(User $user): void
    {
        $user->setRememberToken(Str::random(60));
        $user->save();

        if (config('session.driver') === 'database') {
            $this->deleteDatabaseSessions($user);
        }

        $this->registry->revokeAll($user);
    }

    private function deleteDatabaseSessions(User $user): void
    {
        $table = (string) config('session.table', 'sessions');
        $connection = config('session.connection');
        $query = $connection === null
            ? DB::table($table)
            : DB::connection($connection)->table($table);

        $query->where('user_id', $user->getKey())->delete();
    }
}
