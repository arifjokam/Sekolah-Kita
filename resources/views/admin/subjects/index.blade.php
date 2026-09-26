@extends('layouts.app')

@section('title', 'Mata pelajaran · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Akademik'],
            ['label' => 'Mata pelajaran'],
        ];

        $filterItems = [
            ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Kode atau nama'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Aktif', 'inactive' => 'Tidak aktif']],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        title="Mata pelajaran"
        description="Kode mata pelajaran unik dan dipakai pada penugasan mengajar. Mata pelajaran nonaktif tidak muncul pada pilihan assignment baru."
    >
        <x-slot:actions>
            <a href="{{ route('admin.subjects.create') }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tambah mata pelajaran</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar :items="$filterItems" />

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <caption class="sr-only">Daftar mata pelajaran</caption>
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Kode</th>
                        <th scope="col" class="px-5 py-3">Nama</th>
                        <th scope="col" class="px-5 py-3">Deskripsi</th>
                        <th scope="col" class="px-5 py-3">Assignment</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($subjects as $subject)
                        <tr>
                            <td class="px-5 py-4 font-mono text-xs font-semibold text-slate-900">{{ $subject->code }}</td>
                            <td class="px-5 py-4 font-semibold text-slate-950">{{ $subject->name }}</td>
                            <td class="max-w-xs px-5 py-4 text-sm text-slate-600">{{ \Illuminate\Support\Str::limit($subject->description, 90) ?: '—' }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ $subject->teaching_assignments_count }}</td>
                            <td class="px-5 py-4">
                                <x-admin.status-badge :status="$subject->status" />
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('admin.subjects.show', $subject) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Detail</a>
                                    <a href="{{ route('admin.subjects.edit', $subject) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Edit</a>
                                    <form method="POST" action="{{ route('admin.subjects.status.update', $subject) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $subject->status === 'active' ? 'inactive' : 'active' }}">
                                        <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                            {{ $subject->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-4">
                                <x-admin.empty-state
                                    label="Belum ada mata pelajaran"
                                    message="Mata pelajaran diperlukan sebelum guru dapat ditugaskan mengajar."
                                    action-label="Tambah mata pelajaran"
                                    action-route="admin.subjects.create"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-pagination :paginator="$subjects" />
@endsection
