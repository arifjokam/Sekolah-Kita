@extends('layouts.app')

@section('title', $term->semesterLabel().' · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Semester', 'route' => 'admin.terms.index'],
            ['label' => $term->semesterLabel()],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        :title="$term->sequence.'. '.$term->label"
        :description="($term->academicYear?->label ?? 'Tanpa tahun ajaran').' · '.$term->starts_on->format('d M Y').' — '.$term->ends_on->format('d M Y')"
    >
        <x-slot:actions>
            <a href="{{ route('admin.teaching-assignments.index', ['term_id' => $term->id]) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Assignment</a>
            <a href="{{ route('admin.terms.edit', $term) }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Edit</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Status" :value="$term->status->label()" :tone="$term->isActive() ? 'accent' : 'muted'" />
        <x-admin.stat-card label="Nomor semester" :value="$term->sequence" />
        <x-admin.stat-card label="Assignment" :value="$term->teaching_assignments_count" />
        <x-admin.stat-card label="Tahun ajaran" :value="$term->academicYear?->code ?? '—'" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="detail-heading">
            <h2 id="detail-heading" class="text-lg font-bold text-slate-950">Detail semester</h2>

            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tahun ajaran</dt><dd class="mt-1 text-slate-900">{{ $term->academicYear?->label ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mulai</dt><dd class="mt-1 text-slate-900">{{ $term->starts_on->format('d M Y') }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Selesai</dt><dd class="mt-1 text-slate-900">{{ $term->ends_on->format('d M Y') }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt><dd class="mt-1"><x-admin.status-badge :status="$term->status->value" /></dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="status-heading">
            <h2 id="status-heading" class="text-lg font-bold text-slate-950">Status</h2>

            <form method="POST" action="{{ route('admin.terms.status.update', $term) }}" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')

                <x-form.select
                    name="status"
                    label="Status semester"
                    :value="$term->status->value"
                    :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])"
                    required
                />

                <button class="rounded-lg bg-cyan-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Perbarui status</button>
            </form>
        </section>
    </div>
@endsection
