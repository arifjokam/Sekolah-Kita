@extends('layouts.app')

@section('title', 'Prestasi · Admin')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Privacy-safe publication</p><h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Prestasi</h1><p class="mt-2 max-w-3xl text-sm text-slate-600">Informasi identitas penerima tidak disimpan pada modul ini. Gunakan deskripsi anonymity pada tingkat sekolah.</p></div>
        <a href="{{ route('admin.achievements.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">+ Prestasi baru</a>
    </div>
    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Prestasi</th><th class="px-5 py-3">Kategori</th><th class="px-5 py-3">Tanggal</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse ($achievements as $achievement)
                <tr><td class="px-5 py-4"><p class="font-semibold text-slate-950">{{ $achievement->title }}</p><p class="mt-1 text-xs text-slate-500">/{{ $achievement->slug }}</p></td><td class="px-5 py-4 text-slate-600">{{ $achievement->category }} · {{ $achievement->level }}</td><td class="px-5 py-4 text-slate-600">{{ $achievement->achievement_date->format('d/m/Y') }}</td><td class="px-5 py-4"><x-admin.status-badge :status="$achievement->status" /></td><td class="px-5 py-4"><div class="flex flex-wrap justify-end gap-2"><a href="{{ route('admin.achievements.edit', $achievement) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>@if ($achievement->status !== \App\Enums\PublicationStatus::Published)<form method="POST" action="{{ route('admin.achievements.publish', $achievement) }}">@csrf<button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Terbitkan</button></form>@else<form method="POST" action="{{ route('admin.achievements.unpublish', $achievement) }}">@csrf<button class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-50">Tarik</button></form>@endif @if($achievement->status !== \App\Enums\PublicationStatus::Archived)<form method="POST" action="{{ route('admin.achievements.archive', $achievement) }}">@csrf<button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Arsipkan</button></form>@endif</div></td></tr>
            @empty
                <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-slate-600">Belum ada prestasi.</td></tr>
            @endforelse
        </tbody></table></div>
    </div>
    <x-pagination :paginator="$achievements" />
@endsection
