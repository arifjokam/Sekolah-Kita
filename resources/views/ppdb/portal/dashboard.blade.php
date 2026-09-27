@extends('layouts.app')

@section('title', 'Dashboard pendaftar · Sekolah Kita')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard pendaftar'],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="PPDB"
        title="Pendaftaran {{ $application->applicant_name }}"
        :description="'Nomor pendaftaran '.$application->registration_number.' · Periode '.($period?->name ?? '—').'. Halaman ini hanya menampilkan pengajuan Anda sendiri.'"
    >
        <x-slot:actions>
            @if (Route::has($next_action['route']))
                <a href="{{ route($next_action['route']) }}"
                   class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                    {{ $next_action['label'] }}
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Nomor pendaftaran" :value="$application->registration_number" tone="accent" />
        <x-admin.stat-card label="Status pendaftaran" :value="$applicant_status['label']" :tone="$applicant_status['tone'] === 'emerald' ? 'muted' : 'default'">
            <x-candidate.status-chip class="mt-3" :value="$applicant_status['tone']" :label="$applicant_status['label']" />
        </x-admin.stat-card>
        <x-admin.stat-card label="Periode PPDB" :value="$period?->name ?? '—'" :hint="$period?->closes_at?->translatedFormat('ditutup d M Y')" />
        <x-admin.stat-card
            label="Notifikasi WhatsApp"
            :value="$whatsapp['configured'] ? 'Aktif' : 'Belum dikonfigurasi'"
            :hint="$whatsapp['configured'] ? 'Driver: '.$whatsapp['driver'] : 'Pesan hanya terkirim di dalam aplikasi.'"
            :tone="$whatsapp['configured'] ? 'muted' : 'warning'"
        />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="space-y-6">
            <x-candidate.completeness :sections="$sections" :percentage="$percentage" />

            <section aria-labelledby="sections-heading">
                <h2 id="sections-heading" class="mb-4 text-lg font-bold text-slate-950">Bagian pendaftaran</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-candidate.section-card
                        label="Biodata siswa"
                        route="ppdb.portal.biodata"
                        pattern="ppdb.portal.biodata"
                        :ratio="$sections['biodata']['ratio']"
                        :complete="$sections['biodata']['complete']"
                        :missing="$sections['biodata']['missing']"
                    />
                    <x-candidate.section-card
                        label="Orang tua / wali"
                        route="ppdb.portal.guardians"
                        pattern="ppdb.portal.guardians"
                        :ratio="$sections['guardians']['ratio']"
                        :complete="$sections['guardians']['complete']"
                        :missing="$sections['guardians']['missing']"
                    />
                    <x-candidate.section-card
                        label="Dokumen pendukung"
                        route="ppdb.portal.documents"
                        pattern="ppdb.portal.documents*"
                        :ratio="$sections['documents']['ratio']"
                        :complete="$sections['documents']['complete']"
                        :missing="$sections['documents']['missing']"
                    />
                </div>
            </section>

            <section aria-labelledby="feedback-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 id="feedback-heading" class="text-lg font-bold text-slate-950">Catatan dari administrator</h2>
                <ol class="mt-4 space-y-4">
                    @forelse ($feedback as $history)
                        <li class="border-l-2 border-cyan-200 pl-4">
                            <p class="text-sm font-semibold text-slate-900">{{ $history->to_status->label() }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $history->occurred_at?->translatedFormat('d M Y H:i') }}</p>
                            @if ($history->public_message)
                                <p class="mt-1 text-sm leading-6 text-slate-700">{{ $history->public_message }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-slate-600">Belum ada catatan. Administrator akan memberi kabar pada halaman status pendaftaran.</li>
                    @endforelse
                </ol>
            </section>
        </div>

        <div class="space-y-6">
            <section aria-labelledby="next-heading" class="rounded-2xl border border-cyan-200 bg-cyan-50/70 p-5 shadow-sm">
                <h2 id="next-heading" class="text-lg font-bold text-slate-950">Langkah berikutnya</h2>
                <p class="mt-2 text-sm leading-6 text-slate-700">
                    @if ($can_submit)
                        Semua bagian wajib sudah lengkap. Tinjau data sekali lagi, lalu ajukan pendaftaran untuk ditinjau administrator.
                    @elseif ($can_edit)
                        {{ $missing === [] ? 'Pengajuan sudah lengkap tetapi belum diajukan.' : 'Lengkapi bagian yang masih kurang: '.implode('; ', array_slice($missing, 0, 3)).'.' }}
                    @elseif ($application->isSubmitted())
                        Pengajuan Anda sedang ditinjau. Anda tidak dapat mengubah data pada tahap ini.
                    @else
                        Pengajuan Anda sudah sampai batas akhir pemrosesan. Buka halaman status untuk melihat hasil keputusan.
                    @endif
                </p>
                @if (Route::has($next_action['route']))
                    <a href="{{ route($next_action['route']) }}"
                       class="mt-4 inline-flex rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                        {{ $next_action['label'] }}
                    </a>
                @endif
            </section>

            <x-notification-feed :notifications="$notifications" :unread="$unreadNotificationCount" />
        </div>
    </div>
@endsection
