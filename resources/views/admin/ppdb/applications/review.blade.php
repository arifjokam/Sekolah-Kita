@extends('layouts.app')

@section('title', 'Review PPDB · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'PPDB'],
            ['label' => 'Review'],
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

        $actionableParams = ['actionable' => null];

        if (request('actionable')) {
            $actionableParams = [];
        }
    @endphp

    <x-admin.page-header
        eyebrow="PPDB"
        title="Antrean review"
        description="Pengajuan yang menunggu keputusan ditampilkan lebih dahulu, lalu pengajuan diterima yang belum dikonversi menjadi data siswa."
    >
        <x-slot:actions>
            <a href="{{ route('admin.ppdb.applications.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Semua pendaftar</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Total pengajuan" :value="$summary['total']" />
        <x-admin.stat-card label="Menunggu keputusan" :value="$summary['awaitingReview']" :tone="$summary['awaitingReview'] > 0 ? 'warning' : 'muted'" />
        <x-admin.stat-card label="Menunggu konversi" :value="$summary['acceptedPendingConversion']" />
        <x-admin.stat-card label="Ditandai duplikat" :value="$summary['duplicates']" :tone="$summary['duplicates'] > 0 ? 'warning' : 'muted'" />
    </div>

    <x-admin.filter-bar :items="$filterItems">
        <a href="{{ route('admin.ppdb.review.index', $actionableParams) }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">
            {{ request('actionable') ? 'Tampilkan semua status' : 'Hanya yang menunggu keputusan' }}
        </a>
    </x-admin.filter-bar>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <caption class="sr-only">Antrean review pengajuan PPDB</caption>
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Referensi</th>
                        <th scope="col" class="px-5 py-3">Tahap berikutnya</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3">Reviewer</th>
                        <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($applications as $application)
                        @php
                            $nextStep = match ($application->status->value) {
                                'submitted' => 'Tugaskan reviewer lalu mulai review',
                                'under_review' => 'Minta revisi atau simpan keputusan',
                                'accepted' => $application->conversion_status->value === 'completed' ? 'Selesai dikonversi' : 'Konversi menjadi data siswa',
                                'revision_requested' => 'Menunggu revisi pemohon',
                                default => 'Tidak ada tindakan',
                            };
                        @endphp
                        <tr>
                            <td class="px-5 py-4">
                                <a href="{{ route('admin.ppdb.applications.show', $application) }}" class="font-mono font-semibold text-cyan-700 hover:text-cyan-900">{{ $application->registration_number }}</a>
                                @if ($application->duplicate_flag)
                                    <span class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">Duplikat</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $nextStep }}</td>
                            <td class="px-5 py-4">
                                <x-admin.status-badge :status="$application->status->value" />
                                <span class="mt-1 block text-xs text-slate-500">Konversi: {{ $application->conversion_status->label() }}</span>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $application->reviewer?->name ?? 'Belum ditugaskan' }}</td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('admin.ppdb.applications.show', $application) }}" class="inline-block rounded-lg bg-cyan-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                                    {{ $application->conversion_status->value === 'completed' ? 'Lihat' : 'Tinjau' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-4">
                                <x-admin.empty-state
                                    label="Antrean kosong"
                                    message="Tidak ada pengajuan yang sesuai dengan filter ini. Ubah filter atau buka periode PPDB untuk menerima pendaftar baru."
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
