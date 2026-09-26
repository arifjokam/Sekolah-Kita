import {
    Alignment,
    Autoformat,
    BlockQuote,
    Bold,
    ClassicEditor,
    Essentials,
    Fullscreen,
    Heading,
    HorizontalLine,
    Indent,
    Italic,
    Link,
    List,
    Paragraph,
    RemoveFormat,
    Strikethrough,
    Underline,
    Undo,
    Image,
    ImageTextAlternative,
    ImageUpload,
} from 'ckeditor5';
import 'ckeditor5/ckeditor5.css';
import { normalizeForStorage } from './normalize-rich-text';

const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;
const MAX_BODY_CHARACTERS = 20000;
const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const REQUIRED_PROTOCOLS = /^(https?:|mailto:|tel:)/i;

/**
 * Mirrors RichTextSanitizer::looksLikeRichText() on the server: a body without
 * block level markup is legacy plain text and has to keep rendering as text.
 */
const RICH_TEXT_PATTERN = /<\s*\/?(?:p|h[2-4]|ul|ol|li|blockquote|hr|img)\b/i;

function escapeHtml(value) {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Build the document the editor opens with.
 *
 * A legacy plain text body keeps its paragraph breaks as real paragraphs, so
 * the first save does not collapse the whole article into a single block.
 */
function initialDocument(html) {
    if (html === '' || RICH_TEXT_PATTERN.test(html)) {
        return html;
    }

    return html
        .split(/\n{2,}/)
        .map((block) => block.trim())
        .filter((block) => block !== '')
        .map((block) => `<p>${escapeHtml(block).replace(/\n/g, '<br>')}</p>`)
        .join('');
}

function csrfToken(root, form) {
    const meta = document.querySelector('meta[name="csrf-token"]');

    if (meta) {
        return meta.getAttribute('content') || '';
    }

    return form?.querySelector('input[name="_token"]')?.value || root.dataset.csrfToken || '';
}

function firstErrorMessage(payload) {
    if (!payload || !payload.errors) {
        return null;
    }

    for (const messages of Object.values(payload.errors)) {
        if (Array.isArray(messages) && messages.length > 0) {
            return messages[0];
        }
    }

    return null;
}

/**
 * Upload adapter that posts to the existing administrator image endpoint.
 *
 * The editor never inlines image data and never keeps a remote URL: the server
 * owns the object key, the MIME check, the size and dimension limits and the
 * canonical public URL. Nothing about private storage is exposed, because the
 * endpoint's JSON response only carries the public URL.
 */
class SecureImageUploadAdapter {
    constructor(loader, { endpoint, root, onStatus }) {
        this.loader = loader;
        this.endpoint = endpoint;
        this.root = root;
        this.onStatus = onStatus;
        this.aborted = false;
    }

    async upload() {
        this.aborted = false;
        this.setBusy(true);
        this.onStatus('Mengunggah gambar ke server…');

        const file = this.loader.file;

        try {
            if (!file) {
                throw new Error('Tidak ada berkas gambar.');
            }

            if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
                this.onStatus('Format gambar harus JPG, PNG, atau WEBP.');

                throw new Error('Unsupported image type.');
            }

            const maxBytes = Number(this.root.dataset.maxUploadBytes) || MAX_UPLOAD_BYTES;

            if (file.size > maxBytes) {
                this.onStatus(`Ukuran gambar melebihi ${Math.round(maxBytes / 1024 / 1024)} MB.`);

                throw new Error('Image too large.');
            }

            const body = new FormData();
            body.append('image', file, file.name || 'gambar');

            const response = await fetch(this.endpoint, {
                method: 'POST',
                body,
                headers: {
                    'X-CSRF-TOKEN': csrfToken(this.root, null),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });

            let payload = null;

            try {
                payload = await response.json();
            } catch (error) {
                payload = null;
            }

            if (!response.ok || !payload || typeof payload.url !== 'string' || payload.url === '') {
                const message = firstErrorMessage(payload) || 'Gagal mengunggah gambar.';

                this.onStatus(message);

                throw new Error(message);
            }

            this.onStatus('Gambar ditambahkan.');

            return { default: payload.url };
        } catch (error) {
            if (this.aborted) {
                this.onStatus('Pengunggahan gambar dibatalkan.');
            } else if (error instanceof TypeError) {
                this.onStatus('Gagal mengunggah gambar. Periksa koneksi lalu coba lagi.');
            }

            throw error;
        } finally {
            this.setBusy(false);
        }
    }

    abort() {
        this.aborted = true;
    }

    setBusy(busy) {
        this.root.dataset.uploading = String(busy);
        this.root.querySelectorAll('[data-rte-requires-idle]').forEach((element) => {
            element.disabled = busy;
        });
    }
}

/**
 * Mount a CKEditor 5 instance over a plain textarea.
 *
 * Contract with the backend:
 *  - the textarea stays the submitted field and is kept in sync on every
 *    change, so the form still works without JavaScript;
 *  - nothing is sent while typing and image data is never inlined;
 *  - the server sanitizes the markup again before storing it, so the editor is
 *    a convenience, not a trust boundary.
 *
 * @param {HTMLElement} root
 * @returns {Promise<{destroy: () => void, editor: object}>}
 */
export async function mountRichTextEditor(root) {
    const input = root.querySelector('[data-rte-input]');
    const statusRegion = root.querySelector('[data-rte-status]');
    const countRegion = root.querySelector('[data-rte-count]');
    const form = input?.form;

    if (!input) {
        throw new Error('The rich text editor root is missing its input element.');
    }

    const placeholder = root.dataset.placeholder || 'Tulis isi berita di sini…';
    const label = (input.labels?.[0]?.textContent || 'Isi berita').replace('*', '').trim();

    const surface = document.createElement('div');
    surface.className = 'rte-surface';
    surface.setAttribute('data-rte-editor', 'true');

    root.appendChild(surface);

    const editor = await ClassicEditor.create(surface, {
        licenseKey: 'GPL',
        label,
        placeholder,
        initialData: initialDocument(input.value),
        htmlSupport: false,
        toolbar: {
            items: [
                'undo',
                'redo',
                '|',
                'heading',
                '|',
                'bold',
                'italic',
                'underline',
                'strikethrough',
                'removeFormat',
                '|',
                'link',
                '|',
                'bulletedList',
                'numberedList',
                'outdent',
                'indent',
                '|',
                'blockQuote',
                'horizontalLine',
                '|',
                'imageUpload',
                'insertImage',
                '|',
                'alignment:left',
                'alignment:center',
                'alignment:right',
                'alignment:justify',
                '|',
                'fullscreen',
            ],
            shouldNotGroupWhenFullWidth: true,
        },
        heading: {
            options: [
                { model: 'paragraph', title: 'Paragraf', class: 'ck-heading_paragraph' },
                { model: 'heading2', view: 'h2', title: 'Judul 2', class: 'ck-heading_heading2' },
                { model: 'heading3', view: 'h3', title: 'Judul 3', class: 'ck-heading_heading3' },
                { model: 'heading4', view: 'h4', title: 'Judul 4', class: 'ck-heading_heading4' },
            ],
        },
        list: {
            properties: {
                styles: true,
                startIndex: true,
                reversed: true,
            },
        },
        image: {
            // Only the alt text widget is kept: the resize, style and caption
            // widgets all persist inline styles or elements the sanitizer drops.
            toolbar: ['imageTextAlternative'],
            insert: {
                integrations: ['upload', 'url'],
                type: 'block',
            },
            styles: ['block'],
            resizeUnit: '%',
        },
        imageUpload: {
            types: ['jpeg', 'png', 'webp'],
        },
        link: {
            addTargetToExternalLinks: false,
            defaultProtocol: 'https',
            enforceDefaultProtocol: true,
            protocols: ['http', 'https', 'mailto', 'tel'],
        },
        autoformat: {
            underlineOnEnter: false,
            linkOnPaste: false,
            keepDelimitersWithContent: true,
        },
        removeFormat: {
            formats: ['bold', 'italic', 'underline', 'strikethrough', 'link'],
        },
        fullscreen: {
            onEnter: (element) => element.classList.add('rte-fullscreen-surface'),
            onExit: (element) => element.classList.remove('rte-fullscreen-surface'),
        },
        plugins: [
            Essentials,
            Paragraph,
            Heading,
            Bold,
            Italic,
            Underline,
            Strikethrough,
            List,
            BlockQuote,
            HorizontalLine,
            Alignment,
            Link,
            Indent,
            Autoformat,
            RemoveFormat,
            Undo,
            Fullscreen,
            Image,
            ImageUpload,
            ImageTextAlternative,
        ],
    });

    const setStatus = (message) => {
        if (statusRegion) {
            statusRegion.textContent = message;
        }
    };

    const refreshCount = () => {
        if (!countRegion) {
            return;
        }

        const markup = editor.getData() || '';
        const characters = normalizeForStorage(markup).length;
        const words = (markup.replace(/<[^>]*>/g, ' ').match(/\S+/g) || []).length;
        const overLimit = characters > MAX_BODY_CHARACTERS;

        countRegion.textContent = `${words} kata · ${characters} karakter${overLimit ? ' (melebihi batas)' : ''}`;
        countRegion.classList.toggle('text-rose-700', overLimit);
    };

    const syncInput = () => {
        input.value = normalizeForStorage(editor.getData());
        refreshCount();
    };

    editor.model.document.on('change:data', () => {
        window.requestAnimationFrame(syncInput);
    });

    editor.plugins.get('FileRepository').createUploadAdapter = (loader) =>
        new SecureImageUploadAdapter(loader, {
            endpoint: root.dataset.uploadUrl,
            root,
            onStatus: setStatus,
        });

    // The textarea is hidden only now that the editing surface exists, so a
    // failed mount leaves an honest plain textarea rather than an empty shell.
    input.hidden = true;
    root.hidden = false;

    root.querySelectorAll('[data-rte-requires-idle]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (element instanceof HTMLInputElement && element.type === 'file') {
                event.preventDefault();
                element.click();
            }
        });
    });

    if (form) {
        form.addEventListener('submit', (event) => {
            if (root.dataset.uploading === 'true') {
                event.preventDefault();
                setStatus('Tunggu hingga selesai mengunggah gambar.');

                return;
            }

            syncInput();

            if (editor.getData().replace(/<[^>]*>/g, '').trim() === '') {
                event.preventDefault();
                setStatus('Isi berita tidak boleh kosong.');

                return;
            }

            if (input.value.length > MAX_BODY_CHARACTERS) {
                event.preventDefault();
                setStatus(`Isi berita melebihi ${MAX_BODY_CHARACTERS} karakter markup.`);
            }
        });
    }

    syncInput();

    return {
        editor,
        destroy: () => editor.destroy(),
    };
}

export { normalizeForStorage };
