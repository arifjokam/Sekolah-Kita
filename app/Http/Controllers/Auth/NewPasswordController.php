<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\UserSessionInvalidator;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function __construct(private readonly UserSessionInvalidator $sessionInvalidator) {}

    public function create(Request $request): View
    {
        return view('auth.reset-password', [
            'token' => (string) $request->route('token'),
        ]);
    }

    public function store(ResetPasswordRequest $request): RedirectResponse
    {
        $status = Password::reset($request->validated(), function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
            ]);
            $this->sessionInvalidator->invalidate($user);

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors([
                'email' => __('passwords.token'),
            ]);
        }

        return redirect()->route('login')->with('status', __('passwords.reset'));
    }
}
