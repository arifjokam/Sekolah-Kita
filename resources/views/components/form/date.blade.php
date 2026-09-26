@props([
    'name',
    'label',
    'value' => null,
    'required' => false,
    'hint' => null,
    'min' => null,
    'max' => null,
])
@php
    /*
     * Nested fields are submitted with PHP array syntax rather than dot
     * notation. The two are equivalent to Laravel, but the built-in PHP
     * development server rewrites dots in form field names to underscores, so
     * a dotted name silently arrives as a flat key there and the rule never
     * matches. Array syntax survives every SAPI, including `php artisan serve`.
     */
    $fieldName = str_contains($name, '.')
        ? preg_replace('/\.([^.[]+)(?![^[]*\])/', '[$1]', $name)
        : $name;
    $fieldId = str_replace('.', '-', $name);
@endphp


{{-- Native date input; the browser submits an ISO date, which is what the domain stores. --}}
<input
    id="{{ $fieldId }}"
    name="{{ $fieldName }}"
    type="date"
    value="{{ old($name, $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value) }}"
    @if ($required) required @endif
    @if ($min) min="{{ $min }}" @endif
    @if ($max) max="{{ $max }}" @endif
    @error($name) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @enderror
    {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-600/30']) }}
/>

@if ($hint)
    <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
@endif

<x-form.field-error :name="$name" />
