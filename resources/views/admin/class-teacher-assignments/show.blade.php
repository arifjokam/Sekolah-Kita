@extends('layouts.app')

@section('title', 'Wali kelas · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Wali kelas', 'route' => 'admin.class-teacher-assignments.index'],
            ['label' => $assignment->class?->getFullName() ?? 'Penugasan' ],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Akademik"
        :title="($assignment->teacher?->getDisplayName() ?? 'Guru dihapus').' — wali '.($assignment->class?->getFullName() ?? 'kelas dihapus')"
        :description="($assignment->class?->academicYear?->label ?? 'Tanpa tahun ajaran')"
    >
        <x-slot:actions>
            @if ($assignment->teacher)
                <a href="{{ route('admin.teachers.show', $assignment->teacher) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Data guru</a>
            @endif
            @if ($assignment->class)
                <a href="{{ route('admin.classes.show', $assignment->class) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Kelas</a>
            @endif
            <a href="{{ route('admin.class-teacher-assignments.edit', $assignment) }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Edit</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Status" :value="$assignment->status->label()" :tone="$assignment->isActive() ? 'accent' : 'muted'" />
        <x-admin.stat-card label="Guru" :value="$assignment->teacher?->getDisplayName() ?? '—'" />
        <x-admin.stat-card label="Kelas" :value="$assignment->class?->name ?? '—'" />
        <x-admin.stat-card label="Tahun ajaran" :value="$assignment->class?->academicYear?->code ?? '—'" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="detail-heading">
            <h2 id="detail-heading" class="text-lg font-bold text-slate-950">Detail penugasan</h2>

            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Guru</dt><dd class="mt-1 text-slate-900">{{ $assignment->teacher?->legal_name ?? '—' }}@if ($assignment->teacher?->employee_number) <span class="font-mono text-xs text-slate-500">NIP {{ $assignment->teacher->employee_number }}</span>@endif</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kelas</dt><dd class="mt-1 text-slate-900">{{ $assignment->class?->getFullName() ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tahun ajaran</dt><dd class="mt-1 text-slate-900">{{ $assignment->class?->academicYear?->label ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mulai</dt><dd class="mt-1 text-slate-900">{{ $assignment->starts_on?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Selesai</dt><dd class="mt-1 text-slate-900">{{ $assignment->ends_on?->format('d M Y') ?? '—' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="status-heading">
            <h2 id="status-heading" class="text-lg font-bold text-slate-950">Status</h2>

            <form method="POST" action="{{ route('admin.class-teacher-assignments.status.update', $assignment) }}" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')

                <x-form.select
                    name="status"
                    label="Status penugasan"
                    :value="$assignment->status->value"
                    :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])"
                    required
                />

                <button class="rounded-lg bg-cyan-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Perbarui status</button>
            </form>

            @if ($assignment->internal_reason)
                <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">
                    <p class="font-semibold text-slate-800">Catatan internal</p>
                    <p class="mt-1">{{ $assignment->internal_reason }}</p>
                </div>
            @endif
        </section>
    </div>
@endsection
