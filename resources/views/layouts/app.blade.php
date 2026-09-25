<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Dashboard · Sekolah Kita')</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        @stack('head')
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:text-sm focus:font-semibold focus:shadow-lg">Lewati ke konten utama</a>
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3 text-sm font-semibold tracking-wide text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 focus-visible:ring-offset-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-700 text-sm font-bold text-white">SK</span>
                    <span>Sekolah Kita</span>
                </a>
                <div class="flex items-center gap-3">
                    <a href="{{ route('home') }}" class="hidden text-sm font-medium text-slate-600 hover:text-cyan-700 sm:inline-flex">Lihat situs publik</a>
                    @auth
                        <a href="{{ route('notifications.index') }}" class="inline-flex items-center gap-1 rounded-lg px-2 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-cyan-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                            Notifikasi
                            @if (($unreadNotificationCount ?? 0) > 0)
                                <span class="rounded-full bg-cyan-700 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $unreadNotificationCount }}</span>
                            @endif
                        </a>
                        <span class="hidden text-right text-xs text-slate-500 md:block">{{ auth()->user()->name }}<br><span class="font-medium text-slate-700">{{ auth()->user()->role?->label() }}</span></span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Keluar</button>
                        </form>
                    @endauth
                </div>
            </div>
            @can('access-administrator-area')
                <div class="border-t border-slate-100 bg-slate-50">
                    <nav class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4 sm:px-6 lg:px-8" aria-label="Navigasi administrator">
                        <a href="{{ route('admin.foundation') }}" class="whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium {{ request()->routeIs('admin.foundation') ? 'border-cyan-600 text-cyan-800' : 'border-transparent text-slate-600 hover:text-slate-950' }}">Ringkasan</a>
                        <a href="{{ route('admin.school-profile.edit') }}" class="whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium {{ request()->routeIs('admin.school-profile.*') ? 'border-cyan-600 text-cyan-800' : 'border-transparent text-slate-600 hover:text-slate-950' }}">Profil sekolah</a>
                        <a href="{{ route('admin.news.index') }}" class="whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium {{ request()->routeIs('admin.news.*') ? 'border-cyan-600 text-cyan-800' : 'border-transparent text-slate-600 hover:text-slate-950' }}">Berita</a>
                        <a href="{{ route('admin.achievements.index') }}" class="whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium {{ request()->routeIs('admin.achievements.*') ? 'border-cyan-600 text-cyan-800' : 'border-transparent text-slate-600 hover:text-slate-950' }}">Prestasi</a>
                        <a href="{{ route('admin.gallery-albums.index') }}" class="whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium {{ request()->routeIs('admin.gallery-albums.*') ? 'border-cyan-600 text-cyan-800' : 'border-transparent text-slate-600 hover:text-slate-950' }}">Galeri</a>
                        <a href="{{ route('admin.teachers.index') }}" class="whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium {{ request()->routeIs('admin.teachers.*') ? 'border-cyan-600 text-cyan-800' : 'border-transparent text-slate-600 hover:text-slate-950' }}">Guru publik</a>
                        <a href="{{ route('admin.contact-messages.index') }}" class="whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium {{ request()->routeIs('admin.contact-messages.*') ? 'border-cyan-600 text-cyan-800' : 'border-transparent text-slate-600 hover:text-slate-950' }}">Pesan kontak</a>
                        <a href="{{ route('admin.audit-logs.index') }}" class="whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium {{ request()->routeIs('admin.audit-logs.*') ? 'border-cyan-600 text-cyan-800' : 'border-transparent text-slate-600 hover:text-slate-950' }}">Audit log</a>
                        <a href="{{ route('admin.ppdb.applications.index') }}" class="whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium {{ request()->routeIs('admin.ppdb.*') ? 'border-cyan-600 text-cyan-800' : 'border-transparent text-slate-600 hover:text-slate-950' }}">PPDB</a>
                    </nav>
                </div>
            @endcan
        </header>

        <main id="main-content" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            @if (session('status'))
                <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
        @stack('scripts')
    </body>
</html>
