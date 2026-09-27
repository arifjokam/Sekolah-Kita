@extends('layouts.app')

@section('title', 'Detail jadwal · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Jadwal pelajaran', 'route' => 'admin.class-schedules.index'],
            ['label' => $schedule->class?->getFullName().' · '.$schedule->day_of_week->shortLabel()],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Akademik"
        :title="$schedule->class?->getFullName().' · '.$schedule->day_of_week->label()"
        :description="$schedule->timeRange().' · '.$schedule->subject?->name.' · '.$schedule->teacher?->getDisplayName()"
    >
        <x-slot:actions>
            @if ($schedule->isActive())
                <form method="POST" action="{{ route('admin.class-schedules.deactivate', $schedule) }}">
                    @csrf
                    <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Nonaktifkan</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.class-schedules.reactivate', $schedule) }}">
                    @csrf
                    <button class="rounded-lg border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-50">Aktifkan kembali</button>
                </form>
            @endif
            <a href="{{ route('admin.class-schedules.edit', $schedule) }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Edit</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Hari" :value="$schedule->day_of_week->label()" />
        <x-admin.stat-card label="Waktu" :value="$schedule->timeRange()" :hint="$schedule->durationMinutes().' menit'" />
        <x-admin.stat-card label="Ruang" :value="$schedule->room ?? '—'" />
        <x-admin.stat-card label="Status" :value="$schedule->status->label()" :tone="$schedule->isActive() ? 'accent' : 'muted'" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="scope-heading">
            <h2 id="scope-heading" class="text-lg font-bold text-slate-950">Lingkup akademik</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tahun ajaran</dt><dd class="mt-1 text-slate-900">{{ $schedule->academicYear?->label ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Semester</dt><dd class="mt-1 text-slate-900">{{ $schedule->term?->semesterLabel() ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kelas</dt><dd class="mt-1 text-slate-900">{{ $schedule->class?->getFullName() ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mata pelajaran</dt><dd class="mt-1 text-slate-900">{{ $schedule->subject?->code }} · {{ $schedule->subject?->name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Guru</dt><dd class="mt-1 text-slate-900">{{ $schedule->teacher?->getDisplayName() ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="sessions-heading">
            <h2 id="sessions-heading" class="text-lg font-bold text-slate-950">Sesi absensi</h2>
            <p class="mt-2 text-sm text-slate-600">Satu sesi dibuat untuk setiap tanggal pelajaran ini diisi absensinya.</p>

            <ul class="mt-4 space-y-2">
                @forelse ($sessions as $session)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 p-3 text-sm">
                        <span>
                            <span class="font-semibold text-slate-900">{{ $session->session_date->translatedFormat('d M Y') }}</span>
                            <span class="text-xs text-slate-500">· {{ $session->status->label() }}</span>
                        </span>
                        <span class="text-xs text-slate-500">{{ $session->markedBy?->name ?? 'Belum diisi' }}</span>
                    </li>
                @empty
                    <li class="text-sm text-slate-600">Belum ada sesi absensi untuk jadwal ini.</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
