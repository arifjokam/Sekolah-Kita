@props(['copy'])

{{--
    Renders one of the school profile's long-form fields.

    $copy is the array PublicContentReader returns: the value plus whether it
    survived sanitization as rich text. Plain copy keeps the existing escaped
    rendering with preserved line breaks, and only rich text is emitted as
    markup, which is why the two cases cannot share one element.
--}}
@php
    $text = is_array($copy) ? ($copy['text'] ?? '') : (string) $copy;
    $isHtml = is_array($copy) && ($copy['is_html'] ?? false);
@endphp

@if ($isHtml)
    <div {{ $attributes->merge(['class' => 'rich-text text-base leading-8 text-slate-700']) }}>{!! $text !!}</div>
@elseif ($text !== '')
    <div {{ $attributes->merge(['class' => 'whitespace-pre-line text-base leading-8 text-slate-700']) }}>{{ $text }}</div>
@endif
