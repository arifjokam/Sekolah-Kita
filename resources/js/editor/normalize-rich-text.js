/**
 * Markup normalisation for the rich text editor.
 *
 * CKEditor writes `style="text-align: …"`, wraps block images in `<figure>`
 * and may attach a `<figcaption>`. The stored body must not carry a style
 * attribute, must not inline a caption element the server sanitizer would drop,
 * and may only keep the `data-align` values RichTextSanitizer accepts.
 *
 * Normalising on the way out means the server allowlist stays exactly as
 * strict as it is, and the result is still sanitized again on the way in.
 *
 * The module is deliberately free of editor imports so it can be reasoned
 * about, and unit tested, on its own.
 */

const TEXT_ALIGN_PATTERN = /(^|;)\s*text-align\s*:\s*(left|center|right|justify)\s*(;|$)/i;
const ALIGNMENTS = ['left', 'center', 'right', 'justify'];

/**
 * Blocks the editor may still be configured to emit but the sanitizer refuses.
 * They are unwrapped rather than dropped so no author text is lost.
 */
const UNWRAPPED_ELEMENTS = ['figure', 'div', 'span', 'section', 'article'];

function unwrapElements(html, elements) {
    return elements.reduce((value, element) => {
        const pattern = new RegExp(`<${element}\\b[^>]*>([\\s\\S]*?)<\\/${element}>`, 'gi');

        return value.replace(pattern, '$1');
    }, html);
}

function dropAttributes(html, attribute) {
    return html.replace(new RegExp(`\\s${attribute}="[^"]*"`, 'gi'), '');
}

/**
 * Keep at most one data-align per tag.
 *
 * The style rewrite appends its value where the style attribute was, so the
 * last occurrence on a tag is the one derived from the editor's own state and
 * the earlier ones came from the loaded body.
 */
function collapseDataAlign(html) {
    return html.replace(/<([a-z][a-z0-9]*)((?:"[^"]*"|[^<>])*?)(\s*\/?)>/gi, (match, tag, attributes, closing) => {
        const values = [...String(attributes).matchAll(/\sdata-align="[^"]*"/gi)];

        if (values.length < 2) {
            return match;
        }

        // The style rewrite appends its value where the style attribute was,
        // so the last occurrence is the one derived from the editor state.
        let rest = String(attributes);

        for (const value of values.slice(0, -1)) {
            rest = rest.replace(value[0], ' ');
        }

        return `<${tag}${rest.replace(/\s{2,}/g, ' ')}${closing}>`;
    });
}

function dropRemainingTags(html, elements) {
    return elements.reduce((value, element) => {
        return value.replace(new RegExp(`<\\/?${element}\\b[^>]*>`, 'gi'), '');
    }, html);
}

export function normalizeForStorage(html) {
    let value = String(html ?? '');

    // A caption becomes its own paragraph: the sanitizer has no <figcaption>,
    // and dropping the text silently would lose what the author wrote.
    value = value.replace(
        /<figcaption\b[^>]*>([\s\S]*?)<\/figcaption>/gi,
        (_match, text) => `<p>${text.trim()}</p>`,
    );

    // These wrappers carry nothing the sanitizer keeps, so their content moves
    // up a level instead of disappearing.
    value = unwrapElements(value, UNWRAPPED_ELEMENTS);

    // text-align becomes data-align; every other declaration is dropped.
    value = value.replace(/\sstyle="([^"]*)"/gi, (_match, declarations) => {
        const textAlign = TEXT_ALIGN_PATTERN.exec(declarations);

        if (textAlign === null) {
            return '';
        }

        const alignment = String(textAlign[2]).toLowerCase();

        return ALIGNMENTS.includes(alignment) ? ` data-align="${alignment}"` : '';
    });

    // Any leftover inline style, and the editor's class bookkeeping, are noise.
    value = dropAttributes(value, 'style');
    value = dropAttributes(value, 'class');
    value = collapseDataAlign(value);

    // Tags with no textual content, e.g. an empty <img> left by a failed
    // upload, are removed rather than stored.
    value = dropRemainingTags(value, ['script', 'style', 'iframe', 'object', 'embed']);

    return value.trim();
}

export { ALIGNMENTS };
