@extends('layouts.app')

@section('title', $subject->name.' · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Mata pelajaran', 'route' => 'admin.subjects.index'],
            ['label' => $subject->name],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        :title="$subject->name"
        :description="$subject->code.($subject->description ? ' · '.$subject->description : '')"
    >
        <x-slot:actions>
            <a href="{{ route('admin.teaching-assignments.create', ['subject_id' => $subject->id]) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tugaskan</a>
            <a href="{{ route('admin.subjects.edit', $subject) }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Edit</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Status" :value="$subject->isActive() ? 'Aktif' : 'Tidak aktif'" :tone="$subject->isActive() ? 'accent' : 'muted'" />
        <x-admin.stat-card label="Kode" :value="$subject->code" />
        <x-admin.stat-card label="Assignment aktif" :value="$subject->teaching_assignments_count" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="detail-heading">
            <h2 id="detail-heading" class="text-lg font-bold text-slate-950">Detail mata pelajaran</h2>

            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kode</dt><dd class="mt-1 font-mono text-slate-900">{{ $subject->code }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nama</dt><dd class="mt-1 text-slate-900">{{ $subject->name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Deskripsi</dt><dd class="mt-1 text-slate-900">{{ $subject->description ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt><dd class="mt-1"><x-admin.status-badge :status="$subject->status" /></dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="usage-heading">
            <h2 id="usage-heading" class="text-lg font-bold text-slate-950">Assignment aktif</h2>
            <p class="mt-1 text-sm text-slate-600"> Penugasan mengajar yang memakai mata pelajaran ini.</p>

            <ul class="mt-4 space-y-2">
                @forelse ($subject->teachingAssignments as $assignment)
                    <li class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3 text-sm">
                        <div>
                            <a href="{{ route('admin.teaching-assignments.show', $assignment) }}" class="font-semibold text-cyan-700 hover:text-cyan-900">{{ $assignment->teacher?->getDisplayName() ?? 'Guru dihapus' }}</a>
                            <p class="mt-1 text-xs text-slate-500">{{ $assignment->class?->getFullName() ?? 'Kelas dihapus' }} · {{ $assignment->academicYear?->code ?? '—' }}</p>
                        </div>
                        <x-admin.status-badge :status="$assignment->status->value" />
                    </li>
                @empty
                    <li class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-600">
                        Belum ada assignment aktif untuk mata pelajaran ini.
                    </li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
