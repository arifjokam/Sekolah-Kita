@props([
    'title',
    'message',
])

<div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-cyan-50 text-xl text-cyan-700" aria-hidden="true">✦</div>
    <h2 class="mt-4 text-lg font-semibold text-slate-950">{{ $title }}</h2>
    <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-600">{{ $message }}</p>
    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
