<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Runtime health reporting for the HTTP health endpoints.
 *
 * Liveness and readiness are deliberately separate:
 *
 *  - Liveness answers "is this PHP process able to serve a request at all".
 *    It must not touch MariaDB or Redis. If it did, a dependency outage would
 *    make every upstream report the whole application as dead and a load
 *    balancer would remove all of it, turning a partial degradation into a
 *    total outage.
 *
 *  - Readiness answers "can this process serve traffic right now", and does
 *    check the dependencies that a request actually needs.
 */
class HealthCheckService
{
    /**
     * @return array{status: string, checks: array<string, bool>}
     */
    public function liveness(): array
    {
        return [
            'status' => 'ok',
            'checks' => [
                'php' => true,
            ],
        ];
    }

    /**
     * @return array{healthy: bool, checks: array<string, bool>}
     */
    public function check(): array
    {
        $checks = [
            'database' => $this->databaseIsHealthy(),
            'cache' => $this->cacheIsHealthy(),
            'queue' => $this->queueBackendIsHealthy(),
        ];

        $required = [
            'database' => true,
            'cache' => config('cache.default') !== 'null',
            'queue' => config('queue.default') !== 'null',
        ];

        $healthy = true;

        foreach ($required as $check => $isRequired) {
            if ($isRequired && ! $checks[$check]) {
                $healthy = false;
            }
        }

        return [
            'healthy' => $healthy,
            'checks' => $checks,
        ];
    }

    private function databaseIsHealthy(): bool
    {
        try {
            return DB::selectOne('SELECT 1 AS healthy') !== null;
        } catch (Throwable) {
            return false;
        }
    }

    private function cacheIsHealthy(): bool
    {
        try {
            $key = 'health:'.bin2hex(random_bytes(8));
            Cache::put($key, 'ok', 10);

            return Cache::pull($key) === 'ok';
        } catch (Throwable) {
            return false;
        }
    }

    private function queueBackendIsHealthy(): bool
    {
        $connection = (string) config('queue.default');

        if (in_array($connection, ['sync', 'null'], true)) {
            return $connection === 'sync';
        }

        if ($connection === 'redis') {
            try {
                $response = Redis::connection((string) config('queue.connections.redis.connection', 'default'))->ping();

                return $response !== false && $response !== null;
            } catch (Throwable) {
                return false;
            }
        }

        try {
            DB::connection((string) config('queue.connections.database.connection'))
                ->table((string) config('queue.connections.database.table', 'jobs'))
                ->limit(1)
                ->get();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
