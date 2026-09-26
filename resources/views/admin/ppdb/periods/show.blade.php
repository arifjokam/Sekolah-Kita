@extends('layouts.app')

@section('title', $admissionPeriod->name.' · Periode PPDB')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Periode', 'route' => 'admin.ppdb.periods.index'],
            ['label' => $admissionPeriod->name],
        ];

        $pageDescription = $admissionPeriod->academicYear?->label;
        $pageDescription .= ' · '.$admissionPeriod->opens_at?->translatedFormat('d M Y H:i');
        $pageDescription .= ' — '.$admissionPeriod->closes_at?->translatedFormat('d M Y H:i');
        $pageDescription .= ' · kuota '.($admissionPeriod->quota ?? '∞');
        $pageDescription .= ' · '.$admissionPeriod->applications_count.' pengajuan.';

        $quotaHint = $admissionPeriod->quota === null
            ? 'Periode tanpa batas kuota.'
            : 'Kuota '.$admissionPeriod->quota.' pengajuan.';
        $remainingQuota = $admissionPeriod->quota === null
            ? '∞'
            : max(0, $admissionPeriod->quota - $admissionPeriod->applications_count);
    @endphp

    <x-admin.page-header
        eyebrow="PPDB"
        :title="$admissionPeriod->name"
        :description="$pageDescription"
    >
        <x-slot:actions>
            <a href="{{ route('admin.ppdb.applications.index', ['period' => $admissionPeriod->id]) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Lihat pengajuan</a>
            <a href="{{ route('admin.ppdb.periods.edit', $admissionPeriod) }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Edit</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card
            label="Status periode"
            :value="$admissionPeriod->status->label()"
            :tone="$admissionPeriod->isCurrentlyOpen() ? 'accent' : 'muted'"
        />
        <x-admin.stat-card label="Total pengajuan" :value="$admissionPeriod->applications_count" />
        <x-admin.stat-card
            label="Sisa kuota"
            :value="$remainingQuota"
            :hint="$quotaHint"
        />
        <x-admin.stat-card label="Meut publik" :value="$admissionPeriod->published_at?->translatedFormat('d M Y') ?? 'Belum terbit'" />
    </div>

    <div class="mt-6 flex flex-wrap gap-2">
        @if ($admissionPeriod->status->value === 'draft')
            <form method="POST" action="{{ route('admin.ppdb.periods.open', $admissionPeriod) }}">
                @csrf
                <button class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Buka periode</button>
            </form>
        @endif

        @if ($admissionPeriod->status->value === 'open')
            <form method="POST" action="{{ route('admin.ppdb.periods.close', $admissionPeriod) }}">
                @csrf
                <button class="rounded-lg border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-800 transition hover:bg-amber-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tutup periode</button>
            </form>
        @endif

        @if ($admissionPeriod->status->value !== 'archived')
            <form method="POST" action="{{ route('admin.ppdb.periods.archive', $admissionPeriod) }}">
                @csrf
                <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Arsipkan</button>
            </form>
        @endif

        @if ($admissionPeriod->status->value === 'open' || $admissionPeriod->status->value === 'closed')
            <a href="{{ route('ppdb.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Lihat halaman publik</a>
        @endif
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="status-counts-heading">
            <h2 id="status-counts-heading" class="text-lg font-bold text-slate-950">Sebaran status pengajuan</h2>
            <dl class="mt-4 space-y-2">
                @forelse ($statusCounts as $status => $count)
                    <div class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3 text-sm">
                        <dt class="text-slate-700">{{ \App\Enums\AdmissionApplicationStatus::from($status)->label() }}</dt>
                        <dd class="font-semibold text-slate-950">{{ $count }}</dd>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-600">
                        Belum ada pengajuan pada periode ini.
                    </div>
                @endforelse
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="content-heading">
            <h2 id="content-heading" class="text-lg font-bold text-slate-950">Konten publik</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Deskripsi</dt>
                    <dd class="mt-1 leading-6 text-slate-700">{{ $admissionPeriod->description ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Instruksi</dt>
                    <dd class="mt-1 leading-6 text-slate-700">{{ $admissionPeriod->instructions ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Catatan internal</dt>
                    <dd class="mt-1 leading-6 text-slate-700">{{ $admissionPeriod->internal_notes ?? '—' }}</dd>
                </div>
            </dl>
        </section>
    </div>
@endsection
