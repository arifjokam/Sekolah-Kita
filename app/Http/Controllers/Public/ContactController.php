<?php

namespace App\Http\Controllers\Public;

use App\Enums\ContactMessageStatus;
use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use App\Notifications\ContactMessageReceivedNotification;
use App\Services\AuditLogService;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('public.contact');
    }

    public function store(
        StoreContactMessageRequest $request,
        AuditLogService $audit,
        NotificationPreferenceService $notificationPreferences,
    ): RedirectResponse {
        $message = DB::transaction(function () use ($request, $audit): ContactMessage {
            $message = ContactMessage::query()->create([
                ...$request->safe()->only(['name', 'email', 'phone', 'subject', 'message']),
                'status' => ContactMessageStatus::New,
                'responded_at' => null,
            ]);

            Log::info('Public contact message received.', [
                'contact_message_id' => $message->getKey(),
                'status' => ContactMessageStatus::New->value,
            ]);

            $audit->recordOrFail(
                actor: null,
                action: 'contact.received',
                targetType: 'contact_message',
                targetId: $message->getKey(),
                context: ['status' => ContactMessageStatus::New->value],
                request: $request,
            );

            return $message;
        }, attempts: 3);
        $notificationPreferences->sendToAdministrators(
            new ContactMessageReceivedNotification($message->getKey()),
            NotificationCategory::Contact,
        );

        return redirect()
            ->route('contact.create')
            ->with('contact_success', 'Pesan Anda telah diterima. Kami akan segera menghubungi Anda.');
    }
}
