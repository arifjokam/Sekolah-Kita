<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Tracks the server-side session identifiers currently bound to a user so
 * that account lifecycle events (suspension, deactivation, role change, and
 * password reset) can revoke them.
 *
 * The framework can only enumerate sessions for the database driver, where a
 * `user_id` column supports a direct `DELETE`. Redis-backed sessions are stored
 * by Illuminate\Session\CacheBasedSessionHandler as opaque values in the cache
 * repository, keyed by session id, so there is no user column to query and the
 * mapping has to be maintained explicitly on the write path.
 *
 * Revocation deliberately goes through the cache repository rather than a
 * hand-composed Redis key. The repository is the same object the session
 * handler reads and writes through, so it is the only place that correctly
 * applies the cache prefix and the connection prefix, and it keeps working if
 * the session store is changed from the default.
 *
 * Registration is best effort. If Redis is unavailable the session is simply
 * not tracked, because failing a user's request because the revocation index
 * could not be written would be a worse availability outcome than a delayed
 * revocation. Redis availability is covered separately by platform:readiness.
 */
class UserSessionRegistry
{
    /**
     * A user realistically holds only a handful of concurrent sessions. The
     * cap keeps the registry key bounded on a long-lived deployment.
     */
    private const MAX_TRACKED_SESSIONS = 20;

    public function __construct(private readonly RedisFactory $redis) {}

    /**
     * Record that the given session id currently belongs to the user.
     */
    public function register(User $user, string $sessionId): void
    {
        if ($sessionId === '' || ! $this->enabled()) {
            return;
        }

        try {
            $key = $this->registryKey($user);
            $connection = $this->connection();

            $connection->lrem($key, 0, $sessionId);
            $connection->lpush($key, $sessionId);
            $connection->ltrim($key, 0, self::MAX_TRACKED_SESSIONS - 1);
            $connection->expire($key, $this->ttlSeconds());
        } catch (\Throwable $exception) {
            $this->reportUnavailable('register', $exception);
        }
    }

    /**
     * Register the current request session, if there is an authenticated user.
     */
    public function registerCurrentSession(?User $user, ?Session $session): void
    {
        if ($user === null || $session === null) {
            return;
        }

        $this->register($user, $session->getId());
    }

    /**
     * Revoke every tracked session for the user.
     *
     * @return int the number of session entries removed
     */
    public function revokeAll(User $user): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $key = $this->registryKey($user);
        $sessionIds = [];

        try {
            $sessionIds = array_values(array_filter(
                (array) $this->connection()->lrange($key, 0, self::MAX_TRACKED_SESSIONS - 1),
                static fn (mixed $value): bool => is_string($value) && $value !== '',
            ));
        } catch (\Throwable $exception) {
            $this->reportUnavailable('revoke', $exception);
        }

        $store = Cache::store(config('session.store'));
        $revoked = 0;

        foreach ($sessionIds as $sessionId) {
            try {
                $revoked += $store->forget($sessionId) ? 1 : 0;
            } catch (\Throwable $exception) {
                $this->reportUnavailable('revoke', $exception);
            }
        }

        try {
            $this->connection()->del($key);
        } catch (\Throwable $exception) {
            $this->reportUnavailable('revoke', $exception);
        }

        return $revoked;
    }

    public function enabled(): bool
    {
        return config('session.driver') === 'redis';
    }

    private function connection(): mixed
    {
        return $this->redis->connection((string) (config('session.connection') ?: 'default'));
    }

    /**
     * The registry lives on the Redis connection directly rather than in the
     * cache repository. Session ids are 40-character random strings, so a
     * `user-sessions:<id>` key can never collide with a session key even
     * though the two are namespaced differently.
     */
    private function registryKey(User $user): string
    {
        return 'user-sessions:'.$user->getKey();
    }

    private function ttlSeconds(): int
    {
        return max(300, (int) config('session.lifetime', 120) * 60);
    }

    private function reportUnavailable(string $operation, \Throwable $exception): void
    {
        Log::warning('security.session_registry_unavailable', [
            'operation' => $operation,
            'exception' => $exception::class,
        ]);
    }
}
