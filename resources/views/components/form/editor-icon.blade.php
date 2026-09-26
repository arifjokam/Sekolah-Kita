@props(['name'])

{{--
    One stroke based icon set for the rich text toolbar.

    Every icon is drawn on the same 20x20 grid with the same stroke weight, and
    the colour is inherited from the button, so the active and disabled states
    are carried by text colour alone.
--}}
@php
    $icons = [
        'bold' => 'M7 5h3.5a2.5 2.5 0 0 1 0 5H7zm0 5h4a2.5 2.5 0 0 1 0 5H7z',
        'italic' => 'M11 5h5M9 15h5M13.5 5l-3 10',
        'underline' => 'M6.5 4.5v4.8a3.5 3.5 0 0 0 7 0V4.5M5.5 17h9',
        'strike' => 'M5 10h10M13 6.3A2.7 2.7 0 0 0 10.6 4.2H9.4A2.4 2.4 0 0 0 7 6.6M7 13.7A2.7 2.7 0 0 0 9.4 15.8h1.2A2.4 2.4 0 0 0 13 13.4',
        'bulletList' => 'M8 6h9M8 10h9M8 14h9M4 6h.01M4 10h.01M4 14h.01',
        'orderedList' => 'M9 6h8M9 10h8M9 14h8M3.4 4.8h1V8M3 15.6a.9.9 0 1 1 1.6.5L3 17.4h1.8',
        'blockquote' => 'M6 6h3.5v3.5a3.5 3.5 0 0 1-3.5 3.5M12.5 6H16v3.5a3.5 3.5 0 0 1-3.5 3.5',
        'horizontalRule' => 'M3 10h14',
        'alignLeft' => 'M3 5h14M3 9h8M3 13h14M3 17h8',
        'alignCenter' => 'M3 5h14M6 9h8M3 13h14M6 17h8',
        'alignRight' => 'M3 5h14M9 9h8M3 13h14M9 17h8',
        'alignJustify' => 'M3 5h14M3 9h14M3 13h14M3 17h14',
        'image' => 'M3.5 4.5h13v11h-13zM3.5 12l3.6-3.6 3 3 2.4-2.4 4 4M7.6 8.4h.01',
        'imageAlt' => 'M3.5 4.5h13v11h-13zM6.5 9h7M6.5 12.2h4.5',
        'imageFullWidth' => 'M3 5h14v10H3z',
        'imageHalfWidth' => 'M6 5h8v10H6z',
        'link' => 'M8.6 11.4a3 3 0 0 0 4.2 0l2.2-2.2a3 3 0 0 0-4.2-4.2l-1 1M11.4 8.6a3 3 0 0 0-4.2 0L5 10.8a3 3 0 0 0 4.2 4.2l1-1',
        'undo' => 'M4.5 8.5h6.5a3.5 3.5 0 0 1 0 7H9M4.5 8.5 7.5 5.5M4.5 8.5l3 3',
        'redo' => 'M15.5 8.5H9a3.5 3.5 0 0 0 0 7h2M15.5 8.5 12.5 5.5M15.5 8.5l-3 3',
        'fullscreen' => 'M4 7.5V4h3.5M16 7.5V4h-3.5M4 12.5V16h3.5M16 12.5V16h-3.5',
        'close' => 'M5.5 5.5l9 9M14.5 5.5l-9 9',
    ];
@endphp

@if (isset($icons[$name]))
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" {{ $attributes->merge(['class' => 'h-5 w-5']) }}>
        <path d="{{ $icons[$name] }}" />
    </svg>
@endif
