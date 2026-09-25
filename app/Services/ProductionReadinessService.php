<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductionReadinessService
{
    /**
     * @return array{ready: bool, checks: list<array{name: string, ok: bool, message: string}>}
     */
    public function check(): array
    {
        $checks = [
            $this->checkEnvironment(),
            $this->checkApplicationKey(),
            $this->checkDatabase(),
            $this->checkMigrations(),
            $this->checkRedis(),
            $this->checkStorage(),
            $this->checkMail(),
            $this->checkSecurityConfiguration(),
        ];

        return [
            'ready' => ! in_array(false, array_column($checks, 'ok'), true),
            'checks' => $checks,
        ];
    }

    /**
     * @return array{name: string, ok: bool, message: string}
     */
    private function checkEnvironment(): array
    {
        $ok = app()->environment('production') && config('app.debug') === false;

        return ['name' => 'environment', 'ok' => $ok, 'message' => $ok ? 'Production mode with debug disabled.' : 'Application must run in production with debug disabled.'];
    }

    /**
     * @return array{name: string, ok: bool, message: string}
     */
    private function checkApplicationKey(): array
    {
        $ok = filled(config('app.key'));

        return ['name' => 'application_key', 'ok' => $ok, 'message' => $ok ? 'Application key is configured.' : 'Application key is missing.'];
    }

    /**
     * @return array{name: string, ok: bool, message: string}
     */
    private function checkDatabase(): array
    {
        if (! in_array(config('database.default'), ['mysql', 'mariadb'], true)) {
            return ['name' => 'database', 'ok' => false, 'message' => 'Production must use the MariaDB connection.'];
        }

        try {
            $version = (string) DB::selectOne('SELECT VERSION() AS version')->version;

            return [
                'name' => 'database',
                'ok' => str_contains(strtolower($version), 'mariadb'),
                'message' => str_contains(strtolower($version), 'mariadb') ? 'MariaDB connection is reachable.' : 'The database server is not MariaDB.',
            ];
        } catch (Throwable) {
            return ['name' => 'database', 'ok' => false, 'message' => 'The MariaDB connection could not be verified.'];
        }
    }

    /**
     * @return array{name: string, ok: bool, message: string}
     */
    private function checkMigrations(): array
    {
        try {
            $files = array_map(
                static fn (string $file): string => pathinfo($file, PATHINFO_FILENAME),
                glob(database_path('migrations/*.php')) ?: [],
            );
            $ran = DB::table('migrations')->pluck('migration')->all();
            $pending = array_values(array_diff($files, $ran));
            $ok = $pending === [];

            return ['name' => 'migrations', 'ok' => $ok, 'message' => $ok ? 'All migration files are recorded as run.' : count($pending).' migration(s) are pending.'];
        } catch (Throwable) {
            return ['name' => 'migrations', 'ok' => false, 'message' => 'Migration state could not be read.'];
        }
    }

    /**
     * @return array{name: string, ok: bool, message: string}
     */
    private function checkRedis(): array
    {
        $configured = config('cache.default') === 'redis'
            && config('session.driver') === 'redis'
            && config('queue.default') === 'redis';

        if (! $configured) {
            return ['name' => 'redis', 'ok' => false, 'message' => 'Cache, session, and queue drivers must use Redis.'];
        }

        try {
            $connections = array_values(array_unique(array_filter([
                (string) config('cache.stores.redis.connection', 'cache'),
                (string) (config('session.connection') ?: 'default'),
                (string) config('queue.connections.redis.connection', 'queue'),
            ])));
            foreach ($connections as $connection) {
                $response = Redis::connection($connection)->ping();
                if ($response === false || $response === null) {
                    return ['name' => 'redis', 'ok' => false, 'message' => 'A configured Redis connection did not return a healthy response.'];
                }
            }

            return ['name' => 'redis', 'ok' => true, 'message' => 'Configured Redis connections are reachable.'];
        } catch (Throwable) {
            return ['name' => 'redis', 'ok' => false, 'message' => 'A configured Redis connection is not reachable.'];
        }
    }

    /**
     * @return array{name: string, ok: bool, message: string}
     */
    private function checkStorage(): array
    {
        try {
            $ok = is_dir(storage_path('app/private')) && is_writable(storage_path('app/private'))
                && is_dir(storage_path('app/public')) && is_writable(storage_path('app/public'))
                && Storage::disk('local')->getDriver() !== null;

            return ['name' => 'storage', 'ok' => $ok, 'message' => $ok ? 'Private and public storage roots are writable.' : 'Storage roots are missing or not writable.'];
        } catch (Throwable) {
            return ['name' => 'storage', 'ok' => false, 'message' => 'Storage could not be verified.'];
        }
    }

    /**
     * @return array{name: string, ok: bool, message: string}
     */
    private function checkMail(): array
    {
        $mailer = (string) config('mail.default');
        $transport = (string) config('mail.mailers.'.$mailer.'.transport');
        $failoverMailers = config('mail.mailers.failover.mailers', []);
        $ok = $mailer !== ''
            && ! in_array($mailer, ['log', 'array', 'null'], true)
            && $transport !== ''
            && ! ($mailer === 'failover' && in_array('log', (array) $failoverMailers, true));

        return ['name' => 'mail', 'ok' => $ok, 'message' => $ok ? 'A production mail transport is configured.' : 'Production mail transport is not configured.'];
    }

    /**
     * @return array{name: string, ok: bool, message: string}
     */
    private function checkSecurityConfiguration(): array
    {
        $ok = config('sekolah-kita.audit.failure_mode') === 'throw'
            && config('sekolah-kita.platform.redis_required_in_production') === true
            && $this->hasExplicitTrustedValue(config('app.trusted_proxies'))
            && $this->hasExplicitTrustedValue(config('app.trusted_hosts'))
            && str_starts_with((string) config('app.url'), 'https://')
            && config('session.secure') === true
            && config('session.http_only') === true;

        return ['name' => 'security_configuration', 'ok' => $ok, 'message' => $ok ? 'Critical security settings are present.' : 'HTTPS, trusted proxy/host, secure session, and fail-closed audit settings are incomplete.'];
    }

    private function hasExplicitTrustedValue(mixed $values): bool
    {
        if (! is_array($values)) {
            return false;
        }

        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '' && trim($value) !== '*') {
                return true;
            }
        }

        return false;
    }
}
