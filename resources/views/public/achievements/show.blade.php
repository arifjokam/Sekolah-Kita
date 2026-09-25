@extends('layouts.public')

@section('title', $achievement['title'].' · Prestasi')

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <x-public.breadcrumbs :items="['Prestasi' => route('achievements.index'), $achievement['title'] => route('achievements.show', $achievement['slug'])]" />
        <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            @if (($achievement['cover']['url'] ?? null))
                <img src="{{ $achievement['cover']['url'] }}" alt="" class="aspect-[16/8] w-full object-cover" width="{{ $achievement['cover']['width'] }}" height="{{ $achievement['cover']['height'] }}">
            @else
                <div class="flex aspect-[16/8] items-center justify-center bg-linear-to-br from-amber-100 via-cyan-50 to-slate-100 text-7xl text-amber-700" aria-hidden="true">★</div>
            @endif
            <div class="p-7 sm:p-10">
                <div class="flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-wide text-cyan-700"><span>{{ $achievement['category'] }}</span><span class="text-slate-300">•</span><span>{{ $achievement['level'] }}</span></div>
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">{{ $achievement['title'] }}</h1>
                <p class="mt-3 text-sm text-slate-500"><time datetime="{{ $achievement['achievement_date'] }}">{{ $achievement['achievement_date_label'] }}</time></p>
                <x-public.prose :text="$achievement['description']" class="mt-8" />
            </div>
        </article>
        <div class="mt-8"><a href="{{ route('achievements.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">← Kembali ke semua prestasi</a></div>
    </div>
@endsection
