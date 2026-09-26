@extends('layouts.public')

@section('title', 'Buat akun pendaftar PPDB · Sekolah Kita')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:py-14">
        <nav aria-label="Remah roti" class="mb-6">
            <ol class="flex flex-wrap items-center gap-1 text-sm text-slate-500">
                <li><a href="{{ route('home') }}" class="font-medium hover:text-cyan-700">Beranda</a></li>
                <li aria-hidden="true" class="text-slate-300">/</li>
                <li><a href="{{ route('ppdb.index') }}" class="font-medium hover:text-cyan-700">PPDB</a></li>
                <li aria-hidden="true" class="text-slate-300">/</li>
                <li class="font-medium text-slate-700" aria-current="page">Buat akun pendaftar</li>
            </ol>
        </nav>

        <header class="max-w-3xl">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-700">PPDB {{ $period->name }}</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">Buat akun pendaftar</h1>
            <p class="mt-4 text-base leading-8 text-slate-600">
                Lengkapi pendaftaran secara bertahap. Akun ini hanya untuk Anda sebagai pemohon; akun siswa internal
                dibuat terpisah oleh sekolah setelah pengajuan Anda diterima.
            </p>
        </header>

        <x-form.error-summary title="Periksa kembali isian formulir." />

        <form method="POST" action="{{ route('ppdb.portal.register.store') }}" class="mt-8 space-y-6">
            @csrf

            <div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <x-form.text
                    name="name"
                    label="Nama lengkap"
                    required
                    autocomplete="name"
                    maxlength="150"
                    :value="old('name')"
                    hint="Sesuai akta kelahiran."
                />
                <x-form.text
                    name="email"
                    type="email"
                    label="Alamat email"
                    required
                    autocomplete="email"
                    maxlength="150"
                    :value="old('email')"
                    hint="Dipakai untuk verifikasi akun dan pemberitahuan status pendaftaran."
                />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.text
                        name="phone"
                        type="tel"
                        label="Nomor telepon / WhatsApp"
                        required
                        autocomplete="tel"
                        maxlength="30"
                        :value="old('phone')"
                    />
                    <x-form.text
                        name="whatsapp"
                        type="tel"
                        label="Nomor WhatsApp (opsional)"
                        autocomplete="tel"
                        maxlength="30"
                        :value="old('whatsapp')"
                        hint="Bila berbeda dari nomor telepon."
                    />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.text
                        name="password"
                        type="password"
                        label="Kata sandi"
                        required
                        autocomplete="new-password"
                        hint="Minimal 12 karakter."
                    />
                    <x-form.text
                        name="password_confirmation"
                        type="password"
                        label="Ulangi kata sandi"
                        required
                        autocomplete="new-password"
                    />
                </div>

                {{-- Honeypot. Hidden from users and from assistive technology, so no real applicant fills it. --}}
                <div class="hidden" aria-hidden="true">
                    <label for="website">Website</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off" value="">
                </div>
            </div>

            <label class="flex gap-3 rounded-2xl border border-slate-200 bg-white p-5 text-sm leading-6 text-slate-700 shadow-sm">
                <input type="checkbox" name="consent" value="1" @checked(old('consent')) required
                       class="mt-1 h-4 w-4 rounded border-slate-300 text-cyan-700 focus:ring-cyan-600">
                <span>
                    Saya menyatakan data yang diisi benar, dan menyetujui sekolah memproses data ini untuk keperluan
                    seleksi penerimaan siswa baru. Data hanya digunakan untuk proses PPDB.
                </span>
            </label>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('ppdb.index') }}"
                   class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Batal</a>
                <button type="submit"
                        class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                    Buat akun dan lanjutkan
                </button>
            </div>
        </form>

        <p class="mt-6 text-sm text-slate-600">
            Sudah punya akun? <a href="{{ route('login') }}" class="font-semibold text-cyan-700 hover:text-cyan-900">Masuk di sini</a>.
            Atau gunakan <a href="{{ route('ppdb.application.create') }}" class="font-semibold text-cyan-700 hover:text-cyan-900">formulir pendaftaran langsung</a> tanpa akun.
        </p>
    </div>
@endsection
