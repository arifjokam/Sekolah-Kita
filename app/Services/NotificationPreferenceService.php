<?php

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\UserRole;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationPreferenceService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function isEnabled(User $user, NotificationChannel $channel, NotificationCategory $category): bool
    {
        if ($user->relationLoaded('notificationPreferences')) {
            $preference = $user->notificationPreferences->firstWhere(
                fn (NotificationPreference $item): bool => $item->channel === $channel && $item->category === $category,
            );
        } else {
            $preference = NotificationPreference::query()
                ->where('user_id', $user->getKey())
                ->where('channel', $channel->value)
                ->where('category', $category->value)
                ->first();
        }

        return $preference?->enabled ?? true;
    }

    public function set(
        User $user,
        NotificationChannel $channel,
        NotificationCategory $category,
        bool $enabled,
    ): NotificationPreference {
        return DB::transaction(function () use ($user, $channel, $category, $enabled): NotificationPreference {
            $preference = NotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'channel' => $channel->value,
                    'category' => $category->value,
                ],
                ['enabled' => $enabled],
            );
            $this->audit->recordOrFail(
                $user,
                'notification_preference.updated',
                'notification_preference',
                $preference->getKey(),
                context: [
                    'channel' => $channel->value,
                    'category' => $category->value,
                    'enabled' => $enabled,
                ],
            );

            return $preference;
        }, attempts: 3);
    }

    public function sendToAdministrators(Notification $notification, NotificationCategory $category): void
    {
        User::query()
            ->where('role', UserRole::Administrator->value)
            ->where('status', 'active')
            ->whereNotNull('email_verified_at')
            ->with('notificationPreferences')
            ->chunkById(100, function ($administrators) use ($notification, $category): void {
                foreach ($administrators as $administrator) {
                    if ($this->isEnabled($administrator, NotificationChannel::InApp, $category)) {
                        NotificationFacade::send($administrator, $notification);
                    }
                }
            });
    }
}
