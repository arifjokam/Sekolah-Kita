@extends('layouts.auth')

@section('title', 'Verifikasi email · Sekolah Kita')

@section('content')
    <div class="rounded-3xl border border-white/10 bg-slate-900/80 p-6 shadow-2xl shadow-cyan-950/30 sm:p-8">
        <p class="text-sm font-medium uppercase tracking-[0.18em] text-cyan-300">Verifikasi akun</p>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-white">Periksa email Anda</h1>
        <p class="mt-2 text-sm leading-6 text-slate-400">Kami telah mengirim tautan verifikasi ke email akun Anda. Tautan ini diperlukan sebelum area terproteksi dapat digunakan.</p>

        @if (session('status'))
            <div role="status" class="mt-6 rounded-xl border border-emerald-400/20 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        @error('email')
            <div role="alert" class="mt-6 rounded-xl border border-rose-400/20 bg-rose-400/10 px-4 py-3 text-sm text-rose-200">
                {{ $message }}
            </div>
        @enderror

        <div class="mt-7 flex flex-col gap-4">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900">
                    Kirim ulang tautan verifikasi
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:border-slate-500 hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400">
                    Keluar dari akun
                </button>
            </form>
        </div>
    </div>
@endsection
