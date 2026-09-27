@props([
    'label' => 'Data yang tidak ditemukan',
    'message' => 'Belum ada data yang memenuhi filter ini.',
    'actionLabel' => null,
    'actionRoute' => null,
    'actionParams' => [],
])

<div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-cyan-50 text-xl text-cyan-700" aria-hidden="true">✦</div>
    <h2 class="mt-4 text-lg font-semibold text-slate-950">{{ $label }}</h2>
    <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-600">{{ $message }}</p>
    <div class="mt-5 flex flex-wrap justify-center gap-2">
        @if ($actionRoute !== null && ($slot->isEmpty() || true))
            <a href="{{ route($actionRoute, $actionParams) }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">{{ $actionLabel ?? 'Tambah data' }}</a>
        @endif
        {{ $slot }}
    </div>
</div>
