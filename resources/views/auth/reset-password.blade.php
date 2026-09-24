@extends('layouts.auth')

@section('title', 'Atur kata sandi baru · Sekolah Kita')

@section('content')
    <div class="rounded-3xl border border-white/10 bg-slate-900/80 p-6 shadow-2xl shadow-cyan-950/30 sm:p-8">
        <p class="text-sm font-medium uppercase tracking-[0.18em] text-cyan-300">Pemulihan akun</p>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-white">Atur kata sandi baru</h1>
        <p class="mt-2 text-sm leading-6 text-slate-400">Gunakan kata sandi baru yang tidak pernah dipakai untuk akun ini.</p>

        <form method="POST" action="{{ route('password.update') }}" class="mt-7 flex flex-col gap-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="flex flex-col gap-2">
                <label for="email" class="text-sm font-medium text-slate-200">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required
                    class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                @error('email')
                    <p id="email-error" class="text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label for="password" class="text-sm font-medium text-slate-200">Kata sandi baru</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required autofocus
                    class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
                @error('password')
                    <p id="password-error" class="text-xs text-rose-300">{{ $message }}</p>
                @enderror
                <p class="text-xs text-slate-500">Minimal 12 karakter.</p>
            </div>

            <div class="flex flex-col gap-2">
                <label for="password_confirmation" class="text-sm font-medium text-slate-200">Konfirmasi kata sandi</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required
                    class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30">
            </div>

            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                Simpan kata sandi baru
            </button>
        </form>
    </div>
@endsection
