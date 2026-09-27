@extends('layouts.app')

@section('title', 'Periode PPDB · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'PPDB'],
            ['label' => 'Periode'],
        ];

        // The period form requires an academic year. Rendering the hint on the
        // list makes the empty dropdown on the create screen explainable.
        $hasAcademicYears = $hasAcademicYears ?? \App\Models\AcademicYear::query()->exists();
    @endphp

    <x-admin.page-header
        eyebrow="PPDB"
        title="Periode penerimaan"
        description="Setiap periode PPDB terikat pada satu tahun ajaran. Formulir publik hanya terbuka ketika periode berstatus dibuka."
    >
        <x-slot:actions>
            <a href="{{ route('admin.ppdb.applications.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Pendaftar</a>
            <a href="{{ route('admin.ppdb.periods.create') }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tambah periode</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <caption class="sr-only">Daftar periode PPDB</caption>
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Periode</th>
                        <th scope="col" class="px-5 py-3">Tahun ajaran</th>
                        <th scope="col" class="px-5 py-3">Jendela</th>
                        <th scope="col" class="px-5 py-3">Kuota</th>
                        <th scope="col" class="px-5 py-3">Pengajuan</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($periods as $period)
                        <tr>
                            <td class="px-5 py-4">
                                <a href="{{ route('admin.ppdb.periods.show', $period) }}" class="font-semibold text-cyan-700 hover:text-cyan-900">{{ $period->name }}</a>
                                <p class="mt-1 font-mono text-xs text-slate-500">{{ $period->slug }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $period->academicYear?->label ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-xs text-slate-600">
                                {{ $period->opens_at?->translatedFormat('d M Y H:i') }}<br>
                                {{ $period->closes_at?->translatedFormat('d M Y H:i') }}
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $period->quota ?? '∞' }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $period->applications_count }}</td>
                            <td class="px-5 py-4"><x-admin.status-badge :status="$period->status->value" /></td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('admin.ppdb.periods.edit', $period) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Edit</a>
                                    @if ($period->status->value === 'draft')
                                        <form method="POST" action="{{ route('admin.ppdb.periods.open', $period) }}">
                                            @csrf
                                            <button class="rounded-lg bg-cyan-700 px-3 py-2 text-xs font-semibold text-white transition hover:bg-cyan-800">Buka</button>
                                        </form>
                                    @elseif ($period->status->value === 'open')
                                        <form method="POST" action="{{ route('admin.ppdb.periods.close', $period) }}">
                                            @csrf
                                            <button class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-50">Tutup</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-4">
                                <x-admin.empty-state
                                    label="Belum ada periode PPDB"
                                    :message="$hasAcademicYears
                                        ? 'Periode PPDB memerlukan tahun ajaran yang sudah ada. Buat periode untuk membuka pendaftaran.'
                                        : 'Periode PPDB terikat pada tahun ajaran, dan tabel tahun ajaran masih kosong. Buat tahun ajaran terlebih dahulu agar pilihan pada formulir periode tidak kosong.'"
                                    action-label="Tambah periode"
                                    action-route="admin.ppdb.periods.create"
                                >
                                    @unless ($hasAcademicYears)
                                        <a href="{{ route('admin.academic-years.create') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Tambah tahun ajaran</a>
                                    @endunless
                                </x-admin.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-pagination :paginator="$periods" />
@endsection
