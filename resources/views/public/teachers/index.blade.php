@extends('layouts.public')

@section('title', 'Direktori Guru · Sekolah Kita')
@section('description', 'Profil profesional guru yang telah disetujui untuk dipublikasikan.')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <x-public.breadcrumbs :items="['Direktori guru' => route('teachers.index')]" />
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-700">Tenaga pendidik</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight text-slate-950">Direktori guru</h1>
            <p class="mt-4 text-base leading-7 text-slate-600">Hanya profil profesional yang telah disetujui administrator yang ditampilkan. Data kontak dan identitas pribadi tidak tersedia di halaman publik.</p>
        </div>

        @if ($teachers->isEmpty())
            <div class="mt-10"><x-empty-state title="Belum ada profil guru" message="Profil guru yang telah disetujui akan tampil di direktori publik." /></div>
        @else
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($teachers as $teacher)
                    <article class="group rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                        <a href="{{ route('teachers.show', $teacher['slug']) }}" class="block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                            @if (($teacher['photo']['url'] ?? null))
                                <img src="{{ $teacher['photo']['url'] }}" alt="Foto {{ $teacher['name'] }}" class="mx-auto h-32 w-32 rounded-full object-cover ring-4 ring-cyan-50" width="{{ $teacher['photo']['width'] }}" height="{{ $teacher['photo']['height'] }}" loading="lazy">
                            @else
                                <span class="mx-auto flex h-32 w-32 items-center justify-center rounded-full bg-cyan-100 text-3xl font-bold text-cyan-800 ring-4 ring-cyan-50" aria-hidden="true">{{ str($teacher['name'])->substr(0, 1)->upper() }}</span>
                            @endif
                            <h2 class="mt-5 text-lg font-bold text-slate-950 group-hover:text-cyan-800">{{ $teacher['name'] }}</h2>
                            @if ($teacher['position_title'])
                                <p class="mt-1 text-sm font-medium text-cyan-700">{{ $teacher['position_title'] }}</p>
                            @endif
                        </a>
                        <a href="{{ route('teachers.show', $teacher['slug']) }}" class="mt-4 inline-block text-sm font-semibold text-cyan-700 hover:text-cyan-900">Lihat profil <span aria-hidden="true">→</span></a>
                    </article>
                @endforeach
            </div>
            <x-pagination :paginator="$teachers" />
        @endif
    </div>
@endsection
