@props(['text'])

<div {{ $attributes->merge(['class' => 'whitespace-pre-line text-base leading-8 text-slate-700']) }}>{{ $text }}</div>
