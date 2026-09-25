@extends('layouts.app')

@section('title', 'Berita · Admin')

@section('content')
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Publication workflow</p><h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Berita</h1><p class="mt-2 text-sm text-slate-600">Hanya artikel dengan status <span class="font-semibold">Terbit</span> yang tampil di situs publik.</p></div>
        <a href="{{ route('admin.news.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-cyan-700 px-4 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">+ Berita baru</a>
    </div>

    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Judul</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Diperbarui</th><th class="px-5 py-3 text-right">Aksi</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @forelse ($articles as $article)
                    <tr>
                        <td class="px-5 py-4"><p class="font-semibold text-slate-950">{{ $article->title }}</p><p class="mt-1 text-xs text-slate-500">/{{ $article->slug }}</p></td>
                        <td class="px-5 py-4"><x-admin.status-badge :status="$article->status" /></td>
                        <td class="px-5 py-4 text-slate-600">{{ $article->updated_at->format('d/m/Y H:i') }}</td>
                        <td class="px-5 py-4"><div class="flex flex-wrap justify-end gap-2">
                            <a href="{{ route('admin.news.edit', $article) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                            @if ($article->status !== \App\Enums\PublicationStatus::Published)
                                <form method="POST" action="{{ route('admin.news.publish', $article) }}">@csrf<button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Terbitkan</button></form>
                            @else
                                <form method="POST" action="{{ route('admin.news.unpublish', $article) }}">@csrf<button class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-50">Tarik</button></form>
                            @endif
                            @if ($article->status !== \App\Enums\PublicationStatus::Archived)
                                <form method="POST" action="{{ route('admin.news.archive', $article) }}">@csrf<button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Arsipkan</button></form>
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-12 text-center text-sm text-slate-600">Belum ada berita.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <x-pagination :paginator="$articles" />
@endsection
