<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Sekolah Kita')</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        <div class="min-h-screen">
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-3 text-sm font-semibold tracking-wide text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-600 text-sm font-bold text-white">SK</span>
                        <span>Sekolah Kita</span>
                    </a>

                    @auth
                        <div class="flex items-center gap-4">
                            <span class="hidden text-right text-xs text-slate-500 sm:block">
                                {{ auth()->user()->name }}<br>
                                <span class="font-medium text-slate-700">{{ auth()->user()->role?->label() }}</span>
                            </span>
                            @can('access-administrator-area')
                                <a href="{{ route('admin.foundation') }}" class="text-sm font-medium text-cyan-700 hover:text-cyan-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Area administrator</a>
                            @endcan
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Keluar</button>
                            </form>
                        </div>
                    @endauth
                </div>
            </header>

            <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
                @yield('content')
            </main>
        </div>
    </body>
</html>
