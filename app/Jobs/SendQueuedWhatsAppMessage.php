<?php

namespace App\Jobs;

use App\Contracts\WhatsAppProvider;
use App\Enums\NotificationDeliveryStatus;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one WhatsApp message and records what actually happened.
 *
 * The payload is deliberately four scalars — a recipient id, a class name, a
 * normalised number and the rendered text — so a queued message never holds a
 * serialised model, a phone number inside a relation, or a credential.
 *
 * Retry policy lives in config(): tries and backoff are environment driven.
 * A transport that is merely unavailable does not burn the retries: it is
 * recorded as NotConfigured on the first attempt, because retrying against a
 * missing credential only produces repeated identical failures.
 */
class SendQueuedWhatsAppMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /**
     * @var list<int>
     */
    public array $backoff;

    public function __construct(
        public readonly int $recipientId,
        public readonly string $notificationClass,
        public readonly string $number,
        public readonly string $title,
        public readonly string $body,
    ) {
        $this->tries = (int) config('sekolah-kita.whatsapp.tries', 3);
        $backoff = (array) config('sekolah-kita.whatsapp.backoff', [30, 120, 600]);
        $this->backoff = $backoff === [] ? [30] : array_values(array_map('intval', $backoff));
        $this->onQueue((string) config('sekolah-kita.whatsapp.queue', 'notifications'));
        $this->afterCommit();
    }

    public function handle(WhatsAppProvider $provider, NotificationService $notifications): void
    {
        $recipient = User::query()->find($this->recipientId);
        $message = trim($this->title."\n".$this->body);

        if (! $provider->isConfigured()) {
            $notifications->recordOutcome(
                $recipient,
                $this->notificationClass,
                $this->number,
                NotificationDeliveryStatus::NotConfigured,
                $provider->unavailableReason() ?? 'Provider WhatsApp tidak dikonfigurasi.',
                $this->attempts(),
                $message,
            );

            return;
        }

        $result = $provider->send($this->number, $message);

        $notifications->recordOutcome(
            $recipient,
            $this->notificationClass,
            $this->number,
            $result->status,
            $result->error,
            $this->attempts(),
            $message,
        );

        if (! $result->isSent()) {
            Log::warning('whatsapp.delivery_failed', [
                'notification' => $this->notificationClass,
                'status' => $result->status->value,
                'attempts' => $this->attempts(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('whatsapp.job_failed', [
            'notification' => $this->notificationClass,
            'exception' => $exception?->getMessage(),
        ]);
    }

    private function attempts(): int
    {
        return $this->attempts ?? 1;
    }
}
