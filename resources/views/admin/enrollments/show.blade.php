@extends('layouts.app')

@section('title', 'Detail enrollment · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Enrollment', 'route' => 'admin.enrollments.index'],
            ['label' => $enrollment->student?->getDisplayName() ?? 'Siswa' ],
        ];

        $transferTargets = $classes->where('academic_year_id', $enrollment->academic_year_id)->where('id', '!=', $enrollment->class_id);

        // Component attribute values are pre-computed: Blade splices them into
        // a PHP array literal, and a long concatenated expression inside one is
        // fragile to lex.
        $pageTitle = ($enrollment->student?->getDisplayName() ?? 'Siswa').' di '.($enrollment->class?->getFullName() ?? 'kelas dihapus');
        $pageDescription = $enrollment->academicYear?->label;
        $pageDescription .= ' · status '.$enrollment->status->label();
        $pageDescription .= ' · berlaku '.($enrollment->effective_from?->format('d M Y') ?? '—');
        $pageDescription .= ' — '.($enrollment->effective_to?->format('d M Y') ?? 'berlangsung');
    @endphp

    <x-admin.page-header
        eyebrow="Akademik"
        :title="$pageTitle"
        :description="$pageDescription"
    >
        <x-slot:actions>
            <a href="{{ route('admin.enrollments.edit', $enrollment) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Edit</a>
            <a href="{{ route('admin.students.show', $enrollment->student) }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Data siswa</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="detail-heading">
                <h2 id="detail-heading" class="text-lg font-bold text-slate-950">Rincian enrollment</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Siswa</dt><dd class="mt-1 text-sm text-slate-900">{{ $enrollment->student?->legal_name ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nomor induk</dt><dd class="mt-1 font-mono text-sm text-slate-900">{{ $enrollment->student?->student_number ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kelas</dt><dd class="mt-1 text-sm text-slate-900">{{ $enrollment->class?->getFullName() ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tahun ajaran</dt><dd class="mt-1 text-sm text-slate-900">{{ $enrollment->academicYear?->label ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal enrollment</dt><dd class="mt-1 text-sm text-slate-900">{{ $enrollment->enrolled_on?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal selesai</dt><dd class="mt-1 text-sm text-slate-900">{{ $enrollment->completed_on?->format('d M Y') ?? '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alasan transisi</dt><dd class="mt-1 text-sm leading-6 text-slate-900">{{ $enrollment->transition_reason ?? '—' }}</dd></div>
                </dl>
            </section>

            @if ($enrollment->isActive() && $transferTargets->isNotEmpty())
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="transfer-heading">
                    <h2 id="transfer-heading" class="text-lg font-bold text-slate-950">Pindahkan kelas</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Perpindahan mengakhiri penempatan saat ini dan membuat penempatan baru pada kelas tujuan dalam satu transaksi.
                    </p>

                    <form method="POST" action="{{ route('admin.enrollments.transfer', $enrollment) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                        @csrf
                        <x-form.select
                            name="class_id"
                            label="Kelas tujuan"
                            required
                            :options="$transferTargets->mapWithKeys(fn ($class) => [$class->id => $class->getFullName().' (kapasitas '.($class->capacity ?? '∞').')'])"
                        />
                        <x-form.date name="effective_on" label="Berlaku mulai" required :value="now()->toDateString()" />
                        <div class="sm:col-span-2">
                            <x-form.textarea name="transition_reason" label="Alasan perpindahan" :rows="2" maxlength="1000" />
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Pindahkan siswa</button>
                        </div>
                    </form>
                </section>
            @endif
        </div>

        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="status-heading">
                <h2 id="status-heading" class="text-lg font-bold text-slate-950">Status</h2>
                <p class="mt-3"><x-admin.status-badge :status="$enrollment->status->value" /></p>
                <p class="mt-3 text-sm text-slate-600">
                    {{ $enrollment->isActive() ? 'Enrollment ini sedang berjalan.' : 'Enrollment ini tidak sedang berjalan.' }}
                </p>
            </section>
        </div>
    </div>
@endsection
