<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthCheckService
{
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
            DB::connection((string) config('queue.connections.database.connection'))->table((string) config('queue.connections.database.table', 'jobs'))->limit(1)->get();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
