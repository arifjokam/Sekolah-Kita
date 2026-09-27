@extends('layouts.app')

@section('title', 'Kelas · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Akademik'],
            ['label' => 'Kelas'],
        ];

        $statusOptions = collect($statuses)->mapWithKeys(
            fn ($status) => [$status->value => $status->label()]
        );

        $yearOptions = $academicYears->mapWithKeys(
            fn ($year) => [$year->id => $year->label]
        );

        $filterItems = [
            ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nama kelas'],
            ['name' => 'academic_year_id', 'label' => 'Tahun ajaran', 'type' => 'select', 'options' => $yearOptions],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => $statusOptions],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        title="Kelas"
        description="Nama kelas unik dalam kombinasi tahun ajaran dan tingkat. Kelas tertutup tidak dapat menerima enrollment baru."
    >
        <x-slot:actions>
            <a href="{{ route('admin.enrollments.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Enrollment</a>
            <a href="{{ route('admin.classes.create') }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tambah kelas</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar :items="$filterItems" />

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <caption class="sr-only">Daftar kelas</caption>
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Kelas</th>
                        <th scope="col" class="px-5 py-3">Tahun ajaran</th>
                        <th scope="col" class="px-5 py-3">Kapasitas</th>
                        <th scope="col" class="px-5 py-3">Enrollment</th>
                        <th scope="col" class="px-5 py-3">Wali kelas</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($classes as $class)
                        <tr>
                            <td class="px-5 py-4">
                                <a href="{{ route('admin.classes.show', $class) }}" class="font-semibold text-cyan-700 hover:text-cyan-900">{{ $class->getFullName() }}</a>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $class->academicYear?->label ?? '—' }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $class->capacity ?? '∞' }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $class->enrollments_count }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $class->class_teacher_assignments_count }} penugasan</td>
                            <td class="px-5 py-4"><x-admin.status-badge :status="$class->status->value" /></td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('admin.classes.show', $class) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Detail</a>
                                    <a href="{{ route('admin.classes.edit', $class) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-4">
                                <x-admin.empty-state
                                    label="Belum ada kelas"
                                    message="Kelas dibuat pada satu tahun ajaran tertentu. Buat kelas agar siswa dapat di-enrollment dan penugasan mengajar dapat dibuat."
                                    action-label="Tambah kelas"
                                    action-route="admin.classes.create"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-pagination :paginator="$classes" />
@endsection
