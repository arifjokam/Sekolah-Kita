@extends('layouts.public')

@section('title', ($siteProfile['name'] ?? 'Sekolah Kita').' · Beranda')

@section('content')
    <section class="bg-slate-950 text-white">
        <div class="mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:px-8">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-[0.22em] text-cyan-300">Selamat datang</p>
                <h1 class="mt-5 text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl">{{ $siteProfile['name'] ?? 'Sekolah Kita' }}</h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-300">{{ $siteProfile['about'] ?? 'Temukan informasi, kegiatan, dan prestasi sekolah dalam satu tempat.' }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('school-profile.show') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-400 px-5 py-3 text-sm font-bold text-slate-950 transition hover:bg-cyan-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">Kenali sekolah kami</a>
                    <a href="{{ route('news.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 px-5 py-3 text-sm font-semibold text-white transition hover:border-white/50 hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300">Lihat berita</a>
                </div>
            </div>
            <div class="relative overflow-hidden rounded-3xl border border-white/10 bg-slate-900 shadow-2xl">
                @if (($siteProfile['hero']['url'] ?? null))
                    <img src="{{ $siteProfile['hero']['url'] }}" alt="Gambar utama {{ $siteProfile['name'] }}" class="aspect-[4/3] w-full object-cover" width="{{ $siteProfile['hero']['width'] }}" height="{{ $siteProfile['hero']['height'] }}">
                @else
                    <div class="flex aspect-[4/3] items-center justify-center bg-linear-to-br from-cyan-800 via-slate-800 to-slate-950 p-8 text-center">
                        <div>
                            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-3xl bg-cyan-400 text-2xl font-black text-slate-950">SK</div>
                            <p class="mt-6 text-lg font-semibold text-white">Tempat belajar dan bertumbuh bersama.</p>
                        </div>
                    </div>
                @endif
                <div class="absolute inset-x-0 bottom-0 bg-linear-to-t from-slate-950/90 to-transparent px-6 pb-6 pt-16">
                    <p class="text-sm text-slate-200">Informasi resmi dan cerita dari sekolah</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Kabar terbaru</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Berita sekolah</h2>
            </div>
            <a href="{{ route('news.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Lihat semua berita <span aria-hidden="true">→</span></a>
        </div>
        @forelse ($latestNews as $article)
            <div class="mt-8 grid gap-6 md:grid-cols-3">
                <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                    <a href="{{ route('news.show', $article['slug']) }}" class="block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-cyan-600">
                        @if (($article['cover']['url'] ?? null))
                            <img src="{{ $article['cover']['url'] }}" alt="" class="aspect-[16/9] w-full object-cover transition duration-300 group-hover:scale-105" width="{{ $article['cover']['width'] }}" height="{{ $article['cover']['height'] }}" loading="lazy">
                        @else
                            <div class="flex aspect-[16/9] items-center justify-center bg-linear-to-br from-cyan-100 to-slate-100 text-cyan-800"><span class="text-4xl font-black" aria-hidden="true">✦</span></div>
                        @endif
                    </a>
                    <div class="p-5">
                        <p class="text-xs font-medium text-slate-500"><time datetime="{{ $article['published_at'] }}">{{ $article['published_at_label'] }}</time></p>
                        <h3 class="mt-2 text-lg font-bold leading-6 text-slate-950"><a href="{{ route('news.show', $article['slug']) }}" class="hover:text-cyan-800">{{ $article['title'] }}</a></h3>
                        <p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">{{ $article['excerpt'] }}</p>
                    </div>
                </article>
            </div>
        @empty
            <div class="mt-8"><x-empty-state title="Belum ada berita" message="Berita yang telah diterbitkan akan tampil di sini." /></div>
        @endforelse
    </section>

    <section class="border-y border-slate-200 bg-white">
        <div class="mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8">
            <div>
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Ruang achievements</p>
                        <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Prestasi</h2>
                    </div>
                    <a href="{{ route('achievements.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Lihat semua →</a>
                </div>
                <div class="mt-6 grid gap-4">
                    @forelse ($latestAchievements as $achievement)
                        <a href="{{ route('achievements.show', $achievement['slug']) }}" class="rounded-2xl border border-slate-200 p-5 transition hover:border-cyan-300 hover:bg-cyan-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide text-cyan-700"><span>{{ $achievement['category'] }}</span><span class="text-slate-300">•</span><span>{{ $achievement['level'] }}</span></div>
                            <h3 class="mt-2 text-lg font-bold text-slate-950">{{ $achievement['title'] }}</h3>
                            <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{{ $achievement['description'] }}</p>
                        </a>
                    @empty
                        <p class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-600">Belum ada prestasi yang dipublikasikan.</p>
                    @endforelse
                </div>
            </div>
            <div>
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Cerita kegiatan</p>
                        <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Galeri</h2>
                    </div>
                    <a href="{{ route('gallery.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Lihat galeri →</a>
                </div>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    @forelse ($latestAlbums as $album)
                        <a href="{{ route('gallery.show', $album['slug']) }}" class="group rounded-2xl border border-slate-200 bg-slate-50 p-5 transition hover:border-cyan-300 hover:bg-cyan-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                            <div class="flex h-24 items-center justify-center rounded-xl bg-linear-to-br from-cyan-100 to-slate-200 text-3xl text-cyan-800" aria-hidden="true">▧</div>
                            <h3 class="mt-4 font-bold text-slate-950 group-hover:text-cyan-800">{{ $album['title'] }}</h3>
                            <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $album['description'] }}</p>
                        </a>
                    @empty
                        <p class="rounded-2xl bg-slate-50 p-5 text-sm text-slate-600 sm:col-span-2">Belum ada album galeri yang dipublikasikan.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="rounded-3xl bg-cyan-800 px-6 py-10 text-white sm:px-10 lg:flex lg:items-center lg:justify-between lg:gap-10">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-200">Punya pertanyaan?</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight">Mari terhubung dengan sekolah.</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-cyan-100">Kirim pesan melalui formulir kontak. Pesan Anda hanya dapat dilihat administrator sekolah.</p>
            </div>
            <a href="{{ route('contact.create') }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-cyan-900 transition hover:bg-cyan-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white lg:mt-0">Hubungi kami</a>
        </div>
    </section>
@endsection
