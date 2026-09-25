<?php

namespace App\Http\Controllers;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Http\Requests\UpdateNotificationPreferenceRequest;
use App\Models\NotificationPreference;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationPreferenceService $preferences): View
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate(20);
        $preferenceRows = NotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->orderBy('category')
            ->orderBy('channel')
            ->get();

        return view('notifications.index', [
            'notifications' => $notifications,
            'preferences' => $preferenceRows,
            'channels' => NotificationChannel::cases(),
            'categories' => NotificationCategory::cases(),
            'preferencesService' => $preferences,
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $notificationModel = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $notificationModel->markAsRead();

        return back()->with('status', 'Notifikasi ditandai sebagai sudah dibaca.');
    }

    public function updatePreference(
        UpdateNotificationPreferenceRequest $request,
        NotificationPreferenceService $preferences,
    ): RedirectResponse {
        $validated = $request->validated();
        $preferences->set(
            $request->user(),
            NotificationChannel::from($validated['channel']),
            NotificationCategory::from($validated['category']),
            $request->boolean('enabled'),
        );

        return back()->with('status', 'Preferensi notifikasi diperbarui.');
    }
}
