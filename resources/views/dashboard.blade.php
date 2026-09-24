@extends('layouts.app')

@section('title', 'Dashboard · Sekolah Kita')

@section('content')
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-medium uppercase tracking-[0.18em] text-cyan-700">Fondasi aplikasi</p>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">Halo, {{ $user->name }}.</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Autentikasi dan kontrol akses aktif. Modul domain dan dashboard bisnis akan ditambahkan pada fase berikutnya setelah keputusan domain disetujui.</p>

        <dl class="mt-8 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl bg-slate-50 p-4">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Peran</dt>
                <dd class="mt-2 text-sm font-semibold text-slate-900">{{ $user->role?->label() }}</dd>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Status akun</dt>
                <dd class="mt-2 text-sm font-semibold text-slate-900">{{ $user->status?->label() }}</dd>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Email</dt>
                <dd class="mt-2 text-sm font-semibold text-slate-900">{{ $user->hasVerifiedEmail() ? 'Terverifikasi' : 'Belum terverifikasi' }}</dd>
            </div>
        </dl>
    </div>
@endsection
