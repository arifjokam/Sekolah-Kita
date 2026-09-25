<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdmissionStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $applicationId,
        public readonly string $status,
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
            'category' => NotificationCategory::Admission->value,
            'application_id' => $this->applicationId,
            'status' => $this->status,
        ];
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Admission administrator notification failed.', [
            'application_id' => $this->applicationId,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }
}
