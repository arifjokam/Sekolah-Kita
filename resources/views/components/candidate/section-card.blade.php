@props([
    'label',
    'route',
    'ratio' => 0,
    'complete' => false,
    'missing' => [],
    'pattern' => null,
])

{{--
    One section of the applicant application, with its own completion state.

    An incomplete section always names what is missing: a progress bar on its own
    tells a candidate nothing about what to do next, and "never show a blank
    dashboard" means the gap has to be legible.
--}}
@php $active = $pattern !== null && request()->routeIs($pattern); @endphp

<article {{ $attributes->merge(['class' => 'rounded-2xl border p-5 shadow-sm transition '.(function () use ($active, $complete): string {
    if ($active) {
        return 'border-cyan-300 bg-cyan-50/70';
    }

    return $complete ? 'border-emerald-200 bg-white' : 'border-amber-200 bg-white';
})()]) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="font-semibold text-slate-950">{{ $label }}</h3>
            <p class="mt-1 text-xs font-semibold uppercase tracking-wide {{ $complete ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ $complete ? 'Lengkap' : $ratio.'% lengkap' }}
            </p>
        </div>
        @if (Route::has($route))
            <a
                href="{{ route($route) }}"
                class="shrink-0 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-cyan-400 hover:text-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600"
            >{{ $active ? 'Buka' : 'Lengkapi' }}</a>
        @endif
    </div>

    <div class="mt-4 h-2 w-full overflow-hidden rounded-full bg-slate-200" role="presentation">
        <div class="h-full rounded-full {{ $complete ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ max(0, min(100, $ratio)) }}%"></div>
    </div>

    @if ($missing !== [])
        <ul class="mt-4 space-y-1 text-sm text-slate-700">
            @foreach (array_slice($missing, 0, 6) as $item)
                <li class="flex gap-2">
                    <span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-amber-500" aria-hidden="true"></span>
                    <span>{{ $item }}</span>
                </li>
            @endforeach
            @if (count($missing) > 6)
                <li class="text-xs text-slate-500">dan {{ count($missing) - 6 }} butir lain.</li>
            @endif
        </ul>
    @endif
</article>
