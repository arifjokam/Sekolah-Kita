<?php

namespace App\Notifications;

use App\Contracts\ContactableNotification;
use App\Enums\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The in-app notification an applicant account sees in the portal.
 *
 * Queued on the same queue the rest of the platform uses so a slow queue cannot
 * block a submission request, and so it never depends on a WhatsApp gateway.
 */
class CandidatePortalNotification extends Notification implements ContactableNotification, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly string $title,
        public readonly array $lines,
        public readonly NotificationCategory $category = NotificationCategory::Admission,
        public readonly ?string $url = null,
    ) {
        $this->afterCommit();
        $this->onQueue((string) config('sekolah-kita.notifications.queue', 'notifications'));
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category->value,
            'title' => $this->title,
            'lines' => array_slice($this->lines, 0, 6),
            'url' => $this->url,
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    public function toWhatsApp(): array
    {
        return [
            'title' => $this->title,
            'body' => implode("\n", array_slice($this->lines, 0, 8)),
        ];
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Candidate portal notification failed.', [
            'title' => $this->title,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }
}
