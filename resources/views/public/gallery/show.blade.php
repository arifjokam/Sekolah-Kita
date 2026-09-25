@extends('layouts.public')

@section('title', $album['title'].' · Galeri')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <x-public.breadcrumbs :items="['Galeri' => route('gallery.index'), $album['title'] => route('gallery.show', $album['slug'])]" />
        <div class="max-w-3xl">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-700">Album kegiatan</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight text-slate-950">{{ $album['title'] }}</h1>
            @if ($album['description'])
                <p class="mt-4 text-base leading-7 text-slate-600">{{ $album['description'] }}</p>
            @endif
        </div>

        @if ($items->isEmpty())
            <div class="mt-10"><x-empty-state title="Belum ada media publik" message="Media yang telah ditinjau dan dipublikasikan akan tampil di album ini." /></div>
        @else
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $item)
                    <figure class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        @if (($item['image']['url'] ?? null))
                            <img src="{{ $item['image']['url'] }}" alt="{{ $item['alt_text'] }}" class="aspect-[4/3] w-full object-cover" width="{{ $item['image']['width'] }}" height="{{ $item['image']['height'] }}" loading="lazy">
                        @endif
                        <figcaption class="p-4">
                            @if ($item['caption'])
                                <p class="text-sm font-semibold text-slate-950">{{ $item['caption'] }}</p>
                            @endif
                            @if ($item['event_date_label'])
                                <p class="mt-1 text-xs text-slate-500"><time datetime="{{ $item['event_date'] }}">{{ $item['event_date_label'] }}</time></p>
                            @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
            <x-pagination :paginator="$items" />
        @endif
    </div>
@endsection
