<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Predis\Client;
use Throwable;

/**
 * Reports whether a deployment satisfies the production prerequisites for
 * Sekolah Kita.
 *
 * The service is deliberately fail-closed: every check reports its own state
 * and nothing is skipped because the environment is convenient. Checks are
 * grouped by the concern they cover so an operator can see which subsystem is
 * blocking a release rather than reading a flat list.
 *
 * No check returns configuration values, connection strings, or key material.
 */
class ProductionReadinessService
{
    /**
     * Administrator multi-factor authentication is not implemented in this
     * codebase. It is recorded here as a compile-time constant rather than a
     * configuration value so the readiness result cannot be flipped by an
     * environment variable that ships with the release.
     */
    private const ADMINISTRATOR_MFA_IMPLEMENTED = false;

    /**
     * @return array{
     *     ready: bool,
     *     groups: array<string, list<array{name: string, ok: bool, blocking: bool, message: string}>>,
     *     checks: list<array{name: string, ok: bool, blocking: bool, message: string}>
     * }
     */
    public function check(): array
    {
        $groups = [
            'application' => $this->applicationGroup(),
            'http' => $this->httpGroup(),
            'redis' => $this->redisGroup(),
            'cache' => $this->cacheGroup(),
            'session' => $this->sessionGroup(),
            'queue' => $this->queueGroup(),
            'rate_limiting' => $this->rateLimitingGroup(),
            'database' => $this->databaseGroup(),
            'storage' => $this->storageGroup(),
            'mail' => $this->mailGroup(),
            'administrator_security' => $this->administratorSecurityGroup(),
        ];

        $checks = array_merge(...array_values($groups));
        $blockingFailures = array_filter(
            $checks,
            static fn (array $check): bool => ! $check['ok'] && $check['blocking'],
        );

        return [
            'ready' => $blockingFailures === [],
            'groups' => $groups,
            'checks' => $checks,
        ];
    }

    /**
     * @return list<array{name: string, ok: bool, blocking: bool, message: string}>
     */
    private function applicationGroup(): array
    {
        return [
            $this->makeCheck(
                'environment',
                app()->environment('production'),
                'The application must run with APP_ENV=production.'
            ),
            $this->makeCheck(
                'debug_disabled',
                config('app.debug') === false,
                'APP_DEBUG must be false in production.'
            ),
            $this->makeCheck(
                'application_key',
                filled(config('app.key')),
                'APP_KEY must be generated and injected through the secret store.'
            ),
            $this->makeCheck(
                'config_cached',
                $this->configurationIsCached(),
                'Run "php artisan optimize" so the release does not depend on runtime .env parsing.',
                blocking: false
            ),
            $this->makeCheck(
                'bcrypt_cost',
                (int) env('BCRYPT_ROUNDS', 12) >= 12,
                'BCRYPT_ROUNDS must be at least 12 in production.'
            ),
        ];
    }

    /**
     * @return list<array{name: string, ok: bool, blocking: bool, message: string}>
     */
    private function httpGroup(): array
    {
        return [
            $this->makeCheck(
                'https_url',
                str_starts_with((string) config('app.url'), 'https://'),
                'APP_URL must use the https scheme.'
            ),
            $this->makeCheck(
                'trusted_proxies',
                $this->hasExplicitValue(config('app.trusted_proxies')),
                'TRUSTED_PROXIES must list the explicit proxy addresses or CIDRs that terminate TLS.'
            ),
            $this->makeCheck(
                'trusted_hosts',
                $this->hasExplicitValue(config('app.trusted_hosts')),
                'TRUSTED_HOSTS must list the explicit host patterns the application answers to.'
            ),
            $this->makeCheck(
                'secure_cookies',
                config('session.secure') === true,
                'SESSION_SECURE_COOKIE must be true so cookies are not sent over plaintext HTTP.'
            ),
            $this->makeCheck(
                'http_only_cookies',
                config('session.http_only') === true,
                'SESSION_HTTP_ONLY must be true so cookies are not readable from JavaScript.'
            ),
            $this->makeCheck(
                'same_site_policy',
                in_array(config('session.same_site'), ['lax', 'strict'], true),
                'SESSION_SAME_SITE must be "lax" or "strict".',
            ),
            $this->makeCheck(
                'csrf_protection',
                config('app.key') !== null && ! $this->appIsStateless(),
                'CSRF protection requires the session middleware and a configured application key.'
            ),
        ];
    }

    /**
     * @return list<array{name: string, ok: bool, blocking: bool, message: string}>
     */
    private function redisGroup(): array
    {
        $required = config('sekolah-kita.platform.redis_required_in_production') === true;
        $checks = [
            $this->makeCheck(
                'redis_client',
                $this->redisClientIsAvailable(),
                'The configured Redis client is not installed (REDIS_CLIENT='.(string) config('database.redis.client').').'
            ),
            $this->makeCheck(
                'redis_prefix',
                $this->hasExplicitValue((string) config('database.redis.options.prefix')),
                'REDIS_PREFIX must be set so environments cannot overwrite each other.',
                blocking: false
            ),
        ];

        $connections = $this->configuredRedisConnections();

        foreach ($connections as $purpose => $connection) {
            $checks[] = $this->makeCheck(
                'redis_connection:'.$purpose,
                $this->redisConnectionResponds($connection),
                'The "'.$connection.'" Redis connection ('.$purpose.') did not respond to PING.',
                blocking: $required
            );
        }

        return $checks;
    }

    /**
     * @return list<array{name: string, ok: bool, blocking: bool, message: string}>
     */
    private function cacheGroup(): array
    {
        $driver = (string) config('cache.default');
        $required = config('sekolah-kita.platform.redis_required_in_production') === true;

        return [
            $this->makeCheck(
                'cache_driver',
                ! ($required && $driver === 'database'),
                'The cache store must not be the database driver in production.'
            ),
            $this->makeCheck(
                'cache_prefix',
                $this->hasExplicitValue((string) config('cache.prefix')),
                'CACHE_PREFIX must be set so shared Redis instances stay isolated.',
                blocking: false
            ),
            $this->makeCheck(
                'cache_round_trip',
                $this->cacheRoundTrips(),
                'A cache write followed by a read did not return the stored value.'
            ),
        ];
    }

    /**
     * @return list<array{name: string, ok: bool, blocking: bool, message: string}>
     */
    private function sessionGroup(): array
    {
        $driver = (string) config('session.driver');
        $required = config('sekolah-kita.platform.redis_required_in_production') === true;

        return [
            $this->makeCheck(
                'session_driver',
                ! ($required && $driver === 'database'),
                'The session store must not be the database driver in production.'
            ),
            $this->makeCheck(
                'session_encryption',
                config('session.encrypt') === true,
                'SESSION_ENCRYPT must be true so session payloads are encrypted at rest.'
            ),
            $this->makeCheck(
                'session_serialization',
                config('session.serialization') === 'json',
                'Session serialization must stay "json" to avoid PHP object deserialization risk.'
            ),
            $this->makeCheck(
                'session_lifetime',
                (int) config('session.lifetime') <= 240,
                'SESSION_LIFETIME must be 240 minutes or less.',
                blocking: false
            ),
        ];
    }

    /**
     * @return list<array{name: string, ok: bool, blocking: bool, message: string}>
     */
    private function queueGroup(): array
    {
        $driver = (string) config('queue.default');
        $required = config('sekolah-kita.platform.redis_required_in_production') === true;
        $connection = $driver === 'redis' ? (array) config('queue.connections.redis') : [];
        $retryAfter = (int) ($connection['retry_after'] ?? 0);
        $workerTimeout = (int) config('sekolah-kita.queue.worker_timeout_seconds');
        $failedDriver = (string) config('queue.failed.driver');

        return [
            $this->makeCheck(
                'queue_driver',
                ! ($required && $driver === 'database') && ! in_array($driver, ['sync', 'null'], true),
                'The queue connection must be an asynchronous backend in production.'
            ),
            $this->makeCheck(
                'queue_retry_after',
                $driver !== 'redis' || $retryAfter > $workerTimeout,
                'The queue retry_after value must be strictly greater than the worker timeout ('.$workerTimeout.'s).'
            ),
            $this->makeCheck(
                'failed_job_durability',
                $failedDriver !== 'null' && $failedDriver !== 'file',
                'Failed jobs must be stored durably in the database, not in a local file.'
            ),
            $this->makeCheck(
                'queue_queues_declared',
                config('sekolah-kita.queue.queues') !== [],
                'QUEUE_WORKER_QUEUES must list the queues the supervised workers consume.'
            ),
        ];
    }

    /**
     * @return list<array{name: string, ok: bool, blocking: bool, message: string}>
     */
    private function rateLimitingGroup(): array
    {
        $limiter = config('cache.limiter');
        $required = config('sekolah-kita.platform.redis_required_in_production') === true;

        return [
            $this->makeCheck(
                'rate_limiter_store',
                filled($limiter) && ! ($required && $limiter === 'database'),
                'CACHE_LIMITER must name a non-database store so throttling survives a cache flush.'
            ),
            $this->makeCheck(
                'rate_limiter_backend',
                $this->rateLimiterStoreResolves(),
                'The configured rate limiter store could not be resolved.'
            ),
        ];
    }

    /**
     * @return list<array{name: string, ok: bool, blocking: bool, message: string}>
     */
    private function databaseGroup(): array
    {
        $driver = (string) config('database.default');
        $isMariaDb = in_array($driver, ['mysql', 'mariadb'], true);
        $version = $isMariaDb ? $this->databaseVersion() : null;
        $isMariaDbServer = $version !== null && str_contains(strtolower($version), 'mariadb');

        return [
            $this->makeCheck(
                'database_driver',
                $isMariaDb,
                'Production must use the MariaDB or MySQL connection.'
            ),
            $this->makeCheck(
                'database_reachable',
                $isMariaDb && $version !== null,
                'The database connection could not be verified.'
            ),
            $this->makeCheck(
                'mariadb_server',
                $isMariaDbServer,
                'The database server did not report itself as MariaDB.'
            ),
            ...$this->migrationChecks(),
        ];
    }

    /**
     * @return list<array{name: string, ok: bool, blocking: bool, message: string}>
     */
    private function migrationChecks(): array
    {
        try {
            $files = array_map(
                static fn (string $file): string => pathinfo($file, PATHINFO_FILENAME),
                glob(database_path('migrations/*.php')) ?: [],
            );
            $ran = DB::table('migrations')->pluck('migration')->all();
            $pending = array_values(array_diff($files, $ran));

            return [$this->makeCheck(
                'migrations',
                $pending === [],
                $pending === []
                    ? 'All migration files are recorded as run.'
                    : count($pending).' migration(s) are pending. Run "php artisan migrate --force" after backup.'
            )];
        } catch (Throwable) {
            return [$this->makeCheck('migrations', false, 'Migration state could not be read.')];
        }
    }

    /**
     * @return list<array{name: string, ok: bool, blocking: bool, message: string}>
     */
    private function storageGroup(): array
    {
        try {
            $ok = is_dir(storage_path('app/private')) && is_writable(storage_path('app/private'))
                && is_dir(storage_path('app/public')) && is_writable(storage_path('app/public'))
                && Storage::disk('local')->getDriver() !== null;

            return [$this->makeCheck(
                'storage_writable',
                $ok,
                'Private and public storage roots must exist and be writable by the PHP-FPM user.'
            )];
        } catch (Throwable) {
            return [$this->makeCheck('storage_writable', false, 'Storage could not be verified.')];
        }
    }

    /**
     * @return list<array{name: string, ok: bool, blocking: bool, message: string}>
     */
    private function mailGroup(): array
    {
        $mailer = (string) config('mail.default');
        $transport = (string) config('mail.mailers.'.$mailer.'.transport');
        $failoverMailers = (array) config('mail.mailers.failover.mailers', []);
        $ok = $mailer !== ''
            && ! in_array($mailer, ['log', 'array', 'null'], true)
            && $transport !== ''
            && ! ($mailer === 'failover' && in_array('log', $failoverMailers, true));

        return [$this->makeCheck(
            'mail_transport',
            $ok,
            $ok
                ? 'A production mail transport is configured.'
                : 'MAIL_MAILER must name a real transport; log/array/null mailers silently discard password reset and status mail.'
        )];
    }

    /**
     * @return list<array{name: string, ok: bool, blocking: bool, message: string}>
     */
    private function administratorSecurityGroup(): array
    {
        $required = config('sekolah-kita.security.administrator_mfa_required') === true;
        $mfaImplemented = self::ADMINISTRATOR_MFA_IMPLEMENTED;

        return [
            $this->makeCheck(
                'administrator_mfa',
                ! $required || $mfaImplemented,
                $required && ! $mfaImplemented
                    ? 'Administrator multi-factor authentication is required but not implemented. Provision it, or set ADMIN_MFA_REQUIRED=false to record a named, time-boxed exception.'
                    : 'Administrator multi-factor authentication requirement is satisfied.',
                blocking: $required && ! $mfaImplemented
            ),
            $this->makeCheck(
                'session_idle_timeout',
                (int) config('sekolah-kita.security.session_idle_timeout_minutes') > 0
                    && (int) config('sekolah-kita.security.session_idle_timeout_minutes')
                        <= (int) config('session.lifetime'),
                'SESSION_IDLE_TIMEOUT must be greater than zero and no longer than the session lifetime.',
                blocking: false
            ),
            $this->makeCheck(
                'audit_failure_mode',
                (string) config('sekolah-kita.audit.failure_mode') === 'throw',
                'AUDIT_FAILURE_MODE must be "throw" so a failed audit write never silently discards accountability.'
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function configuredRedisConnections(): array
    {
        $connections = [
            'default' => (string) (config('session.connection') ?: 'default'),
            'cache' => (string) config('cache.stores.redis.connection', 'cache'),
            'queue' => (string) config('queue.connections.redis.connection', 'queue'),
            'rate_limits' => (string) config('cache.stores.rate_limits.connection', 'rate_limits'),
        ];

        return $connections;
    }

    private function redisClientIsAvailable(): bool
    {
        return match ((string) config('database.redis.client', 'phpredis')) {
            'phpredis' => extension_loaded('redis'),
            'predis' => class_exists(Client::class),
            default => false,
        };
    }

    private function redisConnectionResponds(string $connection): bool
    {
        try {
            $response = Redis::connection($connection)->ping();

            return $response !== false && $response !== null;
        } catch (Throwable) {
            return false;
        }
    }

    private function cacheRoundTrips(): bool
    {
        try {
            $key = 'readiness:'.bin2hex(random_bytes(8));
            cache()->put($key, 'ok', 30);

            $value = cache()->pull($key);

            return $value === 'ok';
        } catch (Throwable) {
            return false;
        }
    }

    private function rateLimiterStoreResolves(): bool
    {
        try {
            cache()->store(config('cache.limiter'))->put(
                'readiness:limiter:'.bin2hex(random_bytes(8)),
                'ok',
                10
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function databaseVersion(): ?string
    {
        try {
            $version = DB::selectOne('SELECT VERSION() AS version');

            return $version === null ? null : (string) $version->version;
        } catch (Throwable) {
            return null;
        }
    }

    private function configurationIsCached(): bool
    {
        return app()->configurationIsCached();
    }

    private function appIsStateless(): bool
    {
        return in_array((string) config('session.driver'), ['null'], true);
    }

    /**
     * @return array{name: string, ok: bool, blocking: bool, message: string}
     */
    private function makeCheck(string $name, bool $ok, string $failureMessage, bool $blocking = true): array
    {
        return [
            'name' => $name,
            'ok' => $ok,
            'blocking' => $blocking,
            'message' => $ok ? $this->successMessage($name) : $failureMessage,
        ];
    }

    private function successMessage(string $name): string
    {
        return ucfirst(str_replace('_', ' ', $name)).' is correctly configured.';
    }

    private function hasExplicitValue(mixed $values): bool
    {
        if (! is_array($values)) {
            return is_string($values) && trim($values) !== '' && trim($values) !== '*';
        }

        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '' && trim($value) !== '*') {
                return true;
            }
        }

        return false;
    }
}
