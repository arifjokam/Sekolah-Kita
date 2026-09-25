@extends('layouts.public')

@section('title', 'Galeri · Sekolah Kita')
@section('description', 'Galeri kegiatan sekolah yang telah dipublikasikan.')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <x-public.breadcrumbs :items="['Galeri' => route('gallery.index')]" />
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-700">Cerita dalam gambar</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight text-slate-950">Galeri</h1>
            <p class="mt-4 text-base leading-7 text-slate-600">Album kegiatan yang telah ditinjau dan dipublikasikan oleh administrator sekolah.</p>
        </div>

        @if ($albums->isEmpty())
            <div class="mt-10"><x-empty-state title="Belum ada album" message="Album galeri yang telah dipublikasikan akan tampil di halaman ini." /></div>
        @else
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($albums as $album)
                    <a href="{{ route('gallery.show', $album['slug']) }}" class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-1 hover:border-cyan-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                        <div class="flex aspect-[16/10] items-center justify-center rounded-2xl bg-linear-to-br from-cyan-100 to-slate-200 text-6xl text-cyan-800" aria-hidden="true">▧</div>
                        <h2 class="mt-5 text-xl font-bold text-slate-950 group-hover:text-cyan-800">{{ $album['title'] }}</h2>
                        <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{{ $album['description'] }}</p>
                        <p class="mt-4 text-sm font-semibold text-cyan-700">Lihat album <span aria-hidden="true">→</span></p>
                    </a>
                @endforeach
            </div>
            <x-pagination :paginator="$albums" />
        @endif
    </div>
@endsection
