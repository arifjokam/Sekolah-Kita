<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class EmailVerificationPromptController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            Log::error('security.authentication', [
                'event' => 'email_verification_delivery_failed',
                'exception' => $exception::class,
            ]);

            return back()->withErrors([
                'email' => __('verification.failed'),
            ]);
        }

        Log::notice('security.authentication', [
            'event' => 'email_verification_requested',
            'user_id' => $user->getKey(),
        ]);

        return back()->with('status', __('verification.sent'));
    }
}
