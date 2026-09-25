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

    public function test_readiness_uses_cached_security_configuration_and_requires_strict_redis_flag(): void
    {
        config([
            'sekolah-kita.audit.failure_mode' => 'throw',
            'sekolah-kita.platform.redis_required_in_production' => true,
            'app.trusted_proxies' => ['10.0.0.0/8'],
            'app.trusted_hosts' => ['^school.example$'],
            'app.url' => 'https://school.example',
            'session.secure' => true,
            'session.http_only' => true,
        ]);

        $result = app(ProductionReadinessService::class)->check();
        $securityCheck = collect($result['checks'])->firstWhere('name', 'security_configuration');
        $this->assertTrue($securityCheck['ok']);

        config(['sekolah-kita.platform.redis_required_in_production' => false]);
        $result = app(ProductionReadinessService::class)->check();
        $securityCheck = collect($result['checks'])->firstWhere('name', 'security_configuration');
        $this->assertFalse($securityCheck['ok']);

        config([
            'sekolah-kita.platform.redis_required_in_production' => true,
            'app.trusted_proxies' => ['*'],
        ]);
        $result = app(ProductionReadinessService::class)->check();
        $securityCheck = collect($result['checks'])->firstWhere('name', 'security_configuration');
        $this->assertFalse($securityCheck['ok']);
    }

    public function test_cache_warm_command_dispatches_bounded_maintenance_job(): void
    {
        Queue::fake();

        $this->artisan('platform:warm-cache')->assertSuccessful();

        Queue::assertPushed(WarmPublicContentCache::class);
    }
}
