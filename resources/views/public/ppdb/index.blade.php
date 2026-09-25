@extends('layouts.public')

@section('title', 'PPDB · Sekolah Kita')
@section('description', 'Informasi penerimaan peserta baru Sekolah Kita.')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
        <header class="max-w-3xl">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-700">Penerimaan peserta baru</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight text-slate-950 sm:text-5xl">PPDB Sekolah Kita</h1>
            <p class="mt-4 text-base leading-8 text-slate-600">Lihat informasi resmi penerimaan peserta baru. Pengajuan dan status aplikasi bersifat pribadi dan hanya dapat diakses melalui tautan status yang aman.</p>
        </header>

        @if ($openPeriod)
            <section class="mt-10 rounded-3xl bg-cyan-800 p-6 text-white shadow-xl sm:p-8" aria-labelledby="open-period-heading">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-100">Pendaftaran sedang dibuka</p>
                        <h2 id="open-period-heading" class="mt-2 text-2xl font-bold">{{ $openPeriod['name'] }}</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-cyan-50">{{ $openPeriod['description'] }}</p>
                        <p class="mt-4 text-sm font-medium">{{ $openPeriod['opens_at_label'] }} — {{ $openPeriod['closes_at_label'] }}</p>
                    </div>
                    <a href="{{ route('ppdb.application.create') }}" class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-cyan-800 transition hover:bg-cyan-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-cyan-800">Mulai pengajuan</a>
                </div>
            </section>
        @else
            <div class="mt-10">
                <x-empty-state title="Pendaftaran belum dibuka" message="Periode PPDB yang sedang aktif akan tampil di halaman ini. Silakan kembali lagi sesuai jadwal pendaftaran." />
            </div>
        @endif

        <section class="mt-12" aria-labelledby="period-list-heading">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Informasi resmi</p>
                    <h2 id="period-list-heading" class="mt-2 text-2xl font-bold text-slate-950">Periode penerimaan</h2>
                </div>
                <a href="{{ route('contact.create') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Tanya informasi</a>
            </div>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                @forelse ($periods as $period)
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-slate-950">{{ $period['name'] }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $period['opens_at_label'] }} — {{ $period['closes_at_label'] }}</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $period['status'] === 'open' ? 'Dibuka' : 'Ditutup' }}</span>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-slate-600">{{ $period['description'] }}</p>
                        @if ($period['instructions'])
                            <p class="mt-3 text-sm leading-6 text-slate-600">{{ $period['instructions'] }}</p>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-slate-600">Belum ada informasi periode yang dipublikasikan.</p>
                @endforelse
            </div>
        </section>

        <aside class="mt-12 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
            <strong>Catatan privasi:</strong> jangan kirim dokumen identitas melalui formulir kontak. Dokumen PPDB hanya boleh diunggah melalui alur aplikasi dan disimpan pada penyimpanan privat.
        </aside>
    </div>
@endsection
