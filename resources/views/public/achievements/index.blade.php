@extends('layouts.public')

@section('title', 'Prestasi · Sekolah Kita')
@section('description', 'Prestasi sekolah yang dipublikasikan secara aman dan anonim.')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <x-public.breadcrumbs :items="['Prestasi' => route('achievements.index')]" />
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-700">Ruang achievements</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight text-slate-950">Prestasi</h1>
            <p class="mt-4 text-base leading-7 text-slate-600">Informasi prestasi ditampilkan pada tingkat sekolah atau secara anonim. Tidak ada identitas siswa yang dipublikasikan.</p>
        </div>

        @if ($achievements->isEmpty())
            <div class="mt-10"><x-empty-state title="Belum ada prestasi" message="Prestasi yang telah dipublikasikan akan tampil di halaman ini." /></div>
        @else
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($achievements as $achievement)
                    <article class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                        @if (($achievement['cover']['url'] ?? null))
                            <img src="{{ $achievement['cover']['url'] }}" alt="" class="aspect-[16/9] w-full object-cover" width="{{ $achievement['cover']['width'] }}" height="{{ $achievement['cover']['height'] }}" loading="lazy">
                        @else
                            <div class="flex aspect-[16/9] items-center justify-center bg-linear-to-br from-amber-100 to-cyan-100 text-5xl text-amber-700" aria-hidden="true">★</div>
                        @endif
                        <div class="flex flex-1 flex-col p-5">
                            <div class="flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-wide text-cyan-700"><span>{{ $achievement['category'] }}</span><span class="text-slate-300">•</span><span>{{ $achievement['level'] }}</span></div>
                            <h2 class="mt-3 text-lg font-bold leading-6 text-slate-950"><a href="{{ route('achievements.show', $achievement['slug']) }}" class="hover:text-cyan-800">{{ $achievement['title'] }}</a></h2>
                            <p class="mt-2 text-xs text-slate-500"><time datetime="{{ $achievement['achievement_date'] }}">{{ $achievement['achievement_date_label'] }}</time></p>
                            <p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">{{ $achievement['description'] }}</p>
                            <a href="{{ route('achievements.show', $achievement['slug']) }}" class="mt-auto pt-5 text-sm font-semibold text-cyan-700 hover:text-cyan-900">Lihat detail <span aria-hidden="true">→</span></a>
                        </div>
                    </article>
                @endforeach
            </div>
            <x-pagination :paginator="$achievements" />
        @endif
    </div>
@endsection
