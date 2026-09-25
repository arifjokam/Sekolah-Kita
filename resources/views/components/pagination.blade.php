@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="mt-10 flex items-center justify-between gap-4 border-t border-slate-200 pt-5" aria-label="Navigasi halaman">
        <div class="text-sm text-slate-600">
            Halaman <span class="font-semibold text-slate-950">{{ $paginator->currentPage() }}</span> dari <span class="font-semibold text-slate-950">{{ $paginator->lastPage() }}</span>
        </div>
        <div class="flex items-center gap-2">
            @if ($paginator->onFirstPage())
                <span class="cursor-not-allowed rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-400" aria-disabled="true">Sebelumnya</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-cyan-400 hover:text-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Sebelumnya</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-cyan-400 hover:text-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Berikutnya</a>
            @else
                <span class="cursor-not-allowed rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-400" aria-disabled="true">Berikutnya</span>
            @endif
        </div>
    </nav>
@endif
