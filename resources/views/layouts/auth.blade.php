<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Autentikasi · Sekolah Kita')</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <div class="flex min-h-screen flex-col">
            <header class="border-b border-white/10 px-6 py-5 sm:px-10">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3 text-sm font-semibold tracking-wide text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-400 text-sm font-bold text-slate-950">SK</span>
                    <span>Sekolah Kita</span>
                </a>
            </header>

            <main class="flex flex-1 items-center justify-center px-4 py-12 sm:px-6">
                <section class="w-full max-w-md">
                    @yield('content')
                </section>
            </main>

            <footer class="px-6 py-6 text-center text-xs text-slate-500 sm:px-10">
                Fondasi identitas yang aman untuk Sekolah Kita
            </footer>
        </div>
    </body>
</html>
