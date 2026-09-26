@extends('layouts.app')

@section('title', 'Guru publik · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Konten'],
            ['label' => 'Guru publik'],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Konten publik"
        title="Profil guru publik"
        description="Hanya nama, jabatan, biografi, dan foto yang telah dipublikasikan yang dibuat tersedia untuk umum. Jangan masukkan NIP, kontak, alamat, atau data lahir."
    >
        <x-slot:actions>
            <a href="{{ route('admin.teachers.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Data guru</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <caption class="sr-only">Daftar guru untuk direktori publik</caption>
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Guru</th>
                        <th scope="col" class="px-5 py-3">Jabatan</th>
                        <th scope="col" class="px-5 py-3">Status publik</th>
                        <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($teachers as $teacher)
                        <tr>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-950">{{ $teacher->legal_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $teacher->employment_status->label() }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $teacher->position_title ?: '—' }}</td>
                            <td class="px-5 py-4">
                                @if ($teacher->is_publicly_visible)
                                    <x-admin.status-badge status="published" />
                                @else
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Privat</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('admin.teacher-directory.edit', $teacher) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Edit profil</a>
                                    @if ($teacher->is_publicly_visible)
                                        <form method="POST" action="{{ route('admin.teacher-directory.unpublish', $teacher) }}">
                                            @csrf
                                            <button class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-50">Tarik</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.teacher-directory.publish', $teacher) }}">
                                            @csrf
                                            <button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700">Terbitkan</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-4">
                                <x-admin.empty-state
                                    label="Belum ada data guru"
                                    message="Tambahkan data guru terlebih dahulu, lalu lengkapi profil publiknya."
                                    action-label="Tambah data guru"
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
