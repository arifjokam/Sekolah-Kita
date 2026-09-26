@extends('layouts.app')

@section('title', 'Kelas saya · Sekolah Kita')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.guru'],
            ['label' => 'Kelas saya'],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Guru"
        title="Kelas yang saya ampu"
        description="Hanya kelas dengan penugasan mengajar atau wali kelas aktif yang terhubung dengan akun Anda."
    />

    @unless ($hasProfile)
        <x-admin.empty-state
            label="Data guru belum terhubung"
            message="Akun ini belum terhubung ke data guru, sehingga lingkup kelas belum dapat ditentukan. Administrator perlu menautkan akun ini ke record guru."
        >
            <span class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Hubungi administrator sekolah</span>
        </x-admin.empty-state>
    @endunless

    @if ($hasProfile)
        @if ($classes->isEmpty())
            <x-admin.empty-state
                label="Belum ada kelas terNKEE"
                message="Tidak ada penugasan mengajar atau wali kelas aktif yang terhubung dengan data guru Anda. Administrator perlu membuat penugasan terlebih dahulu."
            >
                <a href="{{ route('admin.teaching-assignments.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Lihat daftar assignment</a>
            </x-admin.empty-state>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($classes as $class)
                    <a
                        href="{{ route('guru.classes.show', $class) }}"
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-cyan-300 hover:bg-cyan-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600"
                    >
                        <p class="text-lg font-bold text-slate-950">{{ $class->getFullName() }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $class->academicYear?->label ?? 'Tanpa tahun ajaran' }}</p>
                        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {{ $class->enrollments_count }} siswa · {{ $class->status->label() }}
                        </p>
                        @if ($homeroomClassIds->contains($class->id))
                            <p class="mt-3 inline-flex rounded-full bg-cyan-100 px-2.5 py-1 text-xs font-semibold text-cyan-800">Wali kelas</p>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    @endif
@endsection
