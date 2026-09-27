@props([
    'name' => 'body',
    'value' => '',
    'label' => 'Isi berita',
    'placeholder' => 'Tulis isi berita di sini…',
    'uploadUrl' => null,
    'maxUploadBytes' => 5242880,
    'rows' => 14,
    'help' => 'Gunakan bilah alat di atas penyunting: format paragraf, tebal, miring, garis bawah, coret, tautan, daftar bertingkat, kutipan, garis pemisah, perataan, dan gambar. Gambar diunggah ke server, tidak pernah disimpan sebagai base64.',
    'helpWhenImagesDisabled' => 'Gunakan bilah alat di atas penyunting: format paragraf, tebal, miring, garis bawah, coret, tautan, daftar bertingkat, kutipan, garis pemisah, dan perataan.',
])

{{--
    Rich text editor for long form HTML content.

    Progressive enhancement: the textarea below is the submitted field and is
    only hidden once resources/js/editor/rich-text-editor.js has attached a
    CKEditor 5 instance to [data-rte-editor]. Without JavaScript the operator
    still gets a working, labelled textarea, and the server sanitizes whatever
    arrives either way.

    The outer [data-rte-field] wrapper exists because the textarea has to stay
    outside the hidden mount root: the script pairs the two through that wrapper
    instead of by descending into the root.

    Passing no $uploadUrl mounts the editor without the image buttons. That is
    the honest configuration for a field with no image pipeline behind it: the
    school profile keeps its own logo and hero file inputs, and offering an
    editor image button there would promise an upload route that does not exist.
--}}
<div data-rte-field>
    <label for="{{ $name }}" class="block text-sm font-semibold text-slate-800">
        {{ $label }}
    </label>

    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        data-rte-input
        rows="{{ $rows }}"
        class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm leading-7"
    >{{ $value }}</textarea>

    <div
        data-rich-text-editor
        @if ($uploadUrl) data-upload-url="{{ $uploadUrl }}" @endif
        data-max-upload-bytes="{{ $maxUploadBytes }}"
        data-placeholder="{{ $placeholder }}"
        class="mt-2 overflow-hidden rounded-xl border border-slate-300 bg-white"
        hidden
    >
        {{-- CKEditor injects its own toolbar and editing surface here. --}}
        <p class="rte-loading px-4 py-6 text-sm text-slate-500">Menyiapkan penyunting…</p>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-3 py-2">
            <p data-rte-count class="text-xs text-slate-500">0 kata · 0 karakter</p>
            @if ($uploadUrl)
                <p class="text-xs text-slate-500">Maks. 5 MB per gambar · JPG, PNG, WEBP</p>
            @endif
        </div>

        <p data-rte-status role="status" aria-live="polite" class="border-t border-slate-200 px-3 py-2 text-sm text-rose-700 empty:hidden"></p>
    </div>

    <p class="mt-1 text-xs text-slate-500">{{ $uploadUrl ? $help : $helpWhenImagesDisabled }}</p>
    <x-form.field-error :name="$name" />
</div>
