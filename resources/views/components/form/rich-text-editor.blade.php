@props([
    'name' => 'body',
    'value' => '',
    'label' => 'Isi berita',
    'placeholder' => 'Tulis isi berita di sini…',
    'uploadUrl' => '#',
    'maxUploadBytes' => 5242880,
    'help' => 'Format: paragraf, judul, tebal, miring, garis bawah, coret, tautan, daftar, kutipan, garis pemisah, perataan, dan gambar. Gambar diunggah ke server, tidak disimpan sebagai base64.',
])

{{--
    Reusable article rich text editor.

    The textarea stays in the form as the submitted field, so the form keeps
    working without JavaScript. resources/js/app.js upgrades it in place and
    hides the textarea, and the server sanitizes the result before storing it.
--}}
<div>
    <label for="{{ $name }}" class="block text-sm font-semibold text-slate-800">
        {{ $label }} <span class="text-rose-700">*</span>
    </label>

    <textarea id="{{ $name }}" name="{{ $name }}" data-rte-input rows="14" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm leading-7">{{ $value }}</textarea>

    <div
        data-rich-text-editor
        data-upload-url="{{ $uploadUrl }}"
        data-max-upload-bytes="{{ $maxUploadBytes }}"
        data-placeholder="{{ $placeholder }}"
        class="mt-2 overflow-hidden rounded-xl border border-slate-300 bg-white"
        hidden
    >
        <div data-rte-toolbar role="toolbar" aria-label="Alat penyuntingan isi berita" class="rte-toolbar flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-slate-200 bg-slate-50 px-3 py-2">
            <div class="flex items-center gap-1" role="group" aria-label="Gaya paragraf">
                <button type="button" data-rte-action="paragraph" title="Paragraf" aria-label="Paragraf" aria-pressed="false" class="rte-toolbar-button flex h-9 min-w-9 items-center justify-center px-2 text-sm font-semibold">P</button>
                <button type="button" data-rte-action="heading2" title="Judul level 2" aria-label="Judul level 2" aria-pressed="false" class="rte-toolbar-button flex h-9 min-w-9 items-center justify-center px-2 text-sm font-semibold">H2</button>
                <button type="button" data-rte-action="heading3" title="Judul level 3" aria-label="Judul level 3" aria-pressed="false" class="rte-toolbar-button flex h-9 min-w-9 items-center justify-center px-2 text-sm font-semibold">H3</button>
                <button type="button" data-rte-action="heading4" title="Judul level 4" aria-label="Judul level 4" aria-pressed="false" class="rte-toolbar-button flex h-9 min-w-9 items-center justify-center px-2 text-sm font-semibold">H4</button>
            </div>

            <div class="flex items-center gap-1" role="group" aria-label="Format teks">
                <button type="button" data-rte-action="bold" title="Tebal (Ctrl+B)" aria-label="Tebal" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="bold" /></button>
                <button type="button" data-rte-action="italic" title="Miring (Ctrl+I)" aria-label="Miring" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="italic" /></button>
                <button type="button" data-rte-action="underline" title="Garis bawah (Ctrl+U)" aria-label="Garis bawah" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="underline" /></button>
                <button type="button" data-rte-action="strike" title="Coret" aria-label="Coret" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="strike" /></button>
                <button type="button" data-rte-action="clearFormatting" title="Hapus format" aria-label="Hapus format" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="close" /></button>
            </div>

            <div class="flex items-center gap-1" role="group" aria-label="Daftar">
                <button type="button" data-rte-action="bulletList" title="Daftar poin" aria-label="Daftar poin" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="bulletList" /></button>
                <button type="button" data-rte-action="orderedList" title="Daftar angka" aria-label="Daftar angka" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="orderedList" /></button>
            </div>

            <div class="flex items-center gap-1" role="group" aria-label="Blok">
                <button type="button" data-rte-action="blockquote" title="Kutipan" aria-label="Kutipan" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="blockquote" /></button>
                <button type="button" data-rte-action="horizontalRule" title="Garis pemisah" aria-label="Garis pemisah" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="horizontalRule" /></button>
            </div>

            <div class="flex items-center gap-1" role="group" aria-label="Perataan teks">
                <button type="button" data-rte-action="alignLeft" title="Rata kiri" aria-label="Rata kiri" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="alignLeft" /></button>
                <button type="button" data-rte-action="alignCenter" title="Rata tengah" aria-label="Rata tengah" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="alignCenter" /></button>
                <button type="button" data-rte-action="alignRight" title="Rata kanan" aria-label="Rata kanan" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="alignRight" /></button>
                <button type="button" data-rte-action="alignJustify" title="Rata kiri kanan" aria-label="Rata kiri kanan" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="alignJustify" /></button>
            </div>

            <div class="flex items-center gap-1" role="group" aria-label="Gambar">
                <button type="button" data-rte-action="image" title="Sisipkan gambar (unggah ke server)" aria-label="Sisipkan gambar" aria-pressed="false" data-rte-requires-idle class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="image" /></button>
                <button type="button" data-rte-action="imageAlt" title="Teks alternatif gambar" aria-label="Teks alternatif gambar" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="imageAlt" /></button>
                <button type="button" data-rte-action="imageWidth" data-rte-value="50" title="Lebar 50 persen" aria-label="Lebar 50 persen" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="imageHalfWidth" /></button>
                <button type="button" data-rte-action="imageWidth" data-rte-value="100" title="Lebar penuh" aria-label="Lebar penuh" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="imageFullWidth" /></button>
            </div>

            <div class="flex items-center gap-1" role="group" aria-label="Tautan dan riwayat">
                <button type="button" data-rte-action="link" title="Tautan" aria-label="Tautan" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="link" /></button>
                <button type="button" data-rte-action="undo" title="Urungkan (Ctrl+Z)" aria-label="Urungkan" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="undo" /></button>
                <button type="button" data-rte-action="redo" title="Ulangi (Ctrl+Y)" aria-label="Ulangi" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="redo" /></button>
            </div>
        </div>

        {{-- The picker behind the toolbar button. Chosen files are uploaded to the
             server; they are never inlined into the markup. --}}
        <input data-rte-file type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" tabindex="-1" aria-hidden="true">

        <div data-rte-progress class="h-1 w-full bg-cyan-100" hidden>
            <div data-rte-progress-bar class="h-1 w-0 bg-cyan-600 transition-[width] duration-150"></div>
        </div>

        <div data-rte-editor class="rte-editor"></div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-3 py-2">
            <p data-rte-count class="text-xs text-slate-500">0 kata · 0 karakter</p>
            <div class="flex items-center gap-3">
                <p class="text-xs text-slate-500">Maks. 5 MB per gambar · JPG, PNG, WEBP</p>
                <button type="button" data-rte-action="fullscreen" title="Layar penuh" aria-label="Layar penuh" aria-pressed="false" class="rte-toolbar-button flex h-9 w-9 items-center justify-center"><x-form.editor-icon name="fullscreen" /></button>
            </div>
        </div>

        <div data-rte-link data-mode="link" class="border-t border-slate-200 bg-white p-3" hidden>
            <form data-rte-link-form class="flex flex-wrap items-end gap-3">
                <div class="min-w-0 flex-1">
                    <label for="{{ $name }}-link" class="block text-xs font-semibold text-slate-700">Alamat tautan</label>
                    <input id="{{ $name }}-link" type="url" data-rte-link-href inputmode="url" placeholder="https://sekolah.sch.id/halaman" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Hanya http://, https://, mailto:, atau tel:.</p>
                </div>
                <div class="min-w-0 flex-1">
                    <label for="{{ $name }}-alt" class="block text-xs font-semibold text-slate-700">Teks alternatif gambar</label>
                    <input id="{{ $name }}-alt" type="text" data-rte-image-alt maxlength="200" placeholder="Deskripsi singkat gambar" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="min-h-10 rounded-lg bg-cyan-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Terapkan</button>
                    <button type="button" data-rte-remove-link class="min-h-10 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Hapus tautan</button>
                    <button type="button" data-rte-close-panel class="flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 text-slate-600 transition hover:bg-slate-50" title="Tutup" aria-label="Tutup"><x-form.editor-icon name="close" /></button>
                </div>
            </form>
        </div>
        <p data-rte-status role="status" aria-live="polite" class="border-t border-slate-200 px-3 py-2 text-sm text-rose-700 empty:hidden"></p>
    </div>

    <p class="mt-1 text-xs text-slate-500">{{ $help }}</p>
    <x-form.field-error :name="$name" />
</div>
