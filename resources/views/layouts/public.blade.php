<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $__env->yieldContent('title', ($siteProfile['name'] ?? 'Sekolah Kita').' · Sekolah Kita') }}</title>
        @hasSection('description')
            <meta name="description" content="{{ $__env->yieldContent('description') }}">
        @endif

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        @stack('head')
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:text-sm focus:font-semibold focus:shadow-lg">Lewati ke konten utama</a>

        <header class="border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="group inline-flex min-w-0 items-center gap-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 focus-visible:ring-offset-2">
                    @if (($siteProfile['logo']['url'] ?? null))
                        <img src="{{ $siteProfile['logo']['url'] }}" alt="Logo {{ $siteProfile['name'] }}" class="h-11 w-11 rounded-xl object-cover">
                    @else
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan-700 text-sm font-bold text-white shadow-sm">SK</span>
                    @endif
                    <span class="min-w-0">
                        <span class="block truncate text-base font-bold tracking-tight text-slate-950">{{ $siteProfile['name'] ?? 'Sekolah Kita' }}</span>
                        <span class="hidden text-xs text-slate-500 sm:block">Sekolah yang belajar dan bertumbuh</span>
                    </span>
                </a>

                <nav class="hidden items-center gap-1 lg:flex" aria-label="Navigasi utama">
                    <a href="{{ route('school-profile.show') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 {{ request()->routeIs('school-profile.show') ? 'bg-cyan-50 text-cyan-800' : '' }}">Profil</a>
                    <a href="{{ route('news.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 {{ request()->routeIs('news.*') ? 'bg-cyan-50 text-cyan-800' : '' }}">Berita</a>
                    <a href="{{ route('achievements.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 {{ request()->routeIs('achievements.*') ? 'bg-cyan-50 text-cyan-800' : '' }}">Prestasi</a>
                    <a href="{{ route('gallery.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 {{ request()->routeIs('gallery.*') ? 'bg-cyan-50 text-cyan-800' : '' }}">Galeri</a>
                    <a href="{{ route('teachers.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 {{ request()->routeIs('teachers.*') ? 'bg-cyan-50 text-cyan-800' : '' }}">Guru</a>
                    <a href="{{ route('contact.create') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 {{ request()->routeIs('contact.*') ? 'bg-cyan-50 text-cyan-800' : '' }}">Kontak</a>
                    <a href="{{ route('ppdb.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 {{ request()->routeIs('ppdb.*') ? 'bg-cyan-50 text-cyan-800' : '' }}">PPDB</a>
                </nav>

                <div class="flex items-center gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="hidden rounded-lg bg-cyan-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 focus-visible:ring-offset-2 sm:inline-flex">Dashboard</a>
                        <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Keluar</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="hidden rounded-lg border border-cyan-200 px-3 py-2 text-sm font-semibold text-cyan-800 transition hover:bg-cyan-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 sm:inline-flex">Masuk</a>
                    @endauth

                    <details class="relative lg:hidden">
                        <summary class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-lg border border-slate-300 text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600" aria-label="Buka navigasi">
                            <span class="sr-only">Menu</span>
                            <span class="space-y-1" aria-hidden="true"><span class="block h-0.5 w-5 bg-current"></span><span class="block h-0.5 w-5 bg-current"></span><span class="block h-0.5 w-5 bg-current"></span></span>
                        </summary>
                        <nav class="absolute right-0 top-14 z-40 w-56 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl" aria-label="Navigasi seluler">
                            <a href="{{ route('school-profile.show') }}" class="block rounded-xl px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Profil sekolah</a>
                            <a href="{{ route('news.index') }}" class="block rounded-xl px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Berita</a>
                            <a href="{{ route('achievements.index') }}" class="block rounded-xl px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Prestasi</a>
                            <a href="{{ route('gallery.index') }}" class="block rounded-xl px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Galeri</a>
                            <a href="{{ route('teachers.index') }}" class="block rounded-xl px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Direktori guru</a>
                            <a href="{{ route('contact.create') }}" class="block rounded-xl px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Kontak</a>
                            <a href="{{ route('ppdb.index') }}" class="block rounded-xl px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">PPDB</a>
                            @auth
                                <a href="{{ route('dashboard') }}" class="mt-1 block rounded-xl bg-cyan-700 px-3 py-2 text-center text-sm font-semibold text-white">Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="mt-1 block rounded-xl bg-cyan-700 px-3 py-2 text-center text-sm font-semibold text-white">Masuk</a>
                            @endauth
                        </nav>
                    </details>
                </div>
            </div>
        </header>

        <main id="main-content">
            @yield('content')
        </main>

        <footer class="mt-20 border-t border-slate-200 bg-white">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[1.5fr_1fr_1fr] lg:px-8">
                <div>
                    <p class="text-base font-bold text-slate-950">{{ $siteProfile['name'] ?? 'Sekolah Kita' }}</p>
                    <p class="mt-3 max-w-md text-sm leading-6 text-slate-600">{{ $siteProfile['about']['teaser'] ?? 'Informasi resmi sekolah tersedia di halaman profil.' }}</p>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-950">Jelajahi</p>
                    <div class="mt-3 grid gap-2 text-sm text-slate-600">
                        <a href="{{ route('news.index') }}" class="hover:text-cyan-700">Berita sekolah</a>
                        <a href="{{ route('achievements.index') }}" class="hover:text-cyan-700">Prestasi</a>
                        <a href="{{ route('gallery.index') }}" class="hover:text-cyan-700">Galeri kegiatan</a>
                        <a href="{{ route('teachers.index') }}" class="hover:text-cyan-700">Direktori guru</a>
                        <a href="{{ route('ppdb.index') }}" class="hover:text-cyan-700">Informasi PPDB</a>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-950">Hubungi kami</p>
                    <div class="mt-3 grid gap-2 text-sm text-slate-600">
                        @if (($siteProfile['address_line'] ?? null) || ($siteProfile['city'] ?? null))
                            <address class="not-italic leading-6">{{ $siteProfile['address_line'] }}@if ($siteProfile['city']), {{ $siteProfile['city'] }}@endif @if ($siteProfile['postal_code']) {{ $siteProfile['postal_code'] }}@endif</address>
                        @endif
                        @if (($siteProfile['public_phone'] ?? null))
                            <a href="tel:{{ $siteProfile['public_phone'] }}" class="hover:text-cyan-700">{{ $siteProfile['public_phone'] }}</a>
                        @endif
                        @if (($siteProfile['public_email'] ?? null))
                            <a href="mailto:{{ $siteProfile['public_email'] }}" class="break-all hover:text-cyan-700">{{ $siteProfile['public_email'] }}</a>
                        @endif
                    </div>
                </div>
            </div>
            <div class="border-t border-slate-100 px-4 py-5 text-center text-xs text-slate-500 sm:px-6 lg:px-8">© {{ now()->year }} {{ $siteProfile['name'] ?? 'Sekolah Kita' }}. Seluruh hak dilindungi.</div>
        </footer>

        @stack('scripts')
    </body>
</html>
