@extends('layouts.app')

@section('title', 'Data guru · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Guru', 'route' => 'admin.teachers.index'],
            ['label' => 'Data guru'],
        ];

        $statusOptions = collect($statuses)->mapWithKeys(
            fn ($status) => [$status->value => $status->label()]
        );

        $filterItems = [
            ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nama, NIP, atau jabatan'],
            ['name' => 'status', 'label' => 'Status kepegawaian', 'type' => 'select', 'options' => $statusOptions],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        title="Data guru"
        description="Record kepegawaian guru. Data privat di sini tidak pernah ditampilkan di direktori publik; profil publik dikelola terpisah pada halaman Guru publik."
    >
        <x-slot:actions>
            <a href="{{ route('admin.teacher-directory.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Guru publik</a>
            <a href="{{ route('admin.teachers.create') }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tambah guru</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar :items="$filterItems" />

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <caption class="sr-only">Daftar guru</caption>
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Guru</th>
                        <th scope="col" class="px-5 py-3">NIP</th>
                        <th scope="col" class="px-5 py-3">Jabatan</th>
                        <th scope="col" class="px-5 py-3">Akun</th>
                        <th scope="col" class="px-5 py-3">Penugasan</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($teachers as $teacher)
                        <tr>
                            <td class="px-5 py-4">
                                <a href="{{ route('admin.teachers.show', $teacher) }}" class="font-semibold text-cyan-700 hover:text-cyan-900">{{ $teacher->legal_name }}</a>
                                <p class="mt-1 text-xs text-slate-500">{{ $teacher->preferred_name ? 'Dipanggil '.$teacher->preferred_name : 'Tanpa nama panggilan' }}</p>
                            </td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-600">{{ $teacher->employee_number }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $teacher->position_title ?: '—' }}</td>
                            <td class="px-5 py-4 text-slate-600">
                                @if ($teacher->user)
                                    <span class="text-xs">{{ $teacher->user->email }}</span>
                                    <span class="mt-1 block text-xs font-semibold text-slate-500">{{ $teacher->user->status?->label() }}</span>
                                @else
                                    <span class="text-xs text-slate-400">Tanpa akun</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-600">
                                {{ $teacher->teaching_assignments_count }} mengajar
                                <span class="block text-slate-400">{{ $teacher->class_teacher_assignments_count }} wali kelas</span>
                            </td>
                            <td class="px-5 py-4"><x-admin.status-badge :status="$teacher->employment_status->value" /></td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('admin.teachers.show', $teacher) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Detail</a>
                                    <a href="{{ route('admin.teachers.edit', $teacher) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-4">
                                <x-admin.empty-state
                                    label="Belum ada data guru"
                                    message="Tambahkan record guru untuk mulai menugaskan guru ke kelas dan mata pelajaran."
                                    action-label="Tambah guru"
                                    action-route="admin.teachers.create"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-pagination :paginator="$teachers" />
@endsection
