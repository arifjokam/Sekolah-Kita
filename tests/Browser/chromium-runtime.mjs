/**
 * Make Playwright's Chromium startable on a box that has no root.
 *
 * Playwright downloads a self-contained Chromium, but it is not fully
 * self-contained: it still links against ~18 desktop libraries (glib, nss,
 * atk, cairo, pango, cups, xkbcommon, gbm, avahi, ...). On a normal desktop or
 * CI image those are present. On a locked-down container -- a Termux/Ubuntu-PRoot
 * box with no `sudo`, which is how this project is developed -- they are not, and
 * Chromium dies before the first scenario with a bare "error while loading
 * shared libraries" and not a single application assertion executed.
 *
 * That failure looks like an application failure and is not one, so it is fixed
 * here rather than in the application. `prepare-chromium.sh` fetches the owning
 * Debian packages unprivileged and unpacks them into a private prefix; this
 * module points the dynamic linker at that prefix before Chromium is spawned.
 *
 * Both steps are no-ops wherever the libraries already resolve, so a developer
 * machine and a CI image need none of this. Nothing outside the private prefix
 * is modified, and no application behaviour depends on any of it.
 */

import { execFileSync } from 'node:child_process'
import { existsSync, readdirSync } from 'node:fs'
import { createRequire } from 'node:module'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const require = createRequire(import.meta.url)
const here = path.dirname(fileURLToPath(import.meta.url))

/** Private prefix the resolver unpacks into. Overridable for unusual layouts. */
const PREFIX = process.env.CHROMIUM_LIB_PREFIX ?? path.resolve(here, '../../storage/framework/testing/chromium-libs')

/** Directories inside that prefix that the linker should search, in order. */
const LIB_DIRS = [
    'usr/lib/aarch64-linux-gnu',
    'lib/aarch64-linux-gnu',
    'usr/lib',
    'lib',
    'usr/lib/x86_64-linux-gnu',
    'lib/x86_64-linux-gnu',
].map((dir) => path.join(PREFIX, dir))

/** Playwright's Chromium binaries, newest revision first. */
function chromiumBinaries() {
    const binaries = []

    const explicit = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH
    if (explicit && existsSync(explicit)) {
        binaries.push(explicit)
    }

    // Both the full browser and the headless shell, on either architecture.
    const layouts = [
        ['chromium-', ['chrome-linux-arm64/chrome', 'chrome-linux/chrome']],
        [
            'chromium_headless_shell-',
            [
                'chrome-headless-shell-linux-arm64/chrome-headless-shell',
                'chrome-headless-shell-linux/chrome-headless-shell',
            ],
        ],
    ]

    const installed = path.join(process.env.HOME ?? '', '.cache/ms-playwright')
    if (existsSync(installed)) {
        // Newest revision first, so the version the harness would use is checked.
        const revisions = readdirSync(installed).sort().reverse()
        for (const [prefix, candidates] of layouts) {
            for (const dir of revisions.filter((name) => name.startsWith(prefix))) {
                for (const candidate of candidates) {
                    binaries.push(path.join(installed, dir, candidate))
                }
            }
        }
    }

    try {
        binaries.push(require('playwright').chromium.executablePath())
    } catch {
        // Playwright not resolvable from here; the caller fails with a clearer
        // error than this module can produce.
    }

    return [...new Set(binaries)].filter(existsSync)
}

/** Shared libraries `ldd` reports as missing for a binary. */
function missingFor(binary) {
    try {
        const output = execFileSync('ldd', [binary], {
            encoding: 'utf8',
            env: { ...process.env, LD_LIBRARY_PATH: linkPath() },
            stdio: ['ignore', 'pipe', 'ignore'],
        })
        return output
            .split('\n')
            .filter((line) => line.includes('not found'))
            .map((line) => line.trim().split(/\s+/)[0])
    } catch {
        return []
    }
}

/** The LD_LIBRARY_PATH value in effect, prefix first, existing value preserved. */
function linkPath() {
    const existing = process.env.LD_LIBRARY_PATH
    return [...LIB_DIRS, existing].filter(Boolean).join(':')
}

/**
 * Ensure Chromium can start, then export LD_LIBRARY_PATH for the child process.
 *
 * Returns a short status string so the caller can report what happened. This
 * mutates `process.env` on purpose: Playwright spawns the browser as a child of
 * this process and the dynamic linker reads the variable at exec time, so
 * setting it here is the only hook that reliably reaches the browser.
 */
export function prepareChromiumRuntime() {
    const binaries = chromiumBinaries()

    if (binaries.length === 0) {
        return { ok: false, detail: 'no Playwright Chromium binary found; run `npx playwright install chromium`' }
    }

    const missing = [...new Set(binaries.flatMap(missingFor))]

    if (missing.length > 0 && !existsSync(path.join(PREFIX, 'usr'))) {
        // The resolver has not populated the private prefix yet.
        try {
            execFileSync('bash', [path.join(here, 'prepare-chromium.sh')], { stdio: 'inherit' })
        } catch (error) {
            return {
                ok: false,
                detail: `Chromium is missing ${missing.join(', ')} and the resolver failed: ${error.message}`,
            }
        }
    }

    process.env.LD_LIBRARY_PATH = linkPath()

    const stillMissing = [...new Set(chromiumBinaries().flatMap(missingFor))]

    if (stillMissing.length > 0) {
        return { ok: false, detail: `Chromium still cannot resolve: ${stillMissing.join(', ')}` }
    }

    return {
        ok: true,
        detail: `Chromium shared libraries resolve from ${existsSync(path.join(PREFIX, 'usr')) ? PREFIX : 'the system'}`,
    }
}
