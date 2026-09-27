@extends('layouts.app')

@section('title', $class->getFullName().' · Kelas saya')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.guru'],
            ['label' => 'Kelas saya', 'route' => 'guru.classes.index'],
            ['label' => $class->getFullName()],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Guru"
        :title="$class->getFullName()"
        :description="$class->academicYear?->label.' · '.$students->count().' siswa aktif pada kelas ini.'"
    />

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-950">Siswa pada kelas ini</h2>
            @if ($isHomeroom)
                <span class="rounded-full bg-cyan-100 px-2.5 py-1 text-xs font-semibold text-cyan-800">Anda wali kelas ini</span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <caption class="sr-only">Siswa pada {{ $class->getFullName() }}</caption>
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Siswa</th>
                        <th scope="col" class="px-5 py-3">Nomor induk</th>
                        <th scope="col" class="px-5 py-3">Sejak</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($students as $enrollment)
                        <tr>
                            <td class="px-5 py-3 font-semibold text-slate-950">{{ $enrollment->student?->getDisplayName() ?? 'Siswa dihapus' }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-slate-600">{{ $enrollment->student?->student_number ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $enrollment->effective_from?->translatedFormat('d M Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-10 text-center text-sm text-slate-600">
                                Belum ada siswa aktif pada kelas ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
