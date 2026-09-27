@extends('layouts.app')

@section('title', 'Jadwal pelajaran · Guru')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.guru'],
            ['label' => 'Jadwal pelajaran'],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Guru"
        title="Jadwal pelajaran saya"
        :description="'Hanya jadwal untuk tahun ajaran '.($activeAcademicYear?->label ?? 'belum aktif').' yang tampil di sini.'"
    />

    <form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3">
        <label class="grid gap-1 text-sm font-medium text-slate-700">
            <span>Semester</span>
            <select name="term_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                <option value="">Semua semester</option>
                @foreach ($terms as $term)
                    <option value="{{ $term->id }}" @selected((int) request('term_id') === $term->id)>
                        {{ $term->semesterLabel() }} ({{ $term->status->label() }})
                    </option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm font-medium text-slate-700">
            <span>Tanggal acuan</span>
            <input type="date" name="date" value="{{ $date->toDateString() }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
        </label>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tampilkan</button>
            <a href="{{ route('guru.jadwal.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Reset</a>
        </div>
    </form>

    @if ($lessons->isEmpty())
        <x-admin.empty-state
            label="Belum ada jadwal"
            message="Administrator belum membuat jadwal pelajaran untuk Anda pada tahun ajaran ini. Jadwal akan tampil otomatis setelah dibuat."
        >
            <a href="{{ route('guru.classes.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Lihat kelas saya</a>
        </x-admin.empty-state>
    @else
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.stat-card label="Hari ini" :value="$date->translatedFormat('l, d M Y')" tone="accent" />
            <x-admin.stat-card label="Pelajaran hari ini" :value="$todayLessons->count()" :hint="$todayLessons->isNotEmpty() ? 'Pertama pukul '.$todayLessons->first()->timeRange() : 'Tidak ada pelajaran'" />
            <x-admin.stat-card
                label="Pelajaran berikutnya"
                :value="$nextLesson ? $nextLesson['lesson']->timeRange() : '—'"
                :hint="$nextLesson ? $nextLesson['lesson']->subject?->name.' · '.$nextLesson['lesson']->class?->getFullName().' dalam '.$nextLesson['starts_in'].' menit' : 'Tidak ada pelajaran berikutnya'"
            />
        </div>

        <section aria-labelledby="today-heading" class="mt-8">
            <h2 id="today-heading" class="mb-4 text-lg font-bold text-slate-950">Pelajaran hari ini</h2>

            @if ($todayLessons->isEmpty())
                <x-admin.empty-state label="Tidak ada pelajaran hari ini" message="Anda tidak memiliki pelajaran pada hari {{ $date->translatedFormat('l') }}." />
            @else
                <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($todayLessons as $lesson)
                        <li class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-lg font-bold text-slate-950">{{ $lesson->timeRange() }}</p>
                            <p class="mt-1 font-semibold text-slate-900">{{ $lesson->subject?->name }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $lesson->class?->getFullName() }}</p>
                            @if ($lesson->room)
                                <p class="mt-1 text-xs text-slate-500">Ruang {{ $lesson->room }}</p>
                            @endif
                            <a href="{{ route('guru.absensi.index', ['date' => $date->toDateString()]) }}"
                               class="mt-4 inline-flex rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Absensi</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section aria-labelledby="week-heading" class="mt-10">
            <h2 id="week-heading" class="mb-4 text-lg font-bold text-slate-950">Jadwal mingguan</h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($grid as $column)
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-950">{{ $column['day']->label() }}</h3>
                        <ul class="mt-3 space-y-2">
                            @forelse ($column['lessons'] as $lesson)
                                <li class="rounded-xl border border-slate-200 p-3">
                                    <p class="text-xs font-bold text-cyan-800">{{ $lesson->timeRange() }}</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-950">{{ $lesson->subject?->name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-600">{{ $lesson->class?->getFullName() }}</p>
                                    @if ($lesson->room)
                                        <p class="mt-0.5 text-xs text-slate-500">Ruang {{ $lesson->room }}</p>
                                    @endif
                                </li>
                            @empty
                                <li class="text-xs text-slate-500">Tidak ada pelajaran.</li>
                            @endforelse
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>

        @if ($homeroomClasses->isNotEmpty())
            <section aria-labelledby="homeroom-heading" class="mt-10">
                <h2 id="homeroom-heading" class="mb-4 text-lg font-bold text-slate-950">Kelas yang saya ampu</h2>
                <ul class="flex flex-wrap gap-2">
                    @foreach ($homeroomClasses as $assignment)
                        <li class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 shadow-sm">
                            {{ $assignment->class?->getFullName() }}
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
@endsection
