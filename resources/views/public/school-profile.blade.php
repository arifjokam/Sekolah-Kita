@extends('layouts.public')

@section('title', ($profile['name'] ?? 'Profil Sekolah').' · Profil Sekolah')

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <x-public.breadcrumbs :items="['Profil sekolah' => route('school-profile.show')]" />

        @if ($profile === null)
            <x-empty-state title="Profil belum tersedia" message="Administrator sekolah belum mempublikkan informasi profil." />
        @else
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="grid lg:grid-cols-[0.9fr_1.1fr]">
                    <div class="min-h-64 bg-slate-900">
                        @if (($profile['hero']['url'] ?? null))
                            <img src="{{ $profile['hero']['url'] }}" alt="Gambar utama {{ $profile['name'] }}" class="h-full min-h-64 w-full object-cover" width="{{ $profile['hero']['width'] }}" height="{{ $profile['hero']['height'] }}">
                        @else
                            <div class="flex h-full min-h-64 items-center justify-center bg-linear-to-br from-cyan-800 to-slate-950 p-10 text-center text-white"><p class="text-2xl font-bold">{{ $profile['name'] }}</p></div>
                        @endif
                    </div>
                    <div class="p-7 sm:p-10 lg:p-14">
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-700">Profil sekolah</p>
                        <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">{{ $profile['name'] }}</h1>
                        @if ($profile['about']['text'] ?? null)
                            <x-public.profile-copy :copy="$profile['about']" class="mt-5" />
                        @endif
                        <dl class="mt-8 grid gap-5 sm:grid-cols-2">
                            @if ($profile['address_line'] || $profile['city'])
                                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alamat</dt><dd class="mt-1 text-sm leading-6 text-slate-700">{{ $profile['address_line'] }}@if ($profile['city']), {{ $profile['city'] }}@endif @if ($profile['postal_code']) {{ $profile['postal_code'] }}@endif</dd></div>
                            @endif
                            @if ($profile['public_phone'])
                                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telepon</dt><dd class="mt-1 text-sm text-slate-700"><a href="tel:{{ $profile['public_phone'] }}" class="hover:text-cyan-700">{{ $profile['public_phone'] }}</a></dd></div>
                            @endif
                            @if ($profile['public_email'])
                                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt><dd class="mt-1 break-all text-sm text-slate-700"><a href="mailto:{{ $profile['public_email'] }}" class="hover:text-cyan-700">{{ $profile['public_email'] }}</a></dd></div>
                            @endif
                        </dl>
                    </div>
                </div>
            </section>

            <div class="mt-8 grid gap-8 lg:grid-cols-2">
                @foreach (['Visi' => $profile['vision'], 'Misi' => $profile['mission']] as $heading => $content)
                    @if ($content['text'] ?? null)
                        <section class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
                            <h2 class="text-xl font-bold text-slate-950">{{ $heading }}</h2>
                            <x-public.profile-copy :copy="$content" class="mt-4" />
                        </section>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
@endsection
