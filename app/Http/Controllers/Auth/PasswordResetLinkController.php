<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendPasswordResetLinkRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(SendPasswordResetLinkRequest $request): RedirectResponse
    {
        try {
            Password::sendResetLink($request->validated());
        } catch (Throwable $exception) {
            Log::error('security.authentication', [
                'event' => 'password_reset_delivery_failed',
                'exception' => $exception::class,
            ]);

            return back()->with('status', __('passwords.sent'));
        }

        return back()->with('status', __('passwords.sent'));
    }
}
