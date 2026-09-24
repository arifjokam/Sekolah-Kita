<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Sekolah Kita</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
        <main class="mx-auto flex min-h-screen max-w-3xl flex-col items-center justify-center gap-8 px-6 py-16 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-cyan-400 text-xl font-bold text-slate-950">SK</div>
            <div class="flex flex-col gap-4">
                <p class="text-sm font-medium uppercase tracking-[0.2em] text-cyan-300">Sekolah Kita</p>
                <h1 class="text-4xl font-semibold tracking-tight text-white sm:text-5xl">Fondasi digital yang aman untuk sekolah.</h1>
                <p class="mx-auto max-w-2xl text-base leading-7 text-slate-400">Phase 1 menyediakan autentikasi dan kontrol akses dasar. Modul sekolah dan konten publik akan hadir setelah keputusan domain disetujui.</p>
            </div>
            <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950">Masuk ke akun</a>
        </main>
    </body>
</html>
