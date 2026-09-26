@extends('layouts.app')

@section('title', 'Wali kelas · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Akademik'],
            ['label' => 'Wali kelas'],
        ];

        $statusOptions = collect($statuses)->mapWithKeys(
            fn ($status) => [$status->value => $status->label()]
        );

        $classOptions = $classes->mapWithKeys(
            fn ($class) => [$class->id => $class->getFullName()]
        );

        $filterItems = [
            ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Nama guru'],
            ['name' => 'class_id', 'label' => 'Kelas', 'type' => 'select', 'options' => $classOptions],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => $statusOptions],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Akademik"
        title="Wali kelas"
        description="Wali kelas memberi akses daftar siswa kelas tersebut pada dashboard guru. Satu kelas hanya boleh memiliki satu penugasan wali kelas aktif untuk guru yang sama."
    >
        <x-slot:actions>
            <a href="{{ route('admin.teaching-assignments.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Assignment mengajar</a>
            <a href="{{ route('admin.class-teacher-assignments.create') }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tambah wali kelas</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar :items="$filterItems" />

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <caption class="sr-only">Daftar penugasan wali kelas</caption>
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Guru</th>
                        <th scope="col" class="px-5 py-3">Kelas</th>
                        <th scope="col" class="px-5 py-3">Tahun ajaran</th>
                        <th scope="col" class="px-5 py-3">Periode</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($assignments as $assignment)
                        <tr>
                            <td class="px-5 py-4 font-semibold text-slate-950">{{ $assignment->teacher?->getDisplayName() ?? 'Guru dihapus' }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $assignment->class?->getFullName() ?? '—' }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $assignment->class?->academicYear?->label ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $assignment->starts_on?->format('d M Y') }} — {{ $assignment->ends_on?->format('d M Y') ?? 'berlangsung' }}</td>
                            <td class="px-5 py-4"><x-admin.status-badge :status="$assignment->status->value" /></td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('admin.class-teacher-assignments.edit', $assignment) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Edit</a>
                                    <form method="POST" action="{{ route('admin.class-teacher-assignments.status.update', $assignment) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $assignment->isActive() ? \App\Enums\AssignmentStatus::Ended->value : \App\Enums\AssignmentStatus::Active->value }}">
                                        <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                            {{ $assignment->isActive() ? 'Akhiri' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-4">
                                <x-admin.empty-state
                                    label="Belum ada wali kelas"
                                    message="Penugasan wali kelas memberi guru akses ke siswa pada kelas yang diampu."
                                    action-label="Tambah wali kelas"
                                    action-route="admin.class-teacher-assignments.create"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-pagination :paginator="$assignments" />
@endsection
