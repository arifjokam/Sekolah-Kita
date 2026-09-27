@props(['html'])

{{--
    Renders a sanitized rich text article body.

    The value is never authored by an administrator directly: RichTextSanitizer
    has already reduced it to the editor allowlist on write, and the public
    reader sanitizes it again before it reaches this component. Only rich text
    bodies are passed here; a legacy plain text body keeps using x-public.prose.
--}}
<div {{ $attributes->merge(['class' => 'rich-text text-base leading-8 text-slate-700']) }}>{!! $html !!}</div>
