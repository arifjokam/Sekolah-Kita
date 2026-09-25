<?php

namespace App\View\Composers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedLayoutComposer
{
    public function compose(View $view): void
    {
        $view->with('unreadNotificationCount', Auth::check()
            ? Auth::user()->unreadNotifications()->count()
            : 0);
    }
}
