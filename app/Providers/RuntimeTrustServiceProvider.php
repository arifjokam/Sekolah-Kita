<?php

namespace App\Providers;

use App\Services\PlatformRuntimeGuard;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\ServiceProvider;

/**
 * Applies HTTP trust configuration at the correct point in the boot sequence.
 *
 * The `withMiddleware()` callback in bootstrap/app.php is registered through
 * `Application::afterResolving(HttpKernel::class)`, which runs while the
 * container is still resolving the HTTP kernel — before the framework has run
 * its bootstrappers, and therefore before `.env` has been loaded and before
 * cached configuration is available. Reading `env()` there silently yields the
 * supplied default, which meant TRUSTED_PROXIES and TRUSTED_HOSTS were never
 * applied in any environment.
 *
 * Resolving these values in a service provider `boot()` method happens after
 * configuration is loaded, so the same code path works for local development,
 * `php artisan config:cache`, and `php artisan optimize`.
 */
class RuntimeTrustServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->applyTrustedProxies();
        $this->applyTrustedHosts();

        PlatformRuntimeGuard::enforce();
    }

    private function applyTrustedProxies(): void
    {
        $proxies = (array) config('app.trusted_proxies', []);

        if ($proxies === []) {
            return;
        }

        TrustProxies::at(array_values($proxies));
    }

    private function applyTrustedHosts(): void
    {
        $hosts = (array) config('app.trusted_hosts', []);

        if ($hosts === []) {
            return;
        }

        TrustHosts::at(array_values($hosts), subdomains: false);
    }
}
