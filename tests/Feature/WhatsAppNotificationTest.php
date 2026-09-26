<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppProvider;
use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Jobs\SendQueuedWhatsAppMessage;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\CandidatePortalNotification;
use App\Services\Messaging\FakeWhatsAppProvider;
use App\Services\Messaging\NullWhatsAppProvider;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The two-channel notification path.
 *
 * The point of this file is honesty about the second channel. With no provider
 * configured — which is the default and the state of any fresh checkout — the
 * in-app notification still goes out, the WhatsApp attempt is recorded as
 * NotConfigured with a reason, and nothing anywhere claims a message was sent.
 *
 * A fake provider is bound for the whole suite, so no test can pass or fail on a
 * real gateway, a real credential, or a real phone number.
 */
class WhatsAppNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FakeWhatsAppProvider::reset();
        config()->set('sekolah-kita.whatsapp.driver', 'fake');
    }

    protected function tearDown(): void
    {
        FakeWhatsAppProvider::reset();

        parent::tearDown();
    }

    public function test_the_fake_provider_is_bound_in_the_test_environment(): void
    {
        $this->assertInstanceOf(FakeWhatsAppProvider::class, app(WhatsAppProvider::class));
        $this->assertTrue(app(NotificationService::class)->whatsappStatus()['configured']);
    }

    public function test_an_in_app_notification_is_always_delivered(): void
    {
        $user = User::factory()->calon()->create();

        app(NotificationService::class)->deliver(
            $user,
            new CandidatePortalNotification('Judul uji', ['Baris pertama.'], NotificationCategory::Admission),
            NotificationCategory::Admission,
            '081200000001',
        );

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->getKey(),
            'notifiable_type' => $user->getMorphClass(),
        ]);

        $this->assertCount(1, $user->notifications()->get());
    }

    public function test_a_configured_provider_records_a_real_delivery(): void
    {
        $user = User::factory()->calon()->create();

        app(NotificationService::class)->deliver(
            $user,
            new CandidatePortalNotification('Pengajuan terkirim', ['Nomor: PPDB-1'], NotificationCategory::Admission),
            NotificationCategory::Admission,
            '0812-0000-0001',
        );

        $delivery = NotificationDelivery::query()->firstOrFail();

        $this->assertSame(NotificationDeliveryStatus::Sent, $delivery->status);
        $this->assertSame(NotificationChannel::WhatsApp, $delivery->channel);
        $this->assertSame('fake', $delivery->provider);
        $this->assertSame('6281200000001', $delivery->recipient, 'The number is normalised to the international form.');
        $this->assertNotNull($delivery->sent_at);

        $this->assertCount(1, FakeWhatsAppProvider::$sent);
        $this->assertSame('6281200000001', FakeWhatsAppProvider::$sent[0]['to']);
        $this->assertStringContainsString('Pengajuan terkirim', FakeWhatsAppProvider::$sent[0]['message']);
    }

    public function test_an_unconfigured_provider_is_reported_as_not_configured(): void
    {
        config()->set('sekolah-kita.whatsapp.driver', 'null');

        $user = User::factory()->calon()->create();

        app(NotificationService::class)->deliver(
            $user,
            new CandidatePortalNotification('Status', ['Berkas perlu diperbaiki.'], NotificationCategory::Admission),
            NotificationCategory::Admission,
            '081200000001',
        );

        $delivery = NotificationDelivery::query()->firstOrFail();

        $this->assertSame(NotificationDeliveryStatus::NotConfigured, $delivery->status);
        $this->assertNull($delivery->sent_at, 'A provider that cannot send must never report a send time.');
        $this->assertStringContainsString('WHATSAPP_DRIVER', (string) $delivery->last_error);

        // The in-app copy is unaffected, which is the whole point of the split.
        $this->assertCount(1, $user->notifications()->get());
    }

    public function test_the_default_driver_is_the_honest_null_provider(): void
    {
        config()->set('sekolah-kita.whatsapp.driver', null);

        $this->app->forgetInstance(WhatsAppProvider::class);
        $provider = $this->app->make(WhatsAppProvider::class);

        $this->assertInstanceOf(NullWhatsAppProvider::class, $provider);
        $this->assertFalse($provider->isConfigured());
        $this->assertNotNull($provider->unavailableReason());

        $result = $provider->send('6281200000001', 'Halo');
        $this->assertTrue($result->isNotConfigured());
        $this->assertFalse($result->isSent());
    }

    public function test_a_transport_failure_is_recorded_rather_than_thrown(): void
    {
        FakeWhatsAppProvider::$nextFailure = 'Gateway menolak: quota habis.';

        $user = User::factory()->calon()->create();

        app(NotificationService::class)->deliver(
            $user,
            new CandidatePortalNotification('Status', ['Isi.'], NotificationCategory::Admission),
            NotificationCategory::Admission,
            '081200000001',
        );

        $delivery = NotificationDelivery::query()->firstOrFail();

        $this->assertSame(NotificationDeliveryStatus::Failed, $delivery->status);
        $this->assertStringContainsString('quota habis', (string) $delivery->last_error);
        $this->assertNull($delivery->sent_at);
    }

    public function test_a_missing_number_is_recorded_as_skipped_not_ignored(): void
    {
        $user = User::factory()->calon()->create();

        app(NotificationService::class)->deliver(
            $user,
            new CandidatePortalNotification('Status', ['Isi.'], NotificationCategory::Admission),
            NotificationCategory::Admission,
            null,
        );

        $delivery = NotificationDelivery::query()->firstOrFail();

        $this->assertSame(NotificationDeliveryStatus::Skipped, $delivery->status);
        $this->assertCount(0, FakeWhatsAppProvider::$sent);
        $this->assertCount(1, $user->notifications()->get());
    }

    public function test_a_recipient_who_opted_out_skips_whatsapp_but_keeps_in_app(): void
    {
        $user = User::factory()->calon()->create();
        $user->notificationPreferences()->create([
            'channel' => NotificationChannel::WhatsApp,
            'category' => NotificationCategory::Admission,
            'enabled' => false,
        ]);
        $user->refresh();

        app(NotificationService::class)->deliver(
            $user,
            new CandidatePortalNotification('Status', ['Isi.'], NotificationCategory::Admission),
            NotificationCategory::Admission,
            '081200000001',
        );

        $delivery = NotificationDelivery::query()->firstOrFail();

        $this->assertSame(NotificationDeliveryStatus::Skipped, $delivery->status);
        $this->assertStringContainsString('menonaktifkan', (string) $delivery->last_error);
        $this->assertCount(0, FakeWhatsAppProvider::$sent);
        $this->assertCount(1, $user->notifications()->get());
    }

    public function test_an_application_without_a_portal_account_still_gets_the_message_queued(): void
    {
        // A public-form application has no account to notify in-app. The message
        // is still queued for the phone number rather than being dropped.
        $user = User::factory()->calon()->create();

        app(NotificationService::class)->deliver(
            null,
            new CandidatePortalNotification('Pengumuman PPDB', ['Pendaftaran ditutup.'], NotificationCategory::Admission),
            NotificationCategory::Admission,
            '081200000001',
        );

        $delivery = NotificationDelivery::query()->firstOrFail();

        $this->assertSame(NotificationDeliveryStatus::Sent, $delivery->status);
        $this->assertNull($delivery->notifiable_id, 'No account is invented for a recipient-less notification.');
        $this->assertCount(1, FakeWhatsAppProvider::$sent);
    }

    public function test_the_job_is_queued_rather_than_sent_inline(): void
    {
        Queue::fake();

        $user = User::factory()->calon()->create();

        app(NotificationService::class)->deliver(
            $user,
            new CandidatePortalNotification('Status', ['Isi.'], NotificationCategory::Admission),
            NotificationCategory::Admission,
            '081200000001',
        );

        // A slow or failing gateway must never block the request that triggered
        // the notification, so the WhatsApp leg is always a queued job.
        Queue::assertPushed(SendQueuedWhatsAppMessage::class, function (SendQueuedWhatsAppMessage $job) use ($user): bool {
            return $job->recipientId === (int) $user->getKey()
                && $job->number === '6281200000001'
                && $job->notificationClass === CandidatePortalNotification::class;
        });
    }

    public function test_the_job_records_a_not_configured_outcome_without_retrying(): void
    {
        config()->set('sekolah-kita.whatsapp.driver', 'null');
        $this->app->forgetInstance(WhatsAppProvider::class);

        $user = User::factory()->calon()->create();

        (new SendQueuedWhatsAppMessage(
            recipientId: (int) $user->getKey(),
            notificationClass: CandidatePortalNotification::class,
            number: '6281200000001',
            title: 'Status',
            body: 'Isi pesan.',
        ))->handle(
            $this->app->make(WhatsAppProvider::class),
            $this->app->make(NotificationService::class),
        );

        $delivery = NotificationDelivery::query()->firstOrFail();

        $this->assertSame(NotificationDeliveryStatus::NotConfigured, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame('Status'."\n".'Isi pesan.', $delivery->body);
    }

    public function test_phone_number_normalisation_covers_the_common_formats(): void
    {
        $service = $this->app->make(NotificationService::class);

        $this->assertSame('628123456789', $service->normaliseNumber('08123456789'));
        $this->assertSame('628123456789', $service->normaliseNumber('+62 812-3456-789'));
        $this->assertSame('628123456789', $service->normaliseNumber('62 812 3456 789'));
        $this->assertNull($service->normaliseNumber(''));
        $this->assertNull($service->normaliseNumber(null));
        $this->assertNull($service->normaliseNumber('12'), 'Too short to be a real number, so no guess is made.');
    }

    public function test_the_recent_delivery_list_is_channel_scoped(): void
    {
        $user = User::factory()->calon()->create();

        NotificationDelivery::factory()->count(3)->create(['notifiable_id' => $user->getKey()]);

        $deliveries = $this->app->make(NotificationService::class)->recentWhatsAppDeliveries();

        $this->assertCount(3, $deliveries);
        $this->assertTrue($deliveries->every(
            fn (NotificationDelivery $delivery): bool => $delivery->channel === NotificationChannel::WhatsApp,
        ));
    }
}
