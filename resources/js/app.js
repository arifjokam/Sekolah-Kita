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

/**
 * Attendance sheet fast controls.
 *
 * The register is a plain form: every radio and note field works with JavaScript
 * disabled, and the form posts exactly the same payload either way. These two
 * buttons only remove the need to click through thirty radios for a class where
 * everybody is present, which is the common case in a real school morning.
 */
function mountAttendanceSheet() {
    const sheet = document.querySelector('[data-attendance-sheet]');

    if (!sheet) {
        return;
    }

    const radios = () => sheet.querySelectorAll('input[type="radio"]');

    sheet.querySelectorAll('[data-mark-all]').forEach((button) => {
        button.addEventListener('click', () => {
            const value = button.dataset.markAll;

            radios().forEach((radio) => {
                if (radio.value === value) {
                    radio.checked = true;
                }
            });
        });
    });

    sheet.querySelectorAll('[data-clear-all]').forEach((button) => {
        button.addEventListener('click', () => {
            radios().forEach((radio) => {
                radio.checked = false;
            });
        });
    });
}

/**
 * The document revision note on the PPDB review screen.
 *
 * Returning a document is not a one-click decision: the note is the only thing
 * the applicant is told, so it is a real required field rather than an optional
 * prompt. The button reveals the field instead of submitting, and focus moves
 * into it, so a reviewer cannot send it back without reading what they are
 * asking for. The server still validates it — this is convenience, not the rule.
 */
function mountDocumentRevision() {
    document.querySelectorAll('[data-document-revision]').forEach((button) => {
        const target = document.getElementById(button.dataset.target);

        if (!target) {
            return;
        }

        button.setAttribute('aria-expanded', 'false');

        button.addEventListener('click', () => {
            const isOpen = !target.classList.contains('hidden');

            target.classList.toggle('hidden', isOpen);
            button.setAttribute('aria-expanded', isOpen ? 'false' : 'true');

            if (!isOpen) {
                target.querySelector('textarea')?.focus();
            }
        });
    });
}

function boot() {
    mountSidebar();
    mountEditors();
    mountAttendanceSheet();
    mountDocumentRevision();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
