<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class ContactMessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $contactMessageId,
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
        $subject = ContactMessage::query()->whereKey($this->contactMessageId)->value('subject');

        return [
            'category' => NotificationCategory::Contact->value,
            'contact_message_id' => $this->contactMessageId,
            'subject' => str((string) $subject)->limit(120, '')->toString(),
        ];
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Contact notification delivery failed.', [
            'contact_message_id' => $this->contactMessageId,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }
}
