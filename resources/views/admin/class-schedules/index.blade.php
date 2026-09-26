@extends('layouts.app')

@section('title', 'Jadwal pelajaran · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Akademik'],
            ['label' => 'Jadwal pelajaran'],
        ];

        $statusOptions = collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()]);
        $dayOptions = collect($days)->mapWithKeys(fn ($day) => [$day->value => $day->label()]);

        $filterItems = [
            ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Mata pelajaran, guru, ruang'],
            ['name' => 'academic_year_id', 'label' => 'Tahun ajaran', 'type' => 'select', 'options' => $options['academicYears']],
            ['name' => 'term_id', 'label' => 'Semester', 'type' => 'select', 'options' => $options['terms']],
            ['name' => 'class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => $options['classes']],
            ['name' => 'teacher_id', 'label' => 'Guru', 'type' => 'select', 'options' => $options['teachers']],
            ['name' => 'subject_id', 'label' => 'Mata pelajaran', 'type' => 'select', 'options' => $options['subjects']],
            ['name' => 'day_of_week', 'label' => 'Hari', 'type' => 'select', 'options' => $dayOptions],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => $statusOptions],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Akademik"
        title="Jadwal pelajaran"
        description="Satu baris adalah satu pelajaran mingguan yang berulang. Kelas, guru, dan ruang tidak boleh dipakai bersamaan pada rentang waktu yang sama."
    >
        <x-slot:actions>
            <a href="{{ route('admin.class-schedules.create', array_filter($filters)) }}"
               class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tambah jadwal</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar :items="$filterItems" />

    @if ($schedules->isEmpty())
        <x-admin.empty-state
            label="Belum ada jadwal"
            message="Jadwal pelajaran diperlukan sebelum absensi dapat diisi. Tambahkan pelajaran mingguan untuk kelas yang sedang berjalan."
            action-label="Tambah jadwal pelajaran"
            action-route="admin.class-schedules.create"
        />
    @else
        <section aria-labelledby="grid-heading" class="mb-10">
            <h2 id="grid-heading" class="mb-4 text-lg font-bold text-slate-950">Jadwal mingguan</h2>

            {{-- Desktop: a real week grid. --}}
            <div class="hidden overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm lg:block">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <caption class="sr-only">Jadwal pelajaran mingguan</caption>
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="w-40 px-4 py-3">Hari</th>
                            @foreach ($grid as $column)
                                <th scope="col" class="px-4 py-3">{{ $column['day']->label() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php
                            $slots = collect($grid)->map(fn ($column) => $column['lessons']->count())->max() ?? 0;
                        @endphp
                        @for ($row = 0; $row < max(1, $slots); $row++)
                            <tr>
                                <th scope="row" class="px-4 py-3 align-top text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    {{ $row === 0 ? 'Jam pelajaran' : '' }}
                                </th>
                                @foreach ($grid as $column)
                                    <td class="px-4 py-3 align-top">
                                        @php $lesson = $column['lessons']->get($row); @endphp
                                        @if ($lesson)
                                            <a href="{{ route('admin.class-schedules.show', $lesson) }}"
                                               class="block rounded-xl border border-cyan-200 bg-cyan-50/70 p-3 transition hover:border-cyan-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                                                <span class="block text-xs font-bold text-cyan-900">{{ $lesson->timeRange() }}</span>
                                                <span class="mt-1 block text-sm font-semibold text-slate-950">{{ $lesson->subject?->name }}</span>
                                                <span class="mt-1 block text-xs text-slate-600">{{ $lesson->class?->getFullName() }} · {{ $lesson->teacher?->getDisplayName() }}</span>
                                                @if ($lesson->room)
                                                    <span class="mt-1 block text-xs text-slate-500">Ruang {{ $lesson->room }}</span>
                                                @endif
                                            </a>
                                        @else
                                            <span class="text-xs text-slate-300">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            {{-- Mobile: the same data as stacked day cards, because a seven-column grid is unreadable on a phone. --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:hidden">
                @foreach ($grid as $column)
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-950">{{ $column['day']->label() }}</h3>
                        <ul class="mt-3 space-y-2">
                            @forelse ($column['lessons'] as $lesson)
                                <li>
                                    <a href="{{ route('admin.class-schedules.show', $lesson) }}" class="block rounded-xl border border-slate-200 p-3 transition hover:border-cyan-400">
                                        <span class="block text-xs font-bold text-cyan-800">{{ $lesson->timeRange() }}</span>
                                        <span class="mt-1 block text-sm font-semibold text-slate-950">{{ $lesson->subject?->name }}</span>
                                        <span class="mt-1 block text-xs text-slate-600">{{ $lesson->class?->getFullName() }} · {{ $lesson->teacher?->getDisplayName() }}</span>
                                        @if ($lesson->room)
                                            <span class="mt-1 block text-xs text-slate-500">Ruang {{ $lesson->room }}</span>
                                        @endif
                                    </a>
                                </li>
                            @empty
                                <li class="text-xs text-slate-500">Tidak ada pelajaran.</li>
                            @endforelse
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="list-heading">
            <h2 id="list-heading" class="mb-4 text-lg font-bold text-slate-950">Daftar jadwal</h2>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <caption class="sr-only">Daftar jadwal pelajaran</caption>
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-5 py-3">Hari &amp; jam</th>
                                <th scope="col" class="px-5 py-3">Kelas</th>
                                <th scope="col" class="px-5 py-3">Mata pelajaran</th>
                                <th scope="col" class="px-5 py-3">Guru</th>
                                <th scope="col" class="px-5 py-3">Ruang</th>
                                <th scope="col" class="px-5 py-3">Status</th>
                                <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($schedules as $schedule)
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <p class="font-semibold text-slate-950">{{ $schedule->day_of_week->label() }}</p>
                                        <p class="text-xs text-slate-500">{{ $schedule->timeRange() }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-slate-700">{{ $schedule->class?->getFullName() }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $schedule->subject?->name }}</td>
                                    <td class="px-5 py-4 text-slate-700">{{ $schedule->teacher?->getDisplayName() }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $schedule->room ?? '—' }}</td>
                                    <td class="px-5 py-4"><x-admin.status-badge :status="$schedule->status->value" /></td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <a href="{{ route('admin.class-schedules.show', $schedule) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Detail</a>
                                            <a href="{{ route('admin.class-schedules.edit', $schedule) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <x-pagination :paginator="$schedules" />
        </section>
    @endif
@endsection
