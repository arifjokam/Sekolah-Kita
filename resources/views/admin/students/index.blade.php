@extends('layouts.app')

@section('title', 'Data siswa · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Siswa', 'route' => 'admin.students.index'],
            ['label' => 'Data siswa'],
        ];

        $statusOptions = collect($statuses)->mapWithKeys(
            fn ($status) => [$status->value => $status->label()]
        );

        $classOptions = $classes->mapWithKeys(
            fn ($class) => [$class->id => $class->getFullName().' · '.($class->academicYear?->code ?? '-')]
        );

        $yearOptions = $academicYears->mapWithKeys(
            fn ($year) => [$year->id => $year->label]
        );

        $filterItems = [
            ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nama, NIS, atau NISN'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => $statusOptions],
            ['name' => 'class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => $classOptions],
            ['name' => 'academic_year_id', 'label' => 'Tahun ajaran', 'type' => 'select', 'options' => $yearOptions],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        title="Data siswa"
        description="Record siswa, wali, dan penempatan kelas. Nomor induk siswa dibuat otomatis bila dikosongkan, dan akun portal tidak pernah dibuat otomatis dari halaman ini."
    >
        <x-slot:actions>
            <a href="{{ route('admin.enrollments.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Enrollment</a>
            <a href="{{ route('admin.students.create') }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tambah siswa</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar :items="$filterItems" />

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <caption class="sr-only">Daftar siswa</caption>
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Siswa</th>
                        <th scope="col" class="px-5 py-3">NIS</th>
                        <th scope="col" class="px-5 py-3">Kelas</th>
                        <th scope="col" class="px-5 py-3">Wali</th>
                        <th scope="col" class="px-5 py-3">Akun</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($students as $student)
                        @php $enrollment = $student->enrollments->firstWhere(fn ($item) => $item->status === \App\Enums\EnrollmentStatus::Active); @endphp
                        <tr>
                            <td class="px-5 py-4">
                                <a href="{{ route('admin.students.show', $student) }}" class="font-semibold text-cyan-700 hover:text-cyan-900">{{ $student->legal_name }}</a>
                                <p class="mt-1 text-xs text-slate-500">{{ $student->preferred_name ? 'Dipanggil '.$student->preferred_name : 'Tanpa nama panggilan' }}</p>
                            </td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-600">{{ $student->student_number }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $enrollment?->class?->getFullName() ?? 'Belum ada enrollment' }}</td>
                            <td class="px-5 py-4 text-xs text-slate-600">{{ $student->guardians_count }} wali terhubung</td>
                            <td class="px-5 py-4 text-xs text-slate-600">
                                @if ($student->user)
                                    {{ $student->user->status?->label() }}
                                @else
                                    <span class="text-slate-400">Tanpa akun</span>
                                @endif
                            </td>
                            <td class="px-5 py-4"><x-admin.status-badge :status="$student->status->value" /></td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('admin.students.show', $student) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Detail</a>
                                    <a href="{{ route('admin.students.edit', $student) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-4">
                                <x-admin.empty-state
                                    label="Belum ada data siswa"
                                    message="Tambahkan siswa untuk mulai menautkan wali dan menempatkan mereka pada kelas."
                                    action-label="Tambah siswa"
                                    action-route="admin.students.create"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-pagination :paginator="$students" />
@endsection
