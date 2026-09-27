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
        title="Dashboard pendaftar"
        :description="'Selamat datang, '.auth()->user()->name.'. Halaman ini hanya menampilkan pengajuan Anda sendiri; Anda tidak dapat membuka pengajuan pendaftar lain.'"
    />

    @unless ($hasApplication)
        <x-admin.empty-state
            label="Belum ada pengajuan"
            message="Tidak ada periode PPDB yang sedang dibuka untuk akun ini. Hubungi bagian tata usaha sekolah bila pendaftaran sudah Anda lakukan di tempat lain."
        >
            <a href="{{ route('ppdb.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Lihat informasi PPDB</a>
        </x-admin.empty-state>
    @endunless

    @if ($hasApplication)
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-admin.stat-card label="Nama pemohon" :value="$application->applicant_name" tone="accent" />
            <x-admin.stat-card label="Nomor pendaftaran" :value="$application->registration_number" />
            <x-admin.stat-card label="Periode" :value="$period?->name ?? '—'" />
            <x-admin.stat-card label="Status" :value="$applicant_status['label']" :tone="$applicant_status['tone'] === 'emerald' ? 'muted' : 'default'" />
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-[1.4fr_1fr]">
            <div class="space-y-6">
                <x-candidate.completeness :sections="$sections" :percentage="$percentage" />

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
            </div>

            <div class="space-y-6">
                <section class="rounded-2xl border border-cyan-200 bg-cyan-50/70 p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-950">Langkah berikutnya</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-700">{{ $next_action['label'] }}.</p>
                    @if (Route::has($next_action['route']))
                        <a href="{{ route($next_action['route']) }}" class="mt-4 inline-flex rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">{{ $next_action['label'] }}</a>
                    @endif
                </section>

                <x-notification-feed :notifications="$recentNotifications" :unread="$unreadNotificationCount" />
            </div>
        </div>
    @endif
@endsection
