@props(['name'])

{{--
    Inline field error.

    The id is derived with dashes because a dotted field name would produce an
    id containing a dot, which is awkward to reference from aria-describedby and
    invalid as a CSS selector.
--}}
@error($name)
    <p id="{{ str_replace('.', '-', $name) }}-error" class="mt-1 text-sm text-rose-700">{{ $message }}</p>
@enderror
