@props([
    'name',
    'label',
    'value' => null,
    'rows' => 4,
    'required' => false,
    'hint' => null,
    'placeholder' => null,
    'maxlength' => null,
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

{{-- Textarea with its label; see x-form.text for why the label lives here. --}}
<div>
    <label for="{{ $fieldId }}" class="block text-sm font-semibold text-slate-800">
        {{ $label }}@if ($required) <span class="text-rose-700">*</span>@endif
    </label>

    <textarea
        id="{{ $fieldId }}"
        name="{{ $fieldName }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($maxlength) maxlength="{{ $maxlength }}" @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @enderror
        {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm leading-6 text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-600/30']) }}
    >{{ old($name, $value) }}</textarea>

    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif

    <x-form.field-error :name="$name" />
</div>
