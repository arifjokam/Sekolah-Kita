<?php

namespace App\Notifications;

use App\Models\AdmissionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class AdmissionApplicantStatusEmailNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 300, 900];

    public function __construct(public readonly int $applicationId)
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
        $application = AdmissionApplication::query()->find($this->applicationId);
        $status = $application?->status->label() ?? 'sedang diproses';

        return (new MailMessage)
            ->subject('Pembaruan status PPDB Sekolah Kita')
            ->greeting('Pembaruan pengajuan PPDB Anda')
            ->line('Status pengajuan Anda saat ini: '.$status.'.')
            ->line('Gunakan tautan status yang telah diberikan kepada Anda untuk melihat informasi yang tersedia.');
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Admission applicant email failed.', [
            'application_id' => $this->applicationId,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }
}
