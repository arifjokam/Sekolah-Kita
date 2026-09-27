@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'actions' => null,
])

<header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">{{ $title }}</h1>
        @if ($description)
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ $description }}</p>
        @endif
    </div>

    @if (trim($actions ?? '') !== '')
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endif
</header>
