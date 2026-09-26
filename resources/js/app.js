/**
 * Admin shell behaviour.
 *
 * The sidebar is a plain element that is only transformed on small screens, so
 * the navigation works with no JavaScript at all: without this script it is
 * simply visible in its untranslated position. Everything here is progressive
 * enhancement for the mobile drawer.
 */
function mountSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const overlay = document.querySelector('[data-sidebar-overlay]');

    if (!sidebar || !toggle) {
        return;
    }

    const setOpen = (open) => {
        sidebar.classList.toggle('-translate-x-full', !open);
        toggle.setAttribute('aria-expanded', String(open));

        if (overlay) {
            overlay.hidden = !open;
        }

        document.body.classList.toggle('overflow-hidden', open);
    };

    setOpen(false);

    toggle.addEventListener('click', () => {
        setOpen(sidebar.classList.contains('-translate-x-full'));
    });

    overlay?.addEventListener('click', () => setOpen(false));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
            toggle.focus();
        }
    });

    // A resize past the breakpoint removes the drawer state, so returning to a
    // wide viewport never leaves the page scroll locked.
    window.matchMedia('(min-width: 1024px)').addEventListener('change', (event) => {
        if (event.matches) {
            setOpen(false);
        }
    });
}

/**
 * The rich text editor is heavy, and only a handful of admin screens need it.
 * Loading it through a dynamic import keeps the bundle off every public page:
 * Vite splits the module into its own chunk, which is only requested when a
 * rich text editor is actually present in the document.
 */
function mountEditors() {
    const roots = document.querySelectorAll('[data-rich-text-editor]');

    if (roots.length === 0) {
        return;
    }

    import('./editor/rich-text-editor')
        .then(({ mountRichTextEditor }) => {
            roots.forEach((root) => {
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

function boot() {
    mountSidebar();
    mountEditors();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
