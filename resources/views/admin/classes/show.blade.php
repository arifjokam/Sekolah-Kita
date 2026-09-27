@extends('layouts.app')

@section('title', $class->getFullName().' · Kelas')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Kelas', 'route' => 'admin.classes.index'],
            ['label' => $class->getFullName()],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        :title="$class->getFullName()"
        :description="$class->academicYear?->label.' · kapasitas '.($class->capacity ?? 'tak dibatasi').' · '.$students->count().' siswa aktif.'"
    >
        <x-slot:actions>
            <a href="{{ route('admin.enrollments.create', ['class_id' => $class->id]) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tambah enrollment</a>
            <a href="{{ route('admin.classes.edit', $class) }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Edit kelas</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="students-heading">
                <h2 id="students-heading" class="text-lg font-bold text-slate-950">Siswa pada kelas ini</h2>
                <ul class="mt-4 divide-y divide-slate-100">
                    @forelse ($students as $enrollment)
                        <li class="flex items-center justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <a href="{{ route('admin.students.show', $enrollment->student) }}" class="font-semibold text-cyan-700 hover:text-cyan-900">{{ $enrollment->student?->getDisplayName() ?? 'Siswa dihapus' }}</a>
                                <p class="mt-1 text-xs text-slate-500">{{ $enrollment->student?->student_number }} · sejak {{ $enrollment->effective_from?->format('d M Y') }}</p>
                            </div>
                            <a href="{{ route('admin.enrollments.show', $enrollment) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Detail</a>
                        </li>
                    @empty
                        <li class="py-8 text-center text-sm text-slate-600">Belum ada siswa pada kelas ini.</li>
                    @endforelse
                </ul>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="teaching-heading">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="teaching-heading" class="text-lg font-bold text-slate-950">Assignment mengajar</h2>
                    <a href="{{ route('admin.teaching-assignments.create', ['class_id' => $class->id]) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Tambah assignment</a>
                </div>

                <ul class="mt-4 divide-y divide-slate-100">
                    @forelse ($teachingAssignments as $assignment)
                        <li class="py-3">
                            <p class="font-semibold text-slate-950">{{ $assignment->subject?->name ?? 'Mata pelajaran dihapus' }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $assignment->teacher?->getDisplayName() ?? 'Guru dihapus' }} · {{ $assignment->academicYear?->label ?? '—' }}
                                · {{ $assignment->starts_on?->format('d M Y') }} — {{ $assignment->ends_on?->format('d M Y') ?? 'berlangsung' }}
                            </p>
                            <div class="mt-2 flex items-center gap-2">
                                <x-admin.status-badge :status="$assignment->status->value" />
                                <a href="{{ route('admin.teaching-assignments.edit', $assignment) }}" class="text-xs font-semibold text-cyan-700 hover:text-cyan-900">Edit</a>
                            </div>
                        </li>
                    @empty
                        <li class="py-8 text-center text-sm text-slate-600">Belum ada assignment mengajar pada kelas ini.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="class-status-heading">
                <h2 id="class-status-heading" class="text-lg font-bold text-slate-950">Status kelas</h2>
                <p class="mt-3"><x-admin.status-badge :status="$class->status->value" /></p>

                <form method="POST" action="{{ route('admin.classes.status.update', $class) }}" class="mt-4 flex items-end gap-2">
                    @csrf
                    @method('PATCH')
                    <div class="min-w-0 flex-1">
                        <label for="status" class="block text-sm font-medium text-slate-700">Ubah status</label>
                        <select id="status" name="status" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected($status === $class->status)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Simpan</button>
                </form>

                @if ($class->closed_at)
                    <p class="mt-3 text-xs text-slate-500">Ditutup {{ $class->closed_at->format('d M Y') }}{{ $class->closure_reason ? ' · '.$class->closure_reason : '' }}</p>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="homeroom-heading">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="homeroom-heading" class="text-lg font-bold text-slate-950">Wali kelas</h2>
                    <a href="{{ route('admin.class-teacher-assignments.create', ['class_id' => $class->id]) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Tambah</a>
                </div>

                <ul class="mt-4 space-y-2">
                    @forelse ($class->classTeacherAssignments as $assignment)
                        <li class="rounded-xl border border-slate-200 px-4 py-3 text-sm">
                            <p class="font-semibold text-slate-950">{{ $assignment->teacher?->getDisplayName() ?? 'Guru dihapus' }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $assignment->starts_on?->format('d M Y') }} — {{ $assignment->ends_on?->format('d M Y') ?? 'berlangsung' }}</p>
                            <div class="mt-2 flex items-center gap-2">
                                <x-admin.status-badge :status="$assignment->status->value" />
                                <a href="{{ route('admin.class-teacher-assignments.edit', $assignment) }}" class="text-xs font-semibold text-cyan-700 hover:text-cyan-900">Edit</a>
                            </div>
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-600">Belum ada wali kelas.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
@endsection
