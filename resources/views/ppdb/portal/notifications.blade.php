@extends('layouts.app')

@section('title', 'Notifikasi · PPDB')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard pendaftar', 'route' => 'ppdb.portal.dashboard'],
            ['label' => 'Notifikasi'],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="PPDB"
        title="Notifikasi"
        description="Semua pemberitahuan tentang pengajuan Anda, termasuk status pengiriman WhatsApp bila kanal tersebut dikonfigurasi."
    >
        <x-slot:actions>
            <a href="{{ route('ppdb.portal.status') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Status pendaftaran</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-admin.stat-card
            label="Status WhatsApp"
            :value="$whatsapp['configured'] ? 'Dikonfigurasi' : 'Belum dikonfigurasi'"
            :hint="$whatsapp['configured'] ? 'Driver '.$whatsapp['driver'] : ($whatsapp['reason'] ?? '')"
            :tone="$whatsapp['configured'] ? 'muted' : 'warning'"
        />
        <x-admin.stat-card label="Nomor tujuan" :value="$lastRecipient ?? '—'" hint="Nomor terakhir yang dipakai." />
        <x-admin.stat-card label="Belum dibaca" :value="$user->unreadNotifications()->count()" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <section aria-labelledby="inbox-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 id="inbox-heading" class="text-lg font-bold text-slate-950">Kotak masuk</h2>

            <ul class="mt-4 divide-y divide-slate-100">
                @forelse ($notifications as $notification)
                    @php
                        $payload = $notification->data;
                        $title = is_array($payload) ? ($payload['title'] ?? 'Pembaruan PPDB') : 'Pembaruan PPDB';
                        $lines = is_array($payload) ? ($payload['lines'] ?? []) : [];
                    @endphp
                    <li class="py-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <p class="font-semibold text-slate-950">{{ $title }}</p>
                            <span class="text-xs font-semibold {{ $notification->read_at === null ? 'text-cyan-700' : 'text-slate-400' }}">
                                {{ $notification->read_at === null ? 'Belum dibaca' : 'Dibaca' }}
                            </span>
                        </div>
                        @foreach ($lines as $line)
                            <p class="mt-1 text-sm leading-6 text-slate-700">{{ $line }}</p>
                        @endforeach
                        <p class="mt-2 text-xs text-slate-500">{{ $notification->created_at?->translatedFormat('d M Y H:i') }}</p>
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-slate-600">Belum ada notifikasi untuk akun Anda.</li>
                @endforelse
            </ul>

            <x-pagination :paginator="$notifications" />
        </section>

        <section aria-labelledby="delivery-heading" class="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 id="delivery-heading" class="text-lg font-bold text-slate-950">Riwayat pengiriman WhatsApp</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                Setiap upaya pengiriman dicatat apa adanya. Ketika sekolah belum mengonfigurasi provider, statusnya
                <em>belum dikonfigurasi</em> — bukan terkirim.
            </p>

            <ul class="mt-4 space-y-3">
                @forelse ($deliveries as $delivery)
                    <li class="rounded-xl border border-slate-200 p-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-semibold text-slate-900">{{ $delivery->status->label() }}</span>
                            <span class="text-xs text-slate-500">{{ $delivery->created_at?->translatedFormat('d M Y H:i') }}</span>
                        </div>
                        <p class="mt-1 text-xs text-slate-600">Ke {{ $delivery->recipient }} · percobaan {{ $delivery->attempts }} · {{ $delivery->provider }}</p>
                        @if ($delivery->last_error)
                            <p class="mt-1 text-xs leading-5 text-rose-700">{{ $delivery->last_error }}</p>
                        @endif
                    </li>
                @empty
                    <li class="text-sm text-slate-600">Belum ada percobaan pengiriman WhatsApp.</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
