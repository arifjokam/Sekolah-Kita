@extends('layouts.auth')

@section('title', 'Akses ditolak · Sekolah Kita')

@section('content')
    <div class="rounded-3xl border border-white/10 bg-slate-900/80 p-6 shadow-2xl shadow-cyan-950/30 sm:p-8">
        <p class="text-sm font-medium uppercase tracking-[0.18em] text-rose-300">403 · Akses ditolak</p>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-white">Anda tidak memiliki izin untuk halaman ini.</h1>
        <p class="mt-3 text-sm leading-6 text-slate-400">Jika Anda yakin memerlukan akses, hubungi administrator sekolah.</p>
        <a href="{{ route('home') }}" class="mt-7 inline-flex text-sm font-medium text-cyan-300 hover:text-cyan-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400">Kembali ke beranda</a>
    </div>
@endsection
