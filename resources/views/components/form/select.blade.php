@props([
    'name',
    'label',
    'options' => [],
    'value' => null,
    'placeholder' => 'Pilih salah satu',
    'required' => false,
    'hint' => null,
    'blankLabel' => null,
])

@php
    /*
     * Nested fields are submitted with PHP array syntax rather than dot
     * notation. The two are equivalent to Laravel, but the built-in PHP
     * development server rewrites dots in form field names to underscores, so
     * a dotted name silently arrives as a flat key there and the rule never
     * matches. Array syntax survives every SAPI, including `php artisan serve`.
     */
    $isNested = str_contains($name, '.');
    $fieldName = $isNested
        ? preg_replace('/\.([^.[]+)(?![^[]*\])/', '[$1]', $name)
        : $name;
    $fieldId = str_replace('.', '-', $name);
@endphp

{{--
    Select control.

    $options is a map of value => label. The selected value comes from the old
    input first, then from the supplied model value, so a failed validation
    round trip keeps the operator's choice.
--}}
<select
    id="{{ $fieldId }}"
    name="{{ $fieldName }}"
    @if ($required) required @endif
    @error($name) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @enderror
    {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm text-slate-900 shadow-sm transition focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-600/30 disabled:bg-slate-100']) }}
>
    <option value="">{{ $blankLabel ?? $placeholder }}</option>
    @foreach ($options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
    @endforeach
</select>

@if ($hint)
    <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
@endif

<x-form.field-error :name="$name" />
