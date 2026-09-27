@props([
    'value' => 'slate',
    'label' => null,
    'size' => 'md',
])

{{--
    The applicant-facing status chip.

    `value` is a tone key rather than a raw status string, so a new workflow state
    cannot leak an untranslated English enum name onto a parent's screen. The
    label is required from the caller, which is where the mapping lives.
--}}
@php
    $tones = [
        'emerald' => 'bg-emerald-100 text-emerald-800',
        'cyan' => 'bg-cyan-100 text-cyan-800',
        'amber' => 'bg-amber-100 text-amber-800',
        'rose' => 'bg-rose-100 text-rose-800',
        'violet' => 'bg-violet-100 text-violet-800',
        'slate' => 'bg-slate-200 text-slate-700',
    ];

    $sizes = [
        'sm' => 'px-2 py-0.5 text-xs',
        'md' => 'px-2.5 py-1 text-xs',
        'lg' => 'px-3 py-1.5 text-sm',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex rounded-full font-semibold '.($tones[$value] ?? $tones['slate']).' '.($sizes[$size] ?? $sizes['md'])]) }}>
    {{ $label }}
</span>
