<?php

namespace App\Services;

use Predis\Client;
use RuntimeException;

/**
 * Fails closed at boot when a production deployment is missing a runtime
 * prerequisite that cannot be recovered from safely at request time.
 *
 * `php artisan platform:readiness` is the reporting tool; this guard is the
 * enforcement tool. A misconfigured release should refuse to serve traffic
 * rather than silently degrade to a database-backed session, a
 * database-backed queue, or a Redis store that the worker never reads.
 *
 * The guard is a no-op outside production, and is individually disableable
 * through the same environment flag that the readiness check reports on, so
 * an operator can accept a documented risk without editing code.
 */
final class PlatformRuntimeGuard
{
    public static function enforce(): void
    {
        if (self::isDiagnosticCommand()) {
            return;
        }

        $failures = self::failures();

        if ($failures === []) {
            return;
        }

        throw new RuntimeException(
            'Sekolah Kita refused to start in production:'.PHP_EOL
            .'- '.implode(PHP_EOL.'- ', $failures).PHP_EOL
            .'Run "php artisan platform:readiness" for the full report.'
        );
    }

    /**
     * @return list<string>
     */
    public static function failures(): array
    {
        if (! app()->environment('production')) {
            return [];
        }

        $failures = [];

        if ((bool) config('app.debug', false)) {
            $failures[] = 'APP_DEBUG must be false in production.';
        }

        if (config('cache.default') === 'database' || config('session.driver') === 'database' || config('queue.default') === 'database') {
            $failures[] = 'Cache, session, and queue must not use the database driver in production (REDIS_REQUIRED_IN_PRODUCTION).';
        }

        if ((bool) config('sekolah-kita.platform.redis_required_in_production', true) && ! self::redisClientIsAvailable()) {
            $failures[] = 'The configured Redis client is not installed (REDIS_CLIENT='.(string) config('database.redis.client').').';
        }

        if (config('session.secure') !== true) {
            $failures[] = 'Session cookies must be marked secure (SESSION_SECURE_COOKIE=true).';
        }

        if (config('session.http_only') !== true) {
            $failures[] = 'Session cookies must be HTTP-only (SESSION_HTTP_ONLY=true).';
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $failures[] = 'APP_URL must use the https scheme.';
        }

        if (config('session.same_site') === 'none' && config('session.secure') !== true) {
            $failures[] = 'SESSION_SAME_SITE=none requires SESSION_SECURE_COOKIE=true.';
        }

        if (! filled(config('app.key'))) {
            $failures[] = 'APP_KEY is not configured.';
        }

        if (self::trustedConfigurationIsWildcard()) {
            $failures[] = 'TRUSTED_PROXIES and TRUSTED_HOSTS must not be wildcards in production.';
        }

        if ((string) config('sekolah-kita.audit.failure_mode') !== 'throw') {
            $failures[] = 'AUDIT_FAILURE_MODE must be "throw" so audit writes fail closed.';
        }

        return $failures;
    }

    /**
     * `platform:readiness` is the tool an operator runs precisely because a
     * release is misconfigured, so the guard must not prevent it from booting.
     * Without this exemption the deployment would be unfixable without editing
     * the environment by hand.
     */
    private static function isDiagnosticCommand(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        $argv = $_SERVER['argv'] ?? [];
        $command = is_array($argv) ? ($argv[1] ?? '') : '';

        return is_string($command) && str_starts_with($command, 'platform:');
    }

    private static function redisClientIsAvailable(): bool
    {
        $client = (string) config('database.redis.client', 'phpredis');

        return match ($client) {
            'phpredis' => extension_loaded('redis'),
            'predis' => class_exists(Client::class),
            default => false,
        };
    }

    private static function trustedConfigurationIsWildcard(): bool
    {
        foreach (['app.trusted_proxies', 'app.trusted_hosts'] as $key) {
            foreach ((array) config($key, []) as $value) {
                if (is_string($value) && in_array(trim($value), ['*', '**', '0.0.0.0/0', '::/0'], true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
