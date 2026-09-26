import { Editor, Extension } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import TextAlign from '@tiptap/extension-text-align';

/**
 * Server rendered rich text editor for article bodies.
 *
 * The module is framework agnostic and reusable: it only needs a root element
 * carrying data attributes, so the same editor can back the school profile,
 * PPDB information and static pages later.
 *
 * Contract with the backend:
 *  - the editor keeps a plain textarea in sync, so the form still posts a
 *    normal "body" field and works without JavaScript;
 *  - nothing is sent to the server while typing, images are uploaded only on an
 *    explicit user action and image data is never inlined as base64;
 *  - the server sanitizes the markup again before storing it, so the editor
 *    output is a convenience, not a trust boundary.
 */

const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;
const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_BODY_CHARACTERS = 20000;

/**
 * Renders alignment as a data attribute instead of an inline style.
 *
 * The stored body must never carry a style attribute, so the official
 * extension is extended to read and write "data-align". Existing markup written
 * with an inline style is still read when the body is loaded.
 */
const DataAttributeTextAlign = TextAlign.extend({
    addGlobalAttributes() {
        return [
            {
                types: this.options.types,
                attributes: {
                    textAlign: {
                        default: this.options.defaultAlignment,
                        parseHTML: (element) => {
                            const alignment = element.getAttribute('data-align') || element.style.textAlign;

                            return this.options.alignments.includes(alignment)
                                ? alignment
                                : this.options.defaultAlignment;
                        },
                        renderHTML: (attributes) =>
                            attributes.textAlign ? { 'data-align': attributes.textAlign } : {},
                    },
                },
            },
        ];
    },
});

/**
 * Responsive image sizing presets, stored as a data attribute so the public
 * stylesheet can size an image without an inline style.
 */
const ImageSizing = Extension.create({
    name: 'imageSizing',

    addGlobalAttributes() {
        return [
            {
                types: ['image'],
                attributes: {
                    dataWidth: {
                        default: null,
                        parseHTML: (element) => element.getAttribute('data-width'),
                        renderHTML: (attributes) =>
                            attributes.dataWidth ? { 'data-width': String(attributes.dataWidth) } : {},
                    },
                },
            },
        ];
    },
});

/**
 * Toolbar actions. Each entry either runs a command or reports whether it is
 * currently active, which is what keeps the toolbar accessible: the pressed
 * state is a real aria-pressed attribute rather than a visual-only class.
 */
const ACTIONS = {
    bold: {
        run: (editor) => editor.chain().focus().toggleBold().run(),
        isActive: (editor) => editor.isActive('bold'),
    },
    italic: {
        run: (editor) => editor.chain().focus().toggleItalic().run(),
        isActive: (editor) => editor.isActive('italic'),
    },
    underline: {
        run: (editor) => editor.chain().focus().toggleUnderline().run(),
        isActive: (editor) => editor.isActive('underline'),
    },
    strike: {
        run: (editor) => editor.chain().focus().toggleStrike().run(),
        isActive: (editor) => editor.isActive('strike'),
    },
    paragraph: {
        run: (editor) => editor.chain().focus().setParagraph().run(),
        isActive: (editor) => editor.isActive('paragraph'),
    },
    heading2: {
        run: (editor) => editor.chain().focus().toggleHeading({ level: 2 }).run(),
        isActive: (editor) => editor.isActive('heading', { level: 2 }),
    },
    heading3: {
        run: (editor) => editor.chain().focus().toggleHeading({ level: 3 }).run(),
        isActive: (editor) => editor.isActive('heading', { level: 3 }),
    },
    heading4: {
        run: (editor) => editor.chain().focus().toggleHeading({ level: 4 }).run(),
        isActive: (editor) => editor.isActive('heading', { level: 4 }),
    },
    bulletList: {
        run: (editor) => editor.chain().focus().toggleBulletList().run(),
        isActive: (editor) => editor.isActive('bulletList'),
    },
    orderedList: {
        run: (editor) => editor.chain().focus().toggleOrderedList().run(),
        isActive: (editor) => editor.isActive('orderedList'),
    },
    blockquote: {
        run: (editor) => editor.chain().focus().toggleBlockquote().run(),
        isActive: (editor) => editor.isActive('blockquote'),
    },
    horizontalRule: {
        run: (editor) => editor.chain().focus().setHorizontalRule().run(),
        isActive: () => false,
    },
    clearFormatting: {
        run: (editor) => editor.chain().focus().unsetAllMarks().clearNodes().run(),
        isActive: () => false,
    },
    alignLeft: {
        run: (editor) => editor.chain().focus().setTextAlign('left').run(),
        isActive: (editor) => editor.isActive({ textAlign: 'left' }),
    },
    alignCenter: {
        run: (editor) => editor.chain().focus().setTextAlign('center').run(),
        isActive: (editor) => editor.isActive({ textAlign: 'center' }),
    },
    alignRight: {
        run: (editor) => editor.chain().focus().setTextAlign('right').run(),
        isActive: (editor) => editor.isActive({ textAlign: 'right' }),
    },
    alignJustify: {
        run: (editor) => editor.chain().focus().setTextAlign('justify').run(),
        isActive: (editor) => editor.isActive({ textAlign: 'justify' }),
    },
    undo: {
        run: (editor) => editor.chain().focus().undo().run(),
        isActive: (editor) => editor.can().undo(),
    },
    redo: {
        run: (editor) => editor.chain().focus().redo().run(),
        isActive: (editor) => editor.can().redo(),
    },
};

const IMAGE_WIDTH_ACTIONS = {
    imageWidth: (editor, value) =>
        editor.chain().focus().updateAttributes('image', { dataWidth: value || null }).run(),
    isActive: (editor, value) =>
        editor.isActive('image') &&
        String(editor.getAttributes('image').dataWidth ?? '') === String(value ?? ''),
};

const REQUIRED_PROTOCOLS = /^(https?:|mailto:|tel:)/i;

/**
 * Mirrors RichTextSanitizer::looksLikeRichText() on the server.
 *
 * A body without block level editor markup is legacy plain text, and has to be
 * handled as text rather than as markup.
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
 * the first save does not collapse the whole article into one block. The text is
 * escaped on the way in: a body that happens to contain markup is still text,
 * which is exactly how it rendered before the editor existed.
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

/**
 * @param {HTMLElement} root
 * @returns {{destroy: () => void, editor: Editor}}
 */
export function mountRichTextEditor(root) {
    const input = root.querySelector('[data-rte-input]');
    const surface = root.querySelector('[data-rte-editor]');
    const fileInput = root.querySelector('[data-rte-file]');
    const statusRegion = root.querySelector('[data-rte-status]');
    const progressBar = root.querySelector('[data-rte-progress]');
    const progressFill = root.querySelector('[data-rte-progress-bar]');
    const countRegion = root.querySelector('[data-rte-count]');
    const linkPanel = root.querySelector('[data-rte-link]');
    const linkForm = root.querySelector('[data-rte-link-form]');
    const linkHref = root.querySelector('[data-rte-link-href]');
    const imageAltField = root.querySelector('[data-rte-image-alt]');
    const fullscreenClass = 'rte-fullscreen';

    if (!input || !surface) {
        throw new Error('The rich text editor root is missing its input or surface element.');
    }

    const maxUploadBytes = Number(root.dataset.maxUploadBytes) || MAX_UPLOAD_BYTES;
    const placeholder = root.dataset.placeholder || 'Tulis isi berita di sini…';
    const form = input.form;

    // Reveal the editor before it is created: ProseMirror measures its surface
    // on mount, and a hidden element cannot take focus afterwards.
    input.hidden = true;
    root.hidden = false;

    const editor = new Editor({
        element: surface,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3, 4] },
                code: false,
                codeBlock: false,
                link: {
                    openOnClick: false,
                    defaultProtocol: 'https',
                    protocols: ['http', 'https', 'mailto', 'tel'],
                    HTMLAttributes: { rel: 'noopener noreferrer nofollow ugc' },
                },
            }),
            DataAttributeTextAlign.configure({ types: ['heading', 'paragraph', 'image'] }),
            Image.configure({
                allowBase64: false,
                inline: false,
            }),
            ImageSizing,
            Placeholder.configure({ placeholder }),
        ],
        content: initialDocument(input.value),
        editorProps: {
            attributes: {
                class: 'rte-surface focus:outline-none',
                'aria-label': 'Isi berita',
            },
        },
        onUpdate: () => scheduleSync(),
        onSelectionUpdate: () => scheduleRefresh(),
        onTransaction: () => scheduleRefresh(),
    });

    surface.focus();

    let pendingSync = null;
    let pendingRefresh = null;

    function scheduleSync() {
        if (pendingSync !== null) {
            return;
        }

        pendingSync = window.requestAnimationFrame(() => {
            pendingSync = null;
            input.value = editor.getHTML();
            scheduleRefresh();
        });
    }

    function scheduleRefresh() {
        if (pendingRefresh !== null) {
            return;
        }

        pendingRefresh = window.requestAnimationFrame(() => {
            pendingRefresh = null;
            refreshToolbar();
            refreshCount();
        });
    }

    function refreshToolbar() {
        root.querySelectorAll('[data-rte-action]').forEach((button) => {
            const name = button.dataset.rteAction;
            const action = ACTIONS[name] || IMAGE_WIDTH_ACTIONS[name];

            if (!action) {
                return;
            }

            const active = action.isActive(editor, button.dataset.rteValue);
            button.setAttribute('aria-pressed', String(Boolean(active)));
            button.classList.toggle('rte-toolbar-button-active', Boolean(active));
        });
    }

    function refreshCount() {
        if (!countRegion) {
            return;
        }

        const text = editor.getText();
        const words = text.trim() === '' ? 0 : text.trim().split(/\s+/).length;
        const characters = editor.getHTML().length;
        const overLimit = characters > MAX_BODY_CHARACTERS;

        countRegion.textContent = `${words} kata · ${characters} karakter${overLimit ? ' (melebihi batas)' : ''}`;
        countRegion.classList.toggle('text-rose-700', overLimit);
    }

    function setStatus(message) {
        if (statusRegion) {
            statusRegion.textContent = message;
        }
    }

    function setUploading(isUploading) {
        root.dataset.uploading = String(isUploading);
        root.querySelectorAll('[data-rte-requires-idle]').forEach((element) => {
            element.disabled = isUploading;
        });

        if (progressBar) {
            progressBar.hidden = !isUploading;
        }

        if (progressFill) {
            progressFill.style.width = '0%';
        }    }

    /**
     * Upload one image and insert it at the current selection.
     */
    function uploadImage(file) {
        if (!file.type || !ACCEPTED_IMAGE_TYPES.includes(file.type)) {
            setStatus('Format gambar harus JPG, PNG, atau WEBP.');

            return;
        }

        if (file.size > maxUploadBytes) {
            setStatus(`Ukuran gambar melebihi ${Math.round(maxUploadBytes / 1024 / 1024)} MB.`);

            return;
        }

        setUploading(true);
        setStatus('Mengunggah gambar…');

        const request = new XMLHttpRequest();
        request.open('POST', root.dataset.uploadUrl, true);
        request.setRequestHeader('X-CSRF-TOKEN', csrfToken(root, form));
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        request.upload.addEventListener('progress', (event) => {
            if (!progressFill || !event.lengthComputable) {
                return;
            }

            progressFill.style.width = `${Math.round((event.loaded / event.total) * 100)}%`;
        });

        request.addEventListener('load', () => {
            setUploading(false);

            let payload = null;

            try {
                payload = JSON.parse(request.responseText);
            } catch (error) {
                payload = null;
            }

            if (request.status < 200 || request.status >= 300 || !payload || !payload.url) {
                setStatus(firstErrorMessage(payload) || 'Gagal mengunggah gambar.');
                openLinkPanel('image');

                return;
            }

            editor.chain().focus().setImage({ src: payload.url, alt: '' }).run();
            setStatus('Gambar ditambahkan. Isi teks alternatif agar dapat diakses pembaca layar.');
            scheduleSync();
        });

        request.addEventListener('error', () => {
            setUploading(false);
            setStatus('Gagal mengunggah gambar. Periksa koneksi lalu coba lagi.');
        });

        const payload = new FormData();
        payload.append('image', file);
        request.send(payload);
    }

    function openLinkPanel(mode) {
        if (!linkPanel) {
            return;
        }

        const isImage = mode === 'image' && editor.isActive('image');

        if (mode === 'link' && !editor.state.selection.empty && !editor.isActive('link')) {
            editor.commands.extendMarkRange('link');
        }

        linkPanel.dataset.mode = isImage ? 'image' : 'link';
        linkPanel.hidden = false;

        if (linkHref) {
            linkHref.value = isImage ? '' : editor.getAttributes('link').href || '';
        }

        if (imageAltField) {
            imageAltField.value = isImage ? editor.getAttributes('image').alt || '' : '';
        }

        (isImage ? imageAltField : linkHref)?.focus();
    }

    function closeLinkPanel() {
        if (linkPanel) {
            linkPanel.hidden = true;
        }
    }

    root.querySelectorAll('[data-rte-action]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();

            const name = button.dataset.rteAction;

            if (name === 'image') {
                fileInput?.click();

                return;
            }

            if (name === 'link' || name === 'imageAlt') {
                if (name === 'imageAlt' && !editor.isActive('image')) {
                    setStatus('Pilih gambar di dalam isi berita terlebih dahulu.');

                    return;
                }

                openLinkPanel(name === 'imageAlt' ? 'image' : 'link');

                return;
            }

            if (name === 'fullscreen') {
                const active = root.classList.toggle(fullscreenClass);
                button.setAttribute('aria-pressed', String(active));
                surface.focus();

                return;
            }

            const action = ACTIONS[name] || IMAGE_WIDTH_ACTIONS[name];

            if (action) {
                action.run(editor, button.dataset.rteValue);
                scheduleSync();
            }
        });
    });

    root.querySelectorAll('[data-rte-close-panel]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            closeLinkPanel();
            surface.focus();
        });
    });

    linkForm?.addEventListener('submit', (event) => {
        event.preventDefault();

        if (linkPanel?.dataset.mode === 'image') {
            editor.chain().focus().updateAttributes('image', { alt: imageAltField?.value ?? '' }).run();
        } else {
            const href = (linkHref?.value ?? '').trim();

            if (!REQUIRED_PROTOCOLS.test(href)) {
                setStatus('Alamat tautan harus diawali http://, https://, mailto:, atau tel:.');

                return;
            }

            editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
        }

        closeLinkPanel();
        scheduleSync();
        surface.focus();
    });

    root.querySelectorAll('[data-rte-remove-link]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            editor.chain().focus().unsetLink().run();
            closeLinkPanel();
            scheduleSync();
            surface.focus();
        });
    });

    fileInput?.addEventListener('change', () => {
        const [file] = fileInput.files || [];

        if (file) {
            uploadImage(file);
        }

        fileInput.value = '';
    });

    ['dragenter', 'dragover'].forEach((type) => {
        surface.addEventListener(type, (event) => {
            event.preventDefault();
            root.dataset.dragging = 'true';
        });
    });

    ['dragleave', 'drop'].forEach((type) => {
        surface.addEventListener(type, () => {
            root.dataset.dragging = 'false';
        });
    });

    surface.addEventListener('drop', (event) => {
        event.preventDefault();

        const [file] = event.dataTransfer?.files || [];

        if (file) {
            uploadImage(file);
        }
    });

    surface.addEventListener('paste', (event) => {
        const [file] = event.clipboardData?.files || [];

        // Only real files are uploaded. Pasted markup and pasted data URLs are
        // left to ProseMirror, and a data URL can never reach the stored body:
        // the image extension runs with allowBase64 false, and the server
        // refuses any image source that is not a stored media asset.
        if (file && file.type.startsWith('image/')) {
            event.preventDefault();
            uploadImage(file);
        }
    });

    if (form) {
        form.addEventListener('submit', (event) => {
            if (root.dataset.uploading === 'true') {
                event.preventDefault();
                setStatus('Tunggu hingga selesai mengunggah gambar.');
                surface.focus();

                return;
            }

            input.value = editor.getHTML();

            if (editor.getText().trim() === '') {
                event.preventDefault();
                setStatus('Isi berita tidak boleh kosong.');
                surface.focus();

                return;
            }

            if (input.value.length > MAX_BODY_CHARACTERS) {
                event.preventDefault();
                setStatus(`Isi berita melebihi ${MAX_BODY_CHARACTERS} karakter markup.`);
                surface.focus();
            }
        });
    }

    refreshToolbar();
    refreshCount();

    return {
        editor,
        destroy: () => editor.destroy(),
    };
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
