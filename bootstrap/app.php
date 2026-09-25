<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));
        if ($trustedProxies !== '') {
            $middleware->trustProxies(at: array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))));
        }

        $trustedHosts = trim((string) env('TRUSTED_HOSTS', ''));
        if ($trustedHosts !== '') {
            $middleware->trustHosts(at: array_values(array_filter(array_map('trim', explode(',', $trustedHosts)))), subdomains: false);
        }

        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'role' => EnsureUserHasRole::class,
        ]);

        if (env('CACHE_STORE', 'database') === 'redis') {
            $middleware->throttleWithRedis();
        }

        $middleware->redirectGuestsTo(static fn (Request $request): string => route('login'));
        $middleware->redirectUsersTo(static fn (Request $request): string => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
