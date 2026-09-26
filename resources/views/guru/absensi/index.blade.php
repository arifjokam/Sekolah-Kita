@extends('layouts.app')

@section('title', 'Absensi · Guru')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.guru'],
            ['label' => 'Absensi'],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Guru"
        title="Absensi"
        :description="'Isi kehadiran untuk pelajaran '.$date->translatedFormat('l, d M Y').'. Hanya kelas yang Anda ampu yang dapat diisi.'"
    >
        <x-slot:actions>
            <a href="{{ route('guru.absensi.report') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Laporan bulanan</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3">
        <label class="grid gap-1 text-sm font-medium text-slate-700">
            <span>Tanggal</span>
            <input type="date" name="date" value="{{ $date->toDateString() }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
        </label>
        <label class="grid gap-1 text-sm font-medium text-slate-700">
            <span>Semester</span>
            <select name="term_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                <option value="">Semua semester</option>
                @foreach ($terms as $term)
                    <option value="{{ $term->id }}" @selected($selectedTermId === $term->id)>
                        {{ $term->semesterLabel() }} · {{ $term->academicYear?->label }}
                    </option>
                @endforeach
            </select>
        </label>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tampilkan</button>
            <a href="{{ route('guru.absensi.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Reset</a>
        </div>
    </form>

    <section aria-labelledby="today-heading" class="mb-8">
        <h2 id="today-heading" class="mb-4 text-lg font-bold text-slate-950">Pelajaran hari ini</h2>

        @if ($lessons->isEmpty())
            <x-admin.empty-state
                label="Tidak ada pelajaran"
                message="Tidak ada jadwal pelajaran yang dapat Anda ampu pada {{ $date->translatedFormat('l, d M Y') }}. Periksa kembali tanggal di atas."
            />
        @else
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($lessons as $lesson)
                    @php
                        // Prefetched by the controller as a schedule_id => session
                        // map. Looking it up here would be one query per lesson.
                        $session = $sessionBySchedule->get($lesson->getKey());
                    @endphp
                    <li class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-lg font-bold text-slate-950">{{ $lesson->timeRange() }}</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $lesson->subject?->name }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $lesson->class?->getFullName() }}</p>
                        @if ($lesson->room)
                            <p class="mt-1 text-xs text-slate-500">Ruang {{ $lesson->room }}</p>
                        @endif

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @if ($session)
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $session->isSubmitted() ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $session->status->label() }}
                                </span>
                                <a href="{{ route('guru.absensi.show', $session) }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                                    {{ $session->isSubmitted() ? 'Lihat / koreksi' : 'Lanjut isi' }}
                                </a>
                            @else
                                <form method="POST" action="{{ route('guru.absensi.open', $lesson) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Mulai absensi</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="sessions-heading">
        <h2 id="sessions-heading" class="mb-4 text-lg font-bold text-slate-950">Sesi terbaru</h2>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <caption class="sr-only">Sesi absensi terbaru</caption>
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-3">Tanggal</th>
                            <th scope="col" class="px-5 py-3">Kelas</th>
                            <th scope="col" class="px-5 py-3">Mata pelajaran</th>
                            <th scope="col" class="px-5 py-3">Waktu</th>
                            <th scope="col" class="px-5 py-3">Status</th>
                            <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($sessions as $session)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-700">{{ $session->session_date->translatedFormat('d M Y') }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $session->class?->getFullName() }}</td>
                                <td class="px-5 py-4 text-slate-700">{{ $session->subject?->name ?? '—' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">
                                    {{ $session->starts_at ? substr((string) $session->starts_at, 0, 5) : '—' }}
                                    —
                                    {{ $session->ends_at ? substr((string) $session->ends_at, 0, 5) : '—' }}
                                </td>
                                <td class="px-5 py-4"><x-admin.status-badge :status="$session->status->value" /></td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('guru.absensi.show', $session) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Buka</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-8 text-center text-slate-600">Belum ada sesi absensi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
