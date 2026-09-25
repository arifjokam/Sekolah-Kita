<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class ContactMessageEmailNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 300, 900];

    public function __construct(public readonly int $contactMessageId)
    {
        $this->afterCommit();
        $this->onQueue((string) config('sekolah-kita.notifications.queue', 'notifications'));
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pesan Anda telah diterima')
            ->greeting('Terima kasih telah menghubungi Sekolah Kita.')
            ->line('Pesan Anda sudah diterima dan akan ditinjau oleh tim sekolah.')
            ->line('Nomor referensi internal: '.$this->contactMessageId.'.');
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Contact email delivery failed.', [
            'contact_message_id' => $this->contactMessageId,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }
}
