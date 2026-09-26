@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
    'placeholder' => null,
    'step' => null,
    'min' => null,
    'max' => null,
    'maxlength' => null,
    'autocomplete' => null,
    'readonly' => false,
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


<input
    id="{{ $fieldId }}"
    name="{{ $fieldName }}"
    type="{{ $type }}"
    value="{{ old($name, $value) }}"
    @if ($required) required @endif
    @if ($placeholder) placeholder="{{ $placeholder }}" @endif
    @if ($step) step="{{ $step }}" @endif
    @if ($min !== null) min="{{ $min }}" @endif
    @if ($max !== null) max="{{ $max }}" @endif
    @if ($maxlength) maxlength="{{ $maxlength }}" @endif
    @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
    @if ($readonly) readonly @endif
    @error($name) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @enderror
    {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-600/30 disabled:bg-slate-100']) }}
/>

@if ($hint)
    <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
@endif

<x-form.field-error :name="$name" />
