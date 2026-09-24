<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(static fn (): Password => Password::min(12)->max(255));

        Gate::policy(User::class, UserPolicy::class);
        Gate::define('manage-users', static fn (User $user): bool => $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator());
        Gate::define('access-administrator-area', static fn (User $user): bool => $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator());

        RateLimiter::for('login', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));
            $ipHash = hash('sha256', (string) $request->ip());
            $limits = [
                Limit::perMinute(15)->by('login-ip:'.$ipHash),
            ];

            if ($email !== '') {
                $emailHash = hash('sha256', $email);
                $pairHash = hash('sha256', $email.'|'.$ipHash);

                $limits[] = Limit::perMinute(5)->by('login-account:'.$emailHash);
                $limits[] = Limit::perMinute(10)->by('login-pair:'.$pairHash);
            }

            return $limits;
        });

        RateLimiter::for('password-reset', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));
            $ipHash = hash('sha256', (string) $request->ip());
            $limits = [
                Limit::perMinute(10)->by('password-reset-ip:'.$ipHash),
            ];

            if ($email !== '') {
                $limits[] = Limit::perMinute(3)->by('password-reset-account:'.hash('sha256', $email));
            }

            return $limits;
        });

        RateLimiter::for('verification', function (Request $request): array {
            $userId = (string) ($request->user()?->getKey() ?? 'guest');
            $ipHash = hash('sha256', (string) $request->ip());

            return [
                Limit::perMinute(3)->by('verification-user:'.$userId),
                Limit::perMinute(10)->by('verification-ip:'.$ipHash),
            ];
        });
    }
}
