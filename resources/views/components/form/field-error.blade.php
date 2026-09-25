@props(['name'])

@error($name)
    <p id="{{ $name }}-error" class="mt-1 text-sm text-rose-700">{{ $message }}</p>
@enderror
