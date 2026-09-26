@props([
    'notifications',
    'unread' => 0,
    'title' => 'Notifikasi terbaru',
])

<section aria-labelledby="notification-feed-heading" class="mb-8">
    <div class="mb-4 flex items-center justify-between gap-4">
        <h2 id="notification-feed-heading" class="text-lg font-bold text-slate-950">{{ $title }}</h2>
        <a href="{{ route('notifications.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">
            Semua notifikasi{{ $unread > 0 ? ' ('.$unread.' belum dibaca)' : '' }}
        </a>
    </div>

    <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        @forelse ($notifications as $notification)
            @php
                $payload = $notification->data;
                $label = is_array($payload) ? ($payload['title'] ?? $payload['message'] ?? 'Pembaruan aplikasi') : 'Pembaruan aplikasi';
            @endphp
            <li class="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-950">{{ $label }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $notification->created_at?->diffForHumans() }}</p>
                </div>
                <span class="shrink-0 text-xs font-semibold {{ $notification->read_at === null ? 'text-cyan-700' : 'text-slate-400' }}">
                    {{ $notification->read_at === null ? 'Belum dibaca' : 'Dibaca' }}
                </span>
            </li>
        @empty
            <li class="px-5 py-8 text-center text-sm text-slate-600">
                Belum ada notifikasi untuk akun Anda.
            </li>
        @endforelse
    </ul>
</section>
