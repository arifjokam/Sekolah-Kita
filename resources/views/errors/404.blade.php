@extends('layouts.public')

@section('title', 'Halaman tidak ditemukan · Sekolah Kita')

@section('content')
    <div class="mx-auto flex min-h-[55vh] max-w-3xl flex-col items-center justify-center px-4 py-20 text-center sm:px-6">
        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-cyan-700">404</p>
        <h1 class="mt-4 text-4xl font-bold tracking-tight text-slate-950">Halaman tidak ditemukan</h1>
        <p class="mt-4 max-w-lg text-base leading-7 text-slate-600">Alamat yang Anda cari tidak tersedia atau konten belum dipublikasikan.</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3"><a href="{{ route('home') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Kembali ke beranda</a><a href="{{ route('contact.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Hubungi sekolah</a></div>
    </div>
@endsection
