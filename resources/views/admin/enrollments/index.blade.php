@extends('layouts.app')

@section('title', 'Enrollment · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Akademik'],
            ['label' => 'Enrollment'],
        ];

        $statusOptions = collect($statuses)->mapWithKeys(
            fn ($status) => [$status->value => $status->label()]
        );

        $classOptions = $classes->mapWithKeys(
            fn ($class) => [$class->id => $class->getFullName()]
        );

        $yearOptions = $academicYears->mapWithKeys(
            fn ($year) => [$year->id => $year->label]
        );

        $filterItems = [
            ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nama atau NIS siswa'],
            ['name' => 'academic_year_id', 'label' => 'Tahun ajaran', 'type' => 'select', 'options' => $yearOptions],
            ['name' => 'class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => $classOptions],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => $statusOptions],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Akademik"
        title="Enrollment siswa"
        description="Enrollment menempatkan siswa pada kelas dalam satu tahun ajaran. Satu siswa hanya boleh memiliki satu enrollment aktif per tahun ajaran, dan kelas tidak boleh melebihi kapasitas."
    >
        <x-slot:actions>
            <a href="{{ route('admin.students.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Data siswa</a>
            <a href="{{ route('admin.enrollments.create') }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tambah enrollment</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar :items="$filterItems" />

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <caption class="sr-only">Daftar enrollment</caption>
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Siswa</th>
                        <th scope="col" class="px-5 py-3">Kelas</th>
                        <th scope="col" class="px-5 py-3">Tahun ajaran</th>
                        <th scope="col" class="px-5 py-3">Periode</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($enrollments as $enrollment)
                        <tr>
                            <td class="px-5 py-4">
                                <a href="{{ route('admin.students.show', $enrollment->student) }}" class="font-semibold text-cyan-700 hover:text-cyan-900">{{ $enrollment->student?->getDisplayName() ?? 'Siswa dihapus' }}</a>
                                <p class="mt-1 font-mono text-xs text-slate-500">{{ $enrollment->student?->student_number }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $enrollment->class?->getFullName() ?? '—' }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $enrollment->academicYear?->label ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-xs text-slate-600">
                                {{ $enrollment->effective_from?->format('d M Y') ?? '—' }} — {{ $enrollment->effective_to?->format('d M Y') ?? 'berlangsung' }}
                            </td>
                            <td class="px-5 py-4"><x-admin.status-badge :status="$enrollment->status->value" /></td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('admin.enrollments.show', $enrollment) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Detail</a>
                                    <a href="{{ route('admin.enrollments.edit', $enrollment) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-4">
                                <x-admin.empty-state
                                    label="Belum ada enrollment"
                                    message="Enrollment menghubungkan siswa dengan kelas. Buat kelas lebih dahulu agar siswa dapat ditempatkan."
                                    action-label="Tambah enrollment"
                                    action-route="admin.enrollments.create"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-pagination :paginator="$enrollments" />
@endsection
