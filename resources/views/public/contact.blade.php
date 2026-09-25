@extends('layouts.public')

@section('title', 'Kontak · Sekolah Kita')
@section('description', 'Hubungi Sekolah Kita melalui formulir kontak yang aman.')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <x-public.breadcrumbs :items="['Kontak' => route('contact.create')]" />
        <div class="grid gap-10 lg:grid-cols-[0.8fr_1.2fr]">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-700">Mari terhubung</p>
                <h1 class="mt-3 text-4xl font-bold tracking-tight text-slate-950">Kontak kami</h1>
                <p class="mt-4 text-base leading-7 text-slate-600">Punya pertanyaan atau ingin mengetahui informasi sekolah? Kirim pesan melalui formulir berikut.</p>
                <div class="mt-8 grid gap-4 text-sm text-slate-700">
                    @if (($siteProfile['address_line'] ?? null) || ($siteProfile['city'] ?? null))
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="font-semibold text-slate-950">Alamat</p><address class="mt-2 not-italic leading-6">{{ $siteProfile['address_line'] }}@if ($siteProfile['city']), {{ $siteProfile['city'] }}@endif @if ($siteProfile['postal_code']) {{ $siteProfile['postal_code'] }}@endif</address></div>
                    @endif
                    @if (($siteProfile['public_phone'] ?? null))
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="font-semibold text-slate-950">Telepon</p><a href="tel:{{ $siteProfile['public_phone'] }}" class="mt-2 block text-cyan-700 hover:text-cyan-900">{{ $siteProfile['public_phone'] }}</a></div>
                    @endif
                    @if (($siteProfile['public_email'] ?? null))
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="font-semibold text-slate-950">Email</p><a href="mailto:{{ $siteProfile['public_email'] }}" class="mt-2 block break-all text-cyan-700 hover:text-cyan-900">{{ $siteProfile['public_email'] }}</a></div>
                    @endif
                </div>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                @if (session('contact_success'))
                    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800" role="status">{{ session('contact_success') }}</div>
                @endif
                <x-form.error-summary title="Pesan belum dapat dikirim." />
                <form method="POST" action="{{ route('contact.store') }}" class="grid gap-5" novalidate>
                    @csrf
                    <div class="hidden" aria-hidden="true">
                        <label for="website">Website</label>
                        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-semibold text-slate-800">Nama <span class="text-rose-700" aria-hidden="true">*</span></label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="150" autocomplete="name" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-950 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                        <x-form.field-error name="name" />
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="email" class="block text-sm font-semibold text-slate-800">Email</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="150" autocomplete="email" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-950 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                            <x-form.field-error name="email" />
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-semibold text-slate-800">Telepon</label>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-950 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                            <x-form.field-error name="phone" />
                        </div>
                    </div>
                    <p class="-mt-3 text-xs text-slate-500">Isi minimal salah satu: email atau telepon.</p>
                    <x-form.field-error name="contact" />
                    <div>
                        <label for="subject" class="block text-sm font-semibold text-slate-800">Subjek <span class="text-rose-700" aria-hidden="true">*</span></label>
                        <input id="subject" name="subject" type="text" value="{{ old('subject') }}" required maxlength="200" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-950 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100" @error('subject') aria-invalid="true" aria-describedby="subject-error" @enderror>
                        <x-form.field-error name="subject" />
                    </div>
                    <div>
                        <label for="message" class="block text-sm font-semibold text-slate-800">Pesan <span class="text-rose-700" aria-hidden="true">*</span></label>
                        <textarea id="message" name="message" rows="6" required maxlength="5000" class="mt-2 block w-full resize-y rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-950 shadow-sm outline-none transition focus:border-cyan-600 focus:ring-2 focus:ring-cyan-100" @error('message') aria-invalid="true" aria-describedby="message-error" @enderror>{{ old('message') }}</textarea>
                        <x-form.field-error name="message" />
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 focus-visible:ring-offset-2">Kirim pesan</button>
                        <p class="text-xs leading-5 text-slate-500">Data Anda hanya digunakan untuk merespons pertanyaan.</p>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
