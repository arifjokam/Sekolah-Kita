@extends('layouts.app')

@section('title', 'Pengajuan PPDB · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'PPDB'],
            ['label' => 'Pendaftar'],
        ];

        $statusOptions = collect($statuses)->mapWithKeys(
            fn ($status) => [$status->value => $status->label()]
        );

        $periodOptions = $periods->mapWithKeys(
            fn ($period) => [$period->id => $period->name]
        );

        $reviewerOptions = $reviewers->mapWithKeys(
            fn ($reviewer) => [$reviewer->id => $reviewer->name]
        );

        $filterItems = [
            ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nomor registrasi atau nama pemohon'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => $statusOptions],
            ['name' => 'period', 'label' => 'Periode', 'type' => 'select', 'options' => $periodOptions],
            ['name' => 'reviewer', 'label' => 'Reviewer', 'type' => 'select', 'options' => $reviewerOptions],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="PPDB"
        title="Antrean pengajuan"
        description="Data sensitif hanya tampil kepada administrator dan reviewer yang ditugaskan."
    >
        <x-slot:actions>
            <a href="{{ route('admin.ppdb.review.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Antrean review</a>
            <a href="{{ route('admin.ppdb.periods.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Kelola periode</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Total pengajuan" :value="$summary['total']" />
        <x-admin.stat-card label="Menunggu review" :value="$summary['awaitingReview']" :tone="$summary['awaitingReview'] > 0 ? 'warning' : 'muted'" />
        <x-admin.stat-card label="Menunggu konversi" :value="$summary['acceptedPendingConversion']" />
        <x-admin.stat-card label="Ditandai duplikat" :value="$summary['duplicates']" :tone="$summary['duplicates'] > 0 ? 'warning' : 'muted'" />
    </div>

    <x-admin.filter-bar :items="$filterItems" />

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <caption class="sr-only">Daftar pengajuan PPDB</caption>
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Referensi</th>
                        <th scope="col" class="px-5 py-3">Periode</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3">Konversi</th>
                        <th scope="col" class="px-5 py-3">Reviewer</th>
                        <th scope="col" class="px-5 py-3">Dikirim</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($applications as $application)
                        <tr>
                            <td class="px-5 py-4">
                                <a href="{{ route('admin.ppdb.applications.show', $application) }}" class="font-mono font-semibold text-cyan-700 hover:text-cyan-900">{{ $application->registration_number }}</a>
                                @if ($application->duplicate_flag)
                                    <span class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">Duplikat perlu ditinjau</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $application->period?->name ?? '—' }}</td>
                            <td class="px-5 py-4"><x-admin.status-badge :status="$application->status->value" /></td>
                            <td class="px-5 py-4"><x-admin.status-badge :status="$application->conversion_status->value" /></td>
                            <td class="px-5 py-4 text-slate-600">{{ $application->reviewer?->name ?? 'Belum ditugaskan' }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $application->submitted_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-4">
                                <x-admin.empty-state
                                    label="Belum ada pengajuan"
                                    message="Pengajuan muncul di sini setelah pendaftar mengirim formulir pada periode PPDB yang sedang dibuka."
                                    action-label="Kelola periode PPDB"
                                    action-route="admin.ppdb.periods.index"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-pagination :paginator="$applications" />
@endsection
