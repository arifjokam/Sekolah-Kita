@extends('layouts.app')

@section('title', 'Tahun ajaran · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Akademik'],
            ['label' => 'Tahun ajaran'],
        ];

        $statusOptions = collect($statuses)->mapWithKeys(
            fn ($status) => [$status->value => $status->label()]
        );

        $filterItems = [
            ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Kode atau label'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => $statusOptions],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        title="Tahun ajaran"
        description="Hanya satu tahun ajaran yang boleh berstatus aktif, karena seluruh query lingkup berjalan bergantung pada satu tahun ajaran berjalan."
    >
        <x-slot:actions>
            <a href="{{ route('admin.terms.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Kelola term</a>
            <a href="{{ route('admin.academic-years.create') }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tambah tahun ajaran</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar :items="$filterItems" />

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <caption class="sr-only">Daftar tahun ajaran</caption>
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Tahun ajaran</th>
                        <th scope="col" class="px-5 py-3">Periode</th>
                        <th scope="col" class="px-5 py-3">Isi</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($academicYears as $academicYear)
                        <tr>
                            <td class="px-5 py-4">
                                <a href="{{ route('admin.academic-years.show', $academicYear) }}" class="font-semibold text-cyan-700 hover:text-cyan-900">{{ $academicYear->label }}</a>
                                <p class="mt-1 font-mono text-xs text-slate-500">{{ $academicYear->code }}</p>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $academicYear->starts_on->format('d M Y') }} — {{ $academicYear->ends_on->format('d M Y') }}</td>
                            <td class="px-5 py-4 text-xs text-slate-600">
                                {{ $academicYear->classes_count }} kelas · {{ $academicYear->terms_count }} term · {{ $academicYear->enrollments_count }} enrollment
                            </td>
                            <td class="px-5 py-4"><x-admin.status-badge :status="$academicYear->status->value" /></td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('admin.academic-years.show', $academicYear) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Detail</a>
                                    <a href="{{ route('admin.academic-years.edit', $academicYear) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-4">
                                <x-admin.empty-state
                                    label="Belum ada tahun ajaran"
                                    message="Tahun ajaran adalah fondasi seluruh data akademik. Buat satu terlebih dahulu agar kelas, term, enrollment, dan PPDB dapat dibuat."
                                    action-label="Tambah tahun ajaran"
                                    action-route="admin.academic-years.create"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-pagination :paginator="$academicYears" />
@endsection
