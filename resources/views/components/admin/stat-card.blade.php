@props([
    'label',
    'value',
    'hint' => null,
    'tone' => 'default',
])

@php
    $tones = [
        'default' => 'border-slate-200 bg-white',
        'accent' => 'border-cyan-200 bg-cyan-50/60',
        'warning' => 'border-amber-200 bg-amber-50/70',
        'muted' => 'border-slate-200 bg-slate-50',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl border p-5 shadow-sm '.$tones[$tone]]) }}>
    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
    <p class="mt-2 text-2xl font-bold tracking-tight text-slate-950">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $hint }}</p>
    @endif
    {{ $slot }}
</div>
