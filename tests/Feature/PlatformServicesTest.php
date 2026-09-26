<?php

namespace Tests\Feature;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Jobs\WarmPublicContentCache;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\ContactMessageReceivedNotification;
use App\Services\AuditLogService;
use App\Services\ProductionReadinessService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlatformServicesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_platform_tables_and_mariadb_indexes_exist(): void
    {
        $this->assertTrue(Schema::hasTable('audit_logs'));
        $this->assertTrue(Schema::hasTable('notifications'));
        $this->assertTrue(Schema::hasTable('notification_preferences'));
        $this->assertTrue(Schema::hasColumns('audit_logs', ['actor_user_id', 'action', 'result', 'changed_fields', 'correlation_id', 'occurred_at']));
        $this->assertTrue(Schema::hasColumns('notifications', ['notifiable_type', 'notifiable_id', 'read_at']));

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MariaDB-specific index test');
        }

        $this->assertNotEmpty(DB::select('SHOW INDEX FROM audit_logs WHERE Key_name = "audit_logs_actor_time_index"'));
        $this->assertNotEmpty(DB::select('SHOW INDEX FROM audit_logs WHERE Key_name = "audit_logs_occurred_at_index"'));
        $this->assertNotEmpty(DB::select('SHOW INDEX FROM admission_applications WHERE Key_name = "admission_applications_duplicate_lookup_index"'));
        $this->assertNotEmpty(DB::select('SHOW INDEX FROM notifications WHERE Key_name = "notifications_recipient_read_index"'));
        $this->assertNotEmpty(DB::select('SHOW INDEX FROM notifications WHERE Key_name = "notifications_recipient_timeline_index"'));
    }

    public function test_notification_listing_query_is_satisfied_by_an_index(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MariaDB-specific query plan test');
        }

        // Regression guard: without a created_at suffix on the recipient index
        // this newest-first listing degrades into a full sort of the user's
        // entire notification history on every page load.
        $plan = DB::select(
            'EXPLAIN SELECT * FROM notifications WHERE notifiable_type = ? AND notifiable_id = ? ORDER BY created_at DESC LIMIT 20',
            [User::class, 1],
        );

        $this->assertNotEmpty($plan);
        $this->assertStringNotContainsString('filesort', strtolower((string) $plan[0]->Extra));
    }

    public function test_audit_context_redacts_sensitive_values(): void
    {
        $admin = User::factory()->administrator()->create();

        $log = app(AuditLogService::class)->record(
            actor: $admin,
            action: 'test.sensitive',
            targetType: 'contact_message',
            targetId: 42,
            context: [
                'email' => 'private@example.test',
                'body' => 'raw private message',
                'status' => 'new',
            ],
        );

        $this->assertInstanceOf(AuditLog::class, $log);
        $encoded = json_encode($log->changed_fields, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('private@example.test', $encoded);
        $this->assertStringNotContainsString('raw private message', $encoded);
        $this->assertSame('new', $log->changed_fields['status']);
    }

    public function test_audit_request_context_uses_uuid_safe_correlation_ids(): void
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_X_REQUEST_ID' => str_repeat('a', 64),
        ]);
        $context = app(AuditLogService::class)->requestContext($request);

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $context['correlation_id']);
        $this->assertSame(36, strlen($context['correlation_id']));
    }

    public function test_only_active_verified_administrators_can_view_audit_logs(): void
    {
        $log = AuditLog::factory()->create();
        $administrator = User::factory()->administrator()->create();
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->actingAs($administrator)->get(route('admin.audit-logs.index'))->assertOk()->assertSee($log->action);
        $this->actingAs($administrator)->get(route('admin.audit-logs.show', $log))->assertOk()->assertSee($log->action);
    }

    public function test_contact_submission_queues_safe_administrator_notification(): void
    {
        Notification::fake();
        $administrator = User::factory()->administrator()->create();

        $this->post(route('contact.store'), [
            'name' => 'Pengirim',
            'email' => 'pengirim@example.test',
            'subject' => 'Informasi',
            'message' => 'Halo sekolah',
        ])->assertRedirect(route('contact.create'));

        Notification::assertSentTo($administrator, ContactMessageReceivedNotification::class);
    }

    public function test_notification_read_and_preferences_are_owned_by_the_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $owner->notify(new ContactMessageReceivedNotification(123));
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($otherUser)->post(route('notifications.read', $notification->id))->assertNotFound();
        $this->actingAs($owner)->put(route('notifications.preferences.update'), [
            'channel' => NotificationChannel::InApp->value,
            'category' => NotificationCategory::System->value,
            'enabled' => '0',
        ])->assertRedirect();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $owner->id,
            'channel' => NotificationChannel::InApp->value,
            'category' => NotificationCategory::System->value,
            'enabled' => false,
        ]);

        $this->actingAs($owner)->post(route('notifications.read', $notification->id))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_health_endpoint_and_security_headers_are_available(): void
    {
        $response = $this->get(route('health.ready'));

        $response->assertOk()
            ->assertJsonPath('checks.database', true)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_audit_records_are_append_only_at_the_model_boundary(): void
    {
        $log = AuditLog::factory()->create();

        $this->expectException(\LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_production_readiness_reports_configuration_without_secrets(): void
    {
        $result = app(ProductionReadinessService::class)->check();

        $this->assertArrayHasKey('ready', $result);
        $this->assertContains('environment', array_column($result['checks'], 'name'));
        $this->assertStringNotContainsString(config('app.key'), json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function test_readiness_reports_granular_http_checks_and_rejects_wildcards(): void
    {
        $result = $this->readinessWithSecurityConfiguration();

        foreach (['https_url', 'trusted_proxies', 'trusted_hosts', 'secure_cookies', 'http_only_cookies'] as $name) {
            $this->assertTrue(
                $this->readinessCheck($result, $name)['ok'],
                $name.' should pass for an explicit https configuration.'
            );
        }

        // A wildcard trusted proxy means any host can spoof X-Forwarded-*.
        $result = $this->readinessWithSecurityConfiguration(['app.trusted_proxies' => ['*']]);
        $this->assertFalse($this->readinessCheck($result, 'trusted_proxies')['ok']);

        $result = $this->readinessWithSecurityConfiguration(['app.trusted_hosts' => ['*']]);
        $this->assertFalse($this->readinessCheck($result, 'trusted_hosts')['ok']);

        // A plaintext APP_URL invalidates the secure-cookie and HSTS posture.
        $result = $this->readinessWithSecurityConfiguration(['app.url' => 'http://school.example']);
        $this->assertFalse($this->readinessCheck($result, 'https_url')['ok']);

        $result = $this->readinessWithSecurityConfiguration(['session.secure' => false]);
        $this->assertFalse($this->readinessCheck($result, 'secure_cookies')['ok']);
    }

    public function test_readiness_distinguishes_dependency_groups(): void
    {
        $result = app(ProductionReadinessService::class)->check();

        $this->assertSame([
            'application',
            'http',
            'redis',
            'cache',
            'session',
            'queue',
            'rate_limiting',
            'database',
            'storage',
            'mail',
            'administrator_security',
        ], array_keys($result['groups']));

        // Every check is explicitly marked blocking or advisory.
        foreach ($result['checks'] as $check) {
            $this->assertArrayHasKey('blocking', $check);
            $this->assertIsBool($check['blocking']);
        }
    }

    public function test_readiness_rejects_database_backed_runtime_topology_in_production(): void
    {
        config([
            'sekolah-kita.platform.redis_required_in_production' => true,
            'cache.default' => 'database',
            'session.driver' => 'database',
            'queue.default' => 'database',
            'cache.limiter' => 'database',
        ]);

        $result = app(ProductionReadinessService::class)->check();

        $this->assertFalse($this->readinessCheck($result, 'cache_driver')['ok']);
        $this->assertFalse($this->readinessCheck($result, 'session_driver')['ok']);
        $this->assertFalse($this->readinessCheck($result, 'queue_driver')['ok']);
        $this->assertFalse($this->readinessCheck($result, 'rate_limiter_store')['ok']);
        $this->assertFalse($result['ready']);
    }

    public function test_readiness_fails_closed_when_worker_timeout_could_duplicate_jobs(): void
    {
        config([
            'queue.default' => 'redis',
            'queue.connections.redis.retry_after' => 90,
            'sekolah-kita.queue.worker_timeout_seconds' => 150,
        ]);

        $result = app(ProductionReadinessService::class)->check();

        $this->assertFalse($this->readinessCheck($result, 'queue_retry_after')['ok']);

        config(['queue.connections.redis.retry_after' => 300]);
        $result = app(ProductionReadinessService::class)->check();
        $this->assertTrue($this->readinessCheck($result, 'queue_retry_after')['ok']);
    }

    public function test_readiness_reports_administrator_mfa_as_a_blocking_gap(): void
    {
        config(['sekolah-kita.security.administrator_mfa_required' => true]);
        $result = app(ProductionReadinessService::class)->check();
        $mfa = $this->readinessCheck($result, 'administrator_mfa');

        $this->assertFalse($mfa['ok']);
        $this->assertTrue($mfa['blocking']);
        $this->assertStringContainsString('not implemented', $mfa['message']);

        // A named owner can record an accepted, time-boxed exception.
        config(['sekolah-kita.security.administrator_mfa_required' => false]);
        $result = app(ProductionReadinessService::class)->check();
        $this->assertTrue($this->readinessCheck($result, 'administrator_mfa')['ok']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function readinessWithSecurityConfiguration(array $overrides = []): array
    {
        config(array_merge([
            'sekolah-kita.audit.failure_mode' => 'throw',
            'sekolah-kita.platform.redis_required_in_production' => true,
            'app.trusted_proxies' => ['10.0.0.0/8'],
            'app.trusted_hosts' => ['^school.example$'],
            'app.url' => 'https://school.example',
            'session.secure' => true,
            'session.http_only' => true,
        ], $overrides));

        return app(ProductionReadinessService::class)->check();
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function readinessCheck(array $result, string $name): array
    {
        $check = collect($result['checks'])->firstWhere('name', $name);

        $this->assertIsArray($check, 'The readiness report is missing the "'.$name.'" check.');

        return $check;
    }

    public function test_cache_warm_command_dispatches_bounded_maintenance_job(): void
    {
        Queue::fake();

        $this->artisan('platform:warm-cache')->assertSuccessful();

        Queue::assertPushed(WarmPublicContentCache::class);
    }
}
