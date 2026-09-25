@extends('layouts.public')

@section('title', 'Berita · Sekolah Kita')
@section('description', 'Kabar dan informasi terbaru dari sekolah.')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <x-public.breadcrumbs :items="['Berita' => route('news.index')]" />
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-700">Informasi sekolah</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight text-slate-950">Berita</h1>
            <p class="mt-4 text-base leading-7 text-slate-600">Kabar, kegiatan, dan informasi resmi yang dipublikasikan untuk warga sekolah.</p>
        </div>

        @if ($articles->isEmpty())
            <div class="mt-10"><x-empty-state title="Belum ada berita" message="Berita yang telah diterbitkan akan tampil di halaman ini." /></div>
        @else
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($articles as $article)
                    <article class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                        <a href="{{ route('news.show', $article['slug']) }}" class="block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-cyan-600">
                            @if (($article['cover']['url'] ?? null))
                                <img src="{{ $article['cover']['url'] }}" alt="" class="aspect-[16/9] w-full object-cover transition duration-300 group-hover:scale-105" width="{{ $article['cover']['width'] }}" height="{{ $article['cover']['height'] }}" loading="lazy">
                            @else
                                <div class="flex aspect-[16/9] items-center justify-center bg-linear-to-br from-cyan-100 to-slate-100 text-4xl text-cyan-800" aria-hidden="true">✦</div>
                            @endif
                        </a>
                        <div class="flex flex-1 flex-col p-5">
                            <p class="text-xs font-medium text-slate-500"><time datetime="{{ $article['published_at'] }}">{{ $article['published_at_label'] }}</time></p>
                            <h2 class="mt-2 text-lg font-bold leading-6 text-slate-950"><a href="{{ route('news.show', $article['slug']) }}" class="hover:text-cyan-800">{{ $article['title'] }}</a></h2>
                            <p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">{{ $article['excerpt'] }}</p>
                            <a href="{{ route('news.show', $article['slug']) }}" class="mt-auto pt-5 text-sm font-semibold text-cyan-700 hover:text-cyan-900">Baca selengkapnya <span aria-hidden="true">→</span></a>
                        </div>
                    </article>
                @endforeach
            </div>
            <x-pagination :paginator="$articles" />
        @endif
    </div>
@endsection
