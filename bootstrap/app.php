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

        /*
         * Only the middleware *registration* belongs here. The trusted proxy
         * and trusted host values themselves are applied by
         * App\Providers\RuntimeTrustServiceProvider::boot().
         *
         * This callback is registered through Application::afterResolving(),
         * so it runs while the container resolves the HTTP kernel — before
         * the framework bootstrappers have loaded `.env` or cached
         * configuration. Calling env() here silently returns the default and
         * the configuration never takes effect.
         *
         * Registering TrustHosts is safe when no host pattern is configured:
         * Illuminate\Http\Middleware\TrustHosts::shouldSpecifyTrustedHosts()
         * makes it a no-op in the local environment and during tests, and
         * otherwise falls back to the APP_URL host pattern.
         */
        $middleware->trustHosts();

        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->redirectGuestsTo(static fn (Request $request): string => route('login'));
        $middleware->redirectUsersTo(static fn (Request $request): string => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
