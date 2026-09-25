@extends('layouts.public')

@section('title', $article['title'].' · Berita')
@section('description', $article['excerpt'])

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <x-public.breadcrumbs :items="['Berita' => route('news.index'), $article['title'] => route('news.show', $article['slug'])]" />
        <article>
            <div class="max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-700">Berita sekolah</p>
                <h1 class="mt-4 text-4xl font-bold tracking-tight text-slate-950 sm:text-5xl">{{ $article['title'] }}</h1>
                <p class="mt-5 text-sm text-slate-500"><time datetime="{{ $article['published_at'] }}">{{ $article['published_at_label'] }}</time></p>
                <p class="mt-5 text-lg leading-8 text-slate-600">{{ $article['excerpt'] }}</p>
            </div>
            @if (($article['cover']['url'] ?? null))
                <img src="{{ $article['cover']['url'] }}" alt="Gambar utama berita sekolah" class="mt-10 aspect-[16/8] w-full rounded-3xl object-cover shadow-sm" width="{{ $article['cover']['width'] }}" height="{{ $article['cover']['height'] }}">
            @endif
            <div class="mt-10 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
                <x-public.prose :text="$article['body']" />
            </div>
        </article>
        <div class="mt-8"><a href="{{ route('news.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">← Kembali ke semua berita</a></div>
    </div>
@endsection
