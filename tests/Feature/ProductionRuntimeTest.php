<?php

namespace Tests\Feature;

use App\Listeners\LogQueueFailures;
use App\Models\User;
use App\Providers\RuntimeTrustServiceProvider;
use App\Services\PlatformRuntimeGuard;
use App\Services\UserSessionRegistry;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Regression coverage for the Phase 8 production runtime wiring.
 *
 * The trusted proxy/host tests are the important ones: the original
 * implementation read env() from bootstrap/app.php, which Laravel evaluates
 * while resolving the HTTP kernel and therefore before the environment is
 * loaded. The configuration was present in config/app.php but never reached
 * the framework middleware, so X-Forwarded-Proto and X-Forwarded-For were
 * silently ignored behind a reverse proxy.
 */
class ProductionRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TrustProxies::flushState();
        TrustHosts::flushState();
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        TrustHosts::flushState();

        parent::tearDown();
    }

    public function test_trusted_proxies_are_applied_from_configuration(): void
    {
        config(['app.trusted_proxies' => ['127.0.0.1']]);

        (new RuntimeTrustServiceProvider($this->app))->boot();

        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        (new TrustProxies)->handle($request, static fn (Request $request) => $request);

        $this->assertSame('203.0.113.9', $request->ip());
        $this->assertTrue($request->isSecure());
    }

    public function test_untrusted_peer_forwarded_headers_are_ignored(): void
    {
        config(['app.trusted_proxies' => ['10.0.0.0/8']]);

        (new RuntimeTrustServiceProvider($this->app))->boot();

        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.50',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.7',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        (new TrustProxies)->handle($request, static fn (Request $request) => $request);

        $this->assertSame('203.0.113.50', $request->ip());
        $this->assertFalse($request->isSecure());
    }

    public function test_trusted_hosts_are_applied_from_configuration(): void
    {
        config(['app.trusted_hosts' => ['^school\.example$']]);

        (new RuntimeTrustServiceProvider($this->app))->boot();

        $middleware = new TrustHosts($this->app);

        $this->assertSame(['^school\.example$'], $this->invokeProtected($middleware, 'hosts'));
    }

    public function test_no_trust_configuration_leaves_framework_defaults_untouched(): void
    {
        config(['app.trusted_proxies' => [], 'app.trusted_hosts' => []]);

        (new RuntimeTrustServiceProvider($this->app))->boot();

        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);

        (new TrustProxies)->handle($request, static fn (Request $request) => $request);

        $this->assertSame('127.0.0.1', $request->ip());
    }

    public function test_runtime_guard_is_inert_outside_production(): void
    {
        config([
            'app.debug' => true,
            'app.url' => 'http://localhost',
            'session.secure' => false,
        ]);

        $this->assertNotSame('production', app()->environment());
        $this->assertSame([], PlatformRuntimeGuard::failures());
    }

    public function test_runtime_guard_fails_closed_for_each_production_gap(): void
    {
        $this->app['env'] = 'production';

        config([
            'app.debug' => true,
            'app.url' => 'http://school.example',
            'app.trusted_proxies' => ['*'],
            'app.trusted_hosts' => ['*'],
            'session.secure' => false,
            'session.http_only' => false,
            'session.same_site' => 'none',
            'session.driver' => 'database',
            'cache.default' => 'database',
            'queue.default' => 'database',
            'sekolah-kita.audit.failure_mode' => 'ignore',
        ]);

        $failures = implode("\n", PlatformRuntimeGuard::failures());

        $this->assertStringContainsString('APP_DEBUG must be false', $failures);
        $this->assertStringContainsString('must use the https scheme', $failures);
        $this->assertStringContainsString('must not be wildcards', $failures);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE', $failures);
        $this->assertStringContainsString('SESSION_HTTP_ONLY', $failures);
        $this->assertStringContainsString('SESSION_SAME_SITE=none', $failures);
        $this->assertStringContainsString('must not use the database driver', $failures);
        $this->assertStringContainsString('AUDIT_FAILURE_MODE', $failures);
    }

    public function test_runtime_guard_throws_outside_diagnostic_commands(): void
    {
        $this->app['env'] = 'production';

        config(['app.debug' => true]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('refused to start in production');

        PlatformRuntimeGuard::enforce();
    }

    public function test_session_registry_is_inert_for_non_redis_session_drivers(): void
    {
        config(['session.driver' => 'array']);

        $registry = app(UserSessionRegistry::class);

        $this->assertFalse($registry->enabled());
        $this->assertSame(0, $registry->revokeAll(User::factory()->make()));
    }

    public function test_rate_limiter_store_defaults_to_the_configured_cache_store(): void
    {
        // config/cache.php derives the limiter from CACHE_STORE so throttling
        // follows the deployment topology without bootstrap-time env() reads.
        $this->assertSame(config('cache.default'), config('cache.limiter'));
    }

    public function test_cache_prefix_is_scoped_by_environment(): void
    {
        $prefix = (string) config('cache.prefix');

        $this->assertNotSame('', $prefix);
        $this->assertStringContainsString('cache-', $prefix);

        $redisPrefix = (string) config('database.redis.options.prefix');
        $this->assertStringContainsString('database-', $redisPrefix);
    }

    public function test_redis_topology_uses_separate_logical_databases(): void
    {
        // Architectural requirement: cache, session, queue, and rate-limit
        // data must not share a logical database.
        $this->assertSame('1', (string) config('database.redis.cache.database'));
        $this->assertSame('2', (string) config('database.redis.queue.database'));
        $this->assertSame('3', (string) config('database.redis.rate_limits.database'));
    }

    public function test_liveness_endpoint_does_not_depend_on_infrastructure(): void
    {
        // A liveness probe that touched MariaDB or Redis would let a
        // dependency outage cause every node to be pulled from rotation.
        $this->get('/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.php', true);
    }

    public function test_readiness_endpoint_reports_dependency_health(): void
    {
        $this->get('/health/ready')
            ->assertOk()
            ->assertJsonPath('healthy', true)
            ->assertJsonStructure(['healthy', 'checks' => ['database', 'cache', 'queue']]);
    }

    public function test_failed_queue_jobs_are_logged_without_payloads(): void
    {
        $exception = new \RuntimeException('secret-token-value-must-not-leak');

        $job = \Mockery::mock(Job::class);
        $job->shouldReceive('getJobId')->andReturn('job-42');
        $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\ProcessAdmissionDocument');
        $job->shouldReceive('getQueue')->andReturn('media');
        $job->shouldReceive('attempts')->andReturn(3);
        // The listener must never read the serialized job body.
        $job->shouldReceive('payload')->never();
        $job->shouldReceive('getRawBody')->never();

        $failed = new JobFailed('redis', $job, $exception);

        Log::spy();
        (new LogQueueFailures)->handle($failed);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $encoded = json_encode($context, JSON_THROW_ON_ERROR);

                return $message === 'queue.job_failed'
                    && $context['connection'] === 'redis'
                    && $context['queue'] === 'media'
                    && $context['job_id'] === 'job-42'
                    && $context['attempts'] === 3
                    && $context['exception'] === \RuntimeException::class
                    && ! str_contains($encoded, 'secret-token-value-must-not-leak');
            });
    }

    public function test_mariadb_connection_does_not_declare_empty_replica_configuration(): void
    {
        $connection = (array) config('database.connections.mariadb');

        // A present-but-empty read/write key makes the framework build a
        // replica connection from an empty configuration.
        $this->assertArrayNotHasKey('read', $connection);
        $this->assertArrayNotHasKey('write', $connection);
    }

    public function test_read_write_replica_configuration_is_applied_when_hosts_are_set(): void
    {
        $values = ['DB_READ_HOST' => 'replica.internal', 'DB_READ_PORT' => '3307'];

        foreach ($values as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }

        try {
            $config = require base_path('config/database.php');

            $this->assertSame(
                ['host' => 'replica.internal', 'port' => '3307'],
                $config['connections']['mariadb']['read']
            );
        } finally {
            foreach (array_keys($values) as $key) {
                unset($_ENV[$key], $_SERVER[$key]);
                putenv($key);
            }
        }
    }

    private function invokeProtected(object $object, string $method): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($object);
    }
}
