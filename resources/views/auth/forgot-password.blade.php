@extends('layouts.auth')

@section('title', 'Lupa kata sandi · Sekolah Kita')

@section('content')
    <div class="rounded-3xl border border-white/10 bg-slate-900/80 p-6 shadow-2xl shadow-cyan-950/30 sm:p-8">
        <p class="text-sm font-medium uppercase tracking-[0.18em] text-cyan-300">Pemulihan akun</p>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-white">Lupa kata sandi?</h1>
        <p class="mt-2 text-sm leading-6 text-slate-400">Masukkan email akun. Jika email terdaftar, kami akan mengirim tautan pemulihan yang berlaku singkat.</p>

        @if (session('status'))
            <div role="status" class="mt-6 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-7 flex flex-col gap-5">
            @csrf

            <div class="flex flex-col gap-2">
                <label for="email" class="text-sm font-medium text-slate-200">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus
                    class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/30"
                    placeholder="nama@sekolah.test">
                @error('email')
                    <p id="email-error" class="text-xs text-rose-300">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                Kirim tautan pemulihan
            </button>
        </form>

        <a href="{{ route('login') }}" class="mt-6 inline-flex text-sm font-medium text-cyan-300 hover:text-cyan-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400">Kembali ke halaman masuk</a>
    </div>
@endsection
