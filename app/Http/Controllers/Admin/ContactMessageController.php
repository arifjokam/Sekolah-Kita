<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContactMessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateContactMessageStatusRequest;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function index(AuditLogService $audit): View
    {
        Gate::authorize('viewAny', ContactMessage::class);
        $audit->recordOrFail(
            auth()->user(),
            'contact_message.list_viewed',
            'contact_message_collection',
        );

        $messages = ContactMessage::query()
            ->select(['id', 'name', 'subject', 'status', 'created_at', 'updated_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.contact-messages.index', compact('messages'));
    }

    public function show(ContactMessage $contactMessage, AuditLogService $audit): View
    {
        Gate::authorize('view', $contactMessage);
        $audit->recordOrFail(
            auth()->user(),
            'contact_message.viewed',
            'contact_message',
            $contactMessage->getKey(),
        );

        return view('admin.contact-messages.show', compact('contactMessage'));
    }

    public function updateStatus(
        UpdateContactMessageStatusRequest $request,
        ContactMessage $contactMessage,
        AuditLogService $audit,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $status = ContactMessageStatus::from($request->validated('status'));

        DB::transaction(function () use ($actor, $contactMessage, $status, $audit): void {
            $contactMessage->forceFill([
                'status' => $status,
                'responded_at' => $status === ContactMessageStatus::Responded ? now() : null,
            ])->save();
            $audit->recordOrFail(
                $actor,
                'contact_message.status_changed',
                'contact_message',
                $contactMessage->getKey(),
                context: ['status' => $status->value],
            );
        }, attempts: 3);

        Log::info('Contact message moderation state changed.', [
            'actor_id' => $actor->getKey(),
            'contact_message_id' => $contactMessage->getKey(),
            'status' => $status->value,
        ]);

        return back()->with('status', 'Status pesan kontak berhasil diperbarui.');
    }
}
