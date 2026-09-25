@extends('layouts.public')

@section('title', $teacher['name'].' · Guru')

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
        <x-public.breadcrumbs :items="['Direktori guru' => route('teachers.index'), $teacher['name'] => route('teachers.show', $teacher['slug'])]" />
        <article class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm sm:p-10">
            <div class="flex flex-col items-center text-center sm:flex-row sm:items-start sm:text-left">
                @if (($teacher['photo']['url'] ?? null))
                    <img src="{{ $teacher['photo']['url'] }}" alt="Foto {{ $teacher['name'] }}" class="h-40 w-40 rounded-3xl object-cover" width="{{ $teacher['photo']['width'] }}" height="{{ $teacher['photo']['height'] }}">
                @else
                    <span class="flex h-40 w-40 items-center justify-center rounded-3xl bg-cyan-100 text-5xl font-bold text-cyan-800" aria-hidden="true">{{ str($teacher['name'])->substr(0, 1)->upper() }}</span>
                @endif
                <div class="mt-6 sm:ml-8 sm:mt-0">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-700">Profil profesional</p>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-950">{{ $teacher['name'] }}</h1>
                    @if ($teacher['position_title'])
                        <p class="mt-2 text-base font-medium text-cyan-700">{{ $teacher['position_title'] }}</p>
                    @endif
                </div>
            </div>
            @if ($teacher['biography'])
                <div class="mt-10 border-t border-slate-200 pt-8">
                    <h2 class="text-lg font-bold text-slate-950">Tentang guru</h2>
                    <x-public.prose :text="$teacher['biography']" class="mt-4" />
                </div>
            @endif
        </article>
        <div class="mt-8"><a href="{{ route('teachers.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">← Kembali ke direktori guru</a></div>
    </div>
@endsection
