@extends('layouts.auth')

@section('title', 'Masuk · Sekolah Kita')

@section('content')
    <div class="rounded-3xl border border-white/10 bg-slate-900/80 p-6 shadow-2xl shadow-cyan-950/30 sm:p-8">
        <p class="text-sm font-medium uppercase tracking-[0.18em] text-cyan-300">Portal akun</p>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-white">Masuk ke Sekolah Kita</h1>
        <p class="mt-2 text-sm leading-6 text-slate-400">Gunakan email dan kata sandi akun yang telah dibuat oleh administrator.</p>

        @if (session('status'))
            <div role="status" class="mt-6 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="mt-7 flex flex-col gap-5">
            @csrf

            <div class="flex flex-col gap-2">
                <label for="email" class="text-sm font-medium text-slate-200">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" inputmode="email" required autofocus
                    class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-600 focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30"
                    placeholder="nama@sekolah.test">
                @error('email')
                    <p id="email-error" class="text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-4">
                    <label for="password" class="text-sm font-medium text-slate-200">Kata sandi</label>
                    <a href="{{ route('password.request') }}" class="text-xs font-medium text-cyan-300 hover:text-cyan-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400">Lupa kata sandi?</a>
                </div>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                    class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                @error('password')
                    <p id="password-error" class="text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-3 text-sm text-slate-400">
                <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-600 bg-slate-950 text-cyan-400 focus:ring-cyan-400" @checked(old('remember'))>
                <span>Ingat saya di perangkat ini</span>
            </label>

            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                Masuk
            </button>
        </form>

        <p class="mt-6 text-center text-xs leading-5 text-slate-500">Belum memiliki akses? Hubungi administrator sekolah untuk provisioning akun.</p>
    </div>
@endsection
