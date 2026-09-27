@extends('layouts.app')

@section('title', $academicYear->label.' · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Tahun ajaran', 'route' => 'admin.academic-years.index'],
            ['label' => $academicYear->label],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        :title="$academicYear->label"
        :description="$academicYear->starts_on->format('d M Y').' — '.$academicYear->ends_on->format('d M Y').($academicYear->description ? ' · '.$academicYear->description : '')"
    >
        <x-slot:actions>
            <a href="{{ route('admin.classes.index', ['academic_year_id' => $academicYear->id]) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Kelas</a>
            <a href="{{ route('admin.terms.index', ['academic_year_id' => $academicYear->id]) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Term</a>
            <a href="{{ route('admin.academic-years.edit', $academicYear) }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Edit</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Status" :value="$academicYear->status->label()" :tone="$academicYear->isActive() ? 'accent' : 'muted'" />
        <x-admin.stat-card label="Kelas" :value="$academicYear->classes_count" />
        <x-admin.stat-card label="Term" :value="$academicYear->terms_count" />
        <x-admin.stat-card label="Enrollment" :value="$academicYear->enrollments_count" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="terms-heading">
            <div class="flex items-center justify-between gap-4">
                <h2 id="terms-heading" class="text-lg font-bold text-slate-950">Term</h2>
                <form method="POST" action="{{ route('admin.academic-years.status.update', $academicYear) }}" class="flex items-center gap-2">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $academicYear->status->value === \App\Enums\AcademicYearStatus::Active->value ? \App\Enums\AcademicYearStatus::Closed->value : \App\Enums\AcademicYearStatus::Active->value }}">
                    <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                        {{ $academicYear->isActive() ? 'Tutup tahun ajaran' : 'Aktifkan tahun ajaran' }}
                    </button>
                </form>
            </div>

            <ul class="mt-4 space-y-2">
                @forelse ($academicYear->terms as $term)
                    <li class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3 text-sm">
                        <div>
                            <p class="font-semibold text-slate-950">{{ $term->sequence }}. {{ $term->label }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $term->starts_on->format('d M Y') }} — {{ $term->ends_on->format('d M Y') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-admin.status-badge :status="$term->status->value" />
                            <a href="{{ route('admin.terms.edit', $term) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Edit</a>
                        </div>
                    </li>
                @empty
                    <li class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-600">
                        Belum ada term. <a href="{{ route('admin.terms.create', ['academic_year_id' => $academicYear->id]) }}" class="font-semibold text-cyan-700 hover:text-cyan-900">Tambahkan term</a>.
                    </li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="classes-heading">
            <div class="flex items-center justify-between gap-4">
                <h2 id="classes-heading" class="text-lg font-bold text-slate-950">Kelas</h2>
                <a href="{{ route('admin.classes.create', ['academic_year_id' => $academicYear->id]) }}" class="rounded-lg bg-cyan-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-cyan-800">Tambah kelas</a>
            </div>

            <ul class="mt-4 space-y-2">
                @forelse ($academicYear->classes as $class)
                    <li class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3 text-sm">
                        <div>
                            <a href="{{ route('admin.classes.show', $class) }}" class="font-semibold text-cyan-700 hover:text-cyan-900">{{ $class->getFullName() }}</a>
                            <p class="mt-1 text-xs text-slate-500">Kapasitas {{ $class->capacity ?? '∞' }}</p>
                        </div>
                        <x-admin.status-badge :status="$class->status->value" />
                    </li>
                @empty
                    <li class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-600">
                        Belum ada kelas pada tahun ajaran ini.
                    </li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
