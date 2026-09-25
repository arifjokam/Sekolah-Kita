@extends('layouts.app')

@section('title', 'Notifikasi · Sekolah Kita')

@section('content')
    <div class="mx-auto max-w-5xl space-y-8">
        <header>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Pusat informasi</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Notifikasi</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Pesan sistem dan pembaruan akun Anda ditampilkan di sini.</p>
        </header>

        <section aria-labelledby="notification-list-heading" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 id="notification-list-heading" class="font-semibold text-slate-950">Pesan terbaru</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($notifications as $notification)
                    <article class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-medium text-slate-950">{{ data_get($notification->data, 'subject', 'Pembaruan sistem') }}</p>
                            <p class="mt-1 text-xs uppercase tracking-wide text-slate-500">{{ data_get($notification->data, 'category', 'system') }}</p>
                            <time class="mt-2 block text-xs text-slate-500" datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->translatedFormat('d M Y H:i') }}</time>
                        </div>
                        @if ($notification->read_at === null)
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                @csrf
                                <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tandai dibaca</button>
                            </form>
                        @else
                            <span class="text-xs font-medium text-emerald-700">Sudah dibaca</span>
                        @endif
                    </article>
                @empty
                    <p class="px-5 py-8 text-sm text-slate-600">Belum ada notifikasi.</p>
                @endforelse
            </div>
            @if ($notifications->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">{{ $notifications->links() }}</div>
            @endif
        </section>

        <section aria-labelledby="preference-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 id="preference-heading" class="font-semibold text-slate-950">Preferensi notifikasi</h2>
            <p class="mt-1 text-sm text-slate-600">Preferensi tidak pernah menonaktifkan pemberitahuan keamanan penting.</p>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @foreach ($preferences as $preference)
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-slate-900">{{ $preference->category->label() }} · {{ $preference->channel->label() }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $preference->enabled ? 'Aktif' : 'Nonaktif' }}</p>
                        </div>
                        <form method="POST" action="{{ route('notifications.preferences.update') }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="channel" value="{{ $preference->channel->value }}">
                            <input type="hidden" name="category" value="{{ $preference->category->value }}">
                            <input type="hidden" name="enabled" value="{{ $preference->enabled ? '0' : '1' }}">
                            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">{{ $preference->enabled ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        </form>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('notifications.preferences.update') }}" class="mt-5 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                @csrf
                @method('PUT')
                <label class="grid gap-1 text-sm font-medium text-slate-700">Saluran
                    <select name="channel" class="rounded-lg border-slate-300 text-sm">
                        @foreach ($channels as $channel)
                            <option value="{{ $channel->value }}">{{ $channel->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-1 text-sm font-medium text-slate-700">Kategori
                    <select name="category" class="rounded-lg border-slate-300 text-sm">
                        @foreach ($categories as $category)
                            <option value="{{ $category->value }}">{{ $category->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-2 pb-2 text-sm font-medium text-slate-700"><input type="checkbox" name="enabled" value="1" checked class="rounded border-slate-300 text-cyan-700"> Aktif</label>
                <button type="submit" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 focus-visible:ring-offset-2">Simpan</button>
            </form>
        </section>
    </div>
@endsection
