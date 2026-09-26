/**
 * The editor is heavy, and only a handful of admin screens need it. Loading it
 * through a dynamic import keeps the bundle off every public page: Vite splits
 * the module into its own chunk, which is only requested when a rich text
 * editor is actually present in the document.
 */
function mountEditors() {
    if (!document.querySelector('[data-rich-text-editor]')) {
        return;
    }

    import('./editor/rich-text-editor')
        .then(({ mountRichTextEditor }) => {
            document.querySelectorAll('[data-rich-text-editor]').forEach((root) => {
                try {
                    mountRichTextEditor(root);
                } catch (error) {
                    console.error('Rich text editor failed to mount.', error);
                }
            });
        })
        .catch((error) => {
            console.error('Rich text editor could not be loaded.', error);
        });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountEditors);
} else {
    mountEditors();
}
