<?php

namespace App\Services;

use App\Contracts\ContactableNotification;
use App\Contracts\WhatsAppProvider;
use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\UserRole;
use App\Jobs\SendQueuedWhatsAppMessage;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Throwable;

/**
 * The single write path for notifications that can also reach a phone.
 *
 * Two channels, one call site:
 *
 *  1. the in-app database notification always goes out, so a deployment with no
 *     WhatsApp gateway still tells an applicant everything they need to know;
 *  2. the WhatsApp message is dispatched to the queue and its *real* outcome is
 *     written to notification_deliveries as Sent, Failed or NotConfigured.
 *
 * The property this service exists to guarantee is that no code path can report
 * a WhatsApp send that did not happen. The delivery row is written from the
 * provider's own result, and an unconfigured provider yields NotConfigured with
 * a reason rather than silence.
 */
class NotificationService
{
    public function __construct(
        private readonly WhatsAppProvider $whatsapp,
        private readonly NotificationPreferenceService $preferences,
    ) {}

    /**
     * Deliver a notification, in-app and optionally over WhatsApp.
     *
     * A null recipient means "there is no account to notify in-app": the message
     * is still queued for WhatsApp. That is the case for an application created
     * through the public one-shot PPDB form, which has no portal account — the
     * guardian's phone is the only reachable destination, and pretending
     * otherwise would either drop the message or invent an account.
     *
     * @param  string|null  $whatsappNumber  the destination; null records a
     *                                       Skipped delivery rather than
     *                                       silently doing nothing.
     */
    public function deliver(
        ?User $recipient,
        Notification $notification,
        NotificationCategory $category,
        ?string $whatsappNumber = null,
    ): void {
        if ($recipient !== null && $this->preferences->isEnabled($recipient, NotificationChannel::InApp, $category)) {
            NotificationFacade::send($recipient, $notification);
        }

        $number = $this->normaliseNumber($whatsappNumber);

        if (! $notification instanceof ContactableNotification) {
            return;
        }

        if ($number === null) {
            $this->recordOutcome(
                $recipient,
                $notification::class,
                null,
                NotificationDeliveryStatus::Skipped,
                'Tidak ada nomor WhatsApp yang tersedia untuk penerima ini.',
                0,
            );

            return;
        }

        if ($recipient !== null && ! $this->preferences->isEnabled($recipient, NotificationChannel::WhatsApp, $category)) {
            $this->recordOutcome(
                $recipient,
                $notification::class,
                $number,
                NotificationDeliveryStatus::Skipped,
                'Penerima menonaktifkan kanal WhatsApp.',
                0,
            );

            return;
        }

        $message = $notification->toWhatsApp();

        SendQueuedWhatsAppMessage::dispatch(
            recipientId: (int) ($recipient?->getKey() ?? 0),
            notificationClass: $notification::class,
            number: $number,
            title: $message['title'],
            body: $message['body'],
        );
    }

    /**
     * Fan a notification out to every active, email-verified administrator.
     */
    public function deliverToAdministrators(Notification $notification, NotificationCategory $category): void
    {
        User::query()
            ->where('role', UserRole::Administrator->value)
            ->where('status', 'active')
            ->whereNotNull('email_verified_at')
            ->with('notificationPreferences')
            ->chunkById(100, function (Collection $administrators) use ($notification, $category): void {
                foreach ($administrators as $administrator) {
                    if ($this->preferences->isEnabled($administrator, NotificationChannel::InApp, $category)) {
                        NotificationFacade::send($administrator, $notification);
                    }
                }
            });
    }

    /**
     * The current WhatsApp provider state, for an operator-facing screen.
     *
     * @return array{driver: string, configured: bool, reason: string|null}
     */
    public function whatsappStatus(): array
    {
        return [
            'driver' => $this->whatsapp->name(),
            'configured' => $this->whatsapp->isConfigured(),
            'reason' => $this->whatsapp->unavailableReason(),
        ];
    }

    /**
     * Recent WhatsApp delivery attempts, newest first.
     *
     * @return Collection<int, NotificationDelivery>
     */
    public function recentWhatsAppDeliveries(int $limit = 10): Collection
    {
        return NotificationDelivery::query()
            ->where('channel', NotificationChannel::WhatsApp->value)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Write the durable outcome of one WhatsApp attempt.
     *
     * Never throws: losing the bookkeeping row must not turn a handled delivery
     * problem into an unhandled one.
     */
    public function recordOutcome(
        ?User $notifiable,
        string $notificationClass,
        ?string $number,
        NotificationDeliveryStatus $status,
        ?string $error,
        int $attempts,
        ?string $body = null,
    ): ?NotificationDelivery {
        try {
            return NotificationDelivery::query()->create([
                'channel' => NotificationChannel::WhatsApp,
                'provider' => $this->whatsapp->name(),
                'notification_class' => Str::limit($notificationClass, 255, ''),
                'notifiable_type' => $notifiable?->getMorphClass(),
                'notifiable_id' => $notifiable?->getKey(),
                'recipient' => $number ?? '—',
                'status' => $status,
                'attempts' => $attempts,
                'body' => $body === null ? null : Str::limit($body, 2000, ''),
                'last_error' => $error === null ? null : Str::limit($error, 500, ''),
                'sent_at' => $status === NotificationDeliveryStatus::Sent ? now() : null,
            ]);
        } catch (Throwable $exception) {
            Log::error('notification.delivery_record_failed', [
                'channel' => NotificationChannel::WhatsApp->value,
                'notification' => $notificationClass,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    /**
     * Normalise an Indonesian phone number to the international form.
     *
     * Returns null rather than a guess when nothing numeric is present, so a
     * blank field is never turned into a message addressed to "0".
     */
    public function normaliseNumber(?string $number): ?string
    {
        if ($number === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (strlen($digits) < 8) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        return '62'.$digits;
    }
}
