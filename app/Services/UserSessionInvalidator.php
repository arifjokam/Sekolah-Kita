<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserSessionInvalidator
{
    public function invalidate(User $user): void
    {
        $user->setRememberToken(Str::random(60));
        $user->save();

        if (config('session.driver') !== 'database') {
            return;
        }

        $table = config('session.table', 'sessions');
        $connection = config('session.connection');
        $query = $connection === null
            ? DB::table($table)
            : DB::connection($connection)->table($table);

        $query->where('user_id', $user->getKey())->delete();
    }
}
