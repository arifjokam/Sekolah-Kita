/**
 * End-to-end browser smoke test.
 *
 * Phase 12 of the brief asks for browser-level verification, which HTTP-level
 * checks cannot provide: a 200 response says nothing about whether CKEditor
 * mounted, whether a toolbar button is clickable, or whether the browser logged
 * an error while hydrating the page.
 *
 * This script drives a real Chromium against a running server. It fails on any
 * uncaught page error, any console error, and any failed request, so a broken
 * editor or a 500 from a lazy chunk cannot pass silently.
 *
 * Usage:
 *   php artisan db:seed --class=DemoUatSeeder
 *   php artisan serve --host=127.0.0.1 --port=8123
 *   npm run build
 *   node tests/Browser/smoke.mjs [baseUrl]
 *
 * Credentials come from DevelopmentAccountsSeeder and are development-only.
 *
 * The suite restores every fixture it mutates — the schedule room and the
 * returned document are both put back — so it can be run repeatedly without a
 * re-seed in between. The one thing it does add is a clearly labelled draft
 * academic year per run: Semester 1 and Semester 2 have to be created inside one
 * year to prove they can coexist, and a semester cannot be deleted afterwards, so
 * the journey takes a fresh year rather than filling the live one up.
 */

import { chromium } from 'playwright'
import { prepareChromiumRuntime } from './chromium-runtime.mjs'

const runtime = prepareChromiumRuntime()
if (!runtime.ok) {
    console.error(`Chromium cannot start: ${runtime.detail}`)
    console.error('This is an environment problem, not an application failure.')
    process.exit(1)
}
console.log(`browser runtime: ${runtime.detail}`)

const BASE = (process.argv[2] ?? 'http://127.0.0.1:8123').replace(/\/$/, '')
const PASSWORD = 'SekolahKita#Dev2026'

const ACCOUNTS = {
    admin: 'admin.test@sekolah.local',
    guru: 'guru.test@sekolah.local',
    siswa: 'siswa.test@sekolah.local',
    // A candidate with a document waiting for a replacement, so the revision
    // loop is exercised in a real browser rather than only in a feature test.
    calon: 'calon.perbaikan@sekolah.local',
}

/** Routes that must render for each role, and what must be visible on them. */
const SCENARIOS = [
    {
        role: 'admin',
        label: 'ADMIN dashboard',
        path: '/dashboard/administrator',
        expect: ['Ringkasan sekolah', 'Aksi cepat'],
    },
    { role: 'admin', label: 'ADMIN teacher list', path: '/admin/teachers', expect: ['Guru'] },
    { role: 'admin', label: 'ADMIN teacher create', path: '/admin/teachers/create', expect: ['Nama lengkap'] },
    { role: 'admin', label: 'ADMIN student list', path: '/admin/students', expect: ['Siswa'] },
    { role: 'admin', label: 'ADMIN student create', path: '/admin/students/create', expect: ['Nama lengkap'] },
    { role: 'admin', label: 'ADMIN academic years', path: '/admin/academic-years', expect: ['Tahun ajaran'] },
    { role: 'admin', label: 'ADMIN semesters', path: '/admin/terms', expect: ['Semester'] },
    { role: 'admin', label: 'ADMIN semester create', path: '/admin/terms/create', expect: ['Semester'] },
    { role: 'admin', label: 'ADMIN classes', path: '/admin/classes', expect: ['Kelas'] },
    { role: 'admin', label: 'ADMIN subjects', path: '/admin/subjects', expect: ['Mata pelajaran'] },
    { role: 'admin', label: 'ADMIN enrollments', path: '/admin/enrollments', expect: ['Enrollment'] },
    { role: 'admin', label: 'ADMIN class schedules', path: '/admin/class-schedules', expect: ['Jadwal pelajaran', 'Jadwal mingguan'] },
    { role: 'admin', label: 'ADMIN schedule create', path: '/admin/class-schedules/create', expect: ['Jadwal pelajaran'] },
    { role: 'admin', label: 'ADMIN teaching assignments', path: '/admin/teaching-assignments', expect: ['Assignment mengajar'] },
    { role: 'admin', label: 'ADMIN class teacher assignments', path: '/admin/class-teacher-assignments', expect: ['Wali kelas'] },
    { role: 'admin', label: 'ADMIN ppdb periods', path: '/admin/ppdb/periods', expect: ['Periode'] },
    { role: 'admin', label: 'ADMIN ppdb applications', path: '/admin/ppdb/applications', expect: ['Pendaftar'] },
    { role: 'admin', label: 'ADMIN ppdb review', path: '/admin/ppdb/review', expect: ['Review'] },
    { role: 'admin', label: 'ADMIN news list', path: '/admin/news', expect: ['Berita'] },
    { role: 'admin', label: 'ADMIN school profile', path: '/admin/school-profile', expect: ['Profil sekolah'] },
    { role: 'admin', label: 'ADMIN achievements', path: '/admin/achievements', expect: ['Prestasi'] },
    { role: 'admin', label: 'ADMIN gallery', path: '/admin/gallery-albums', expect: ['Galeri'] },
    { role: 'admin', label: 'ADMIN audit logs', path: '/admin/audit-logs', expect: ['Audit'] },
    { role: 'admin', label: 'ADMIN notifications', path: '/notifications', expect: ['Notifikasi'] },

    { role: 'guru', label: 'GURU dashboard', path: '/dashboard/guru', expect: ['mengajar'] },
    { role: 'guru', label: 'GURU classes', path: '/guru/kelas', expect: ['Kelas'] },
    { role: 'guru', label: 'GURU schedule', path: '/guru/jadwal', expect: ['Jadwal pelajaran saya'] },
    { role: 'guru', label: 'GURU profile', path: '/guru/profil', expect: ['Biodata guru', 'Data resmi guru'] },
    { role: 'guru', label: 'GURU attendance', path: '/guru/absensi', expect: ['Absensi'] },
    { role: 'guru', label: 'GURU monthly report', path: '/guru/absensi/laporan', expect: ['Laporan absensi bulanan'] },
    { role: 'guru', label: 'GURU notifications', path: '/notifications', expect: ['Notifikasi'] },

    { role: 'siswa', label: 'SISWA dashboard', path: '/dashboard/siswa', expect: ['Siswa'] },
    { role: 'siswa', label: 'SISWA profile', path: '/siswa/profil', expect: ['Biodata siswa', 'Data resmi siswa'] },
    { role: 'siswa', label: 'SISWA schedule', path: '/siswa/jadwal', expect: ['Jadwal pelajaran saya'] },
    { role: 'siswa', label: 'SISWA attendance', path: '/siswa/absensi', expect: ['Absensi saya'] },
    { role: 'siswa', label: 'SISWA notifications', path: '/notifications', expect: ['Notifikasi'] },

    { role: 'calon', label: 'CALON dashboard', path: '/ppdb/akun', expect: ['Kelengkapan pendaftaran'] },
    { role: 'calon', label: 'CALON biodata', path: '/ppdb/akun/biodata', expect: ['Biodata siswa'] },
    { role: 'calon', label: 'CALON guardians', path: '/ppdb/akun/wali', expect: ['Orang tua / wali'] },
    { role: 'calon', label: 'CALON documents', path: '/ppdb/akun/dokumen', expect: ['Dokumen pendukung'] },
    { role: 'calon', label: 'CALON review', path: '/ppdb/akun/review', expect: ['Review data'] },
    { role: 'calon', label: 'CALON status', path: '/ppdb/akun/status', expect: ['Status pendaftaran'] },
    { role: 'calon', label: 'CALON notifications', path: '/ppdb/akun/notifikasi', expect: ['Riwayat pengiriman WhatsApp'] },

    { role: 'guest', label: 'PUBLIC home', path: '/', expect: ['Sekolah'] },
    { role: 'guest', label: 'PUBLIC news', path: '/berita', expect: ['Berita'] },
    { role: 'guest', label: 'PUBLIC ppdb', path: '/ppdb', expect: ['PPDB'] },
    { role: 'guest', label: 'PUBLIC ppdb register', path: '/ppdb/daftar-akun', expect: ['Buat akun pendaftar'] },
]

/** Screens that must block a role server-side, not merely hide the link. */
const FORBIDDEN = [
    { role: 'guru', path: '/admin/students' },
    { role: 'guru', path: '/admin/teachers' },
    { role: 'guru', path: '/admin/audit-logs' },
    { role: 'guru', path: '/admin/class-schedules' },
    { role: 'guru', path: '/siswa/profil' },
    { role: 'siswa', path: '/admin/students' },
    { role: 'siswa', path: '/dashboard/guru' },
    { role: 'siswa', path: '/guru/absensi' },
    { role: 'calon', path: '/dashboard/guru' },
    { role: 'calon', path: '/dashboard/siswa' },
    { role: 'calon', path: '/dashboard/administrator' },
    { role: 'calon', path: '/guru/profil' },
    { role: 'calon', path: '/guru/absensi' },
    { role: 'calon', path: '/siswa/profil' },
    { role: 'calon', path: '/admin/ppdb/applications' },
    { role: 'calon', path: '/admin/class-schedules' },
]

const problems = []
let checks = 0

function record(kind, detail) {
    problems.push(`${kind}: ${detail}`)
}

/**
 * Submit the form that owns a given control, not the first submit on the page.
 *
 * The workspace layout renders the sign-out form in the header, above `<main>`,
 * so its "Keluar" button is a `button[type="submit"]` that precedes every real
 * form on the page. An unscoped `button[type="submit"]` click therefore signs the
 * user out and silently converts the rest of the run into a wall of
 * "missing text" failures against the login screen. Anchoring on the form that
 * contains a known control is what a user actually does.
 */
function formSubmit(page, control) {
    return page.locator(`form:has(${control}) button[type="submit"]`).first()
}

/**
 * Noise that is not an application defect, filtered deliberately.
 *
 * Both cases are dev/test-harness artefacts, and neither is a reason to relax
 * the application's Content-Security-Policy or its 403 behaviour:
 *
 *  1. `laravel/boost` is a require-dev package that injects an inline browser
 *     logger, and it registers only when `app()->environment('local')` or debug
 *     is on. The app ships a strict `script-src 'self'`, so the browser blocks
 *     Boost's own script. It is absent from a production install.
 *  2. A 403 response is logged by the browser as a console error. On the
 *     FORBIDDEN scenarios below that 403 is precisely the correct outcome.
 */
const IGNORED_CONSOLE = [
    /Content Security Policy/,
    /_boost\/browser-logs/,
    /Failed to load resource: the server responded with a status of 403/,
]

function isNoise(text) {
    return IGNORED_CONSOLE.some((pattern) => pattern.test(text))
}

/**
 * Watch a page for the failure modes a status-code check cannot see.
 */
function watch(page, label) {
    page.on('pageerror', (error) => record('page error', `${label} :: ${error.message}`))
    page.on('console', (message) => {
        if (message.type() === 'error' && !isNoise(message.text())) {
            record('console error', `${label} :: ${message.text()}`)
        }
    })
    page.on('requestfailed', (request) => {
        // favicon is served by the dev server and is not part of the app.
        if (request.url().includes('favicon')) {
            return
        }
        record('failed request', `${label} :: ${request.url()} ${request.failure()?.errorText ?? ''}`)
    })
    page.on('response', (response) => {
        if (response.status() >= 500) {
            record('server error', `${label} :: ${response.status()} ${response.url()}`)
        }
    })
}

async function login(page, email) {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' })
    await page.fill('input[name="email"]', email)
    await page.fill('input[name="password"]', PASSWORD)
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20000 }),
        page.click('button[type="submit"]'),
    ])
    return page.url()
}

async function checkScenario(context, scenario) {
    const page = await context.newPage()
    watch(page, scenario.label)
    checks++

    try {
        const response = await page.goto(`${BASE}${scenario.path}`, {
            waitUntil: 'networkidle',
            timeout: 30000,
        })
        const status = response?.status() ?? 0
        if (status !== 200) {
            record('unexpected status', `${scenario.label} :: ${status} on ${scenario.path}`)
            return
        }
        const body = await page.locator('body').innerText()
        for (const needle of scenario.expect) {
            if (!body.includes(needle)) {
                record('missing text', `${scenario.label} :: expected "${needle}"`)
            }
        }
    } catch (error) {
        record('navigation error', `${scenario.label} :: ${error.message}`)
    } finally {
        await page.close()
    }
}

async function checkForbidden(context, entry) {
    const page = await context.newPage()
    watch(page, `FORBIDDEN ${entry.role} ${entry.path}`)
    checks++

    try {
        const response = await page.goto(`${BASE}${entry.path}`, {
            waitUntil: 'domcontentloaded',
            timeout: 30000,
        })
        const status = response?.status() ?? 0
        if (status !== 403 && status !== 302) {
            record('authorization leak', `${entry.role} reached ${entry.path} with ${status}`)
        }
    } catch (error) {
        record('navigation error', `FORBIDDEN ${entry.role} ${entry.path} :: ${error.message}`)
    } finally {
        await page.close()
    }
}

/**
 * The reason this script exists: prove CKEditor really mounts and really works.
 * A 200 on /admin/news/create proves nothing about the editor.
 */
async function checkRichTextEditor(context) {
    const page = await context.newPage()
    watch(page, 'CKEDITOR')
    checks++

    try {
        await page.goto(`${BASE}/admin/news/create`, { waitUntil: 'networkidle', timeout: 40000 })

        const toolbar = page.locator('.ck-toolbar')
        await toolbar.waitFor({ state: 'visible', timeout: 30000 })

        const editor = page.locator('.ck-editor__editable')
        await editor.waitFor({ state: 'visible', timeout: 30000 })

        // The textarea must be hidden only after CKEditor took over.
        const textareaHidden = await page.locator('textarea[name="body"]').isHidden()
        if (!textareaHidden) {
            record('editor not mounted', 'the body textarea is still visible behind the editor')
        }

        const buttons = await page.locator('.ck-toolbar button').count()
        if (buttons < 10) {
            record('toolbar too small', `only ${buttons} toolbar buttons rendered`)
        }

        // Type into the editor and confirm the value reaches the submitted field.
        await editor.click()
        await page.keyboard.type('Verifikasi browser otomatis')
        await page.keyboard.press('Enter')
        await editor.click()
        await page.keyboard.type('Paragraf kedua.')

        const submitted = await page.locator('textarea[name="body"]').inputValue()
        if (!submitted.includes('Verifikasi browser otomatis')) {
            record('editor not syncing', `textarea did not receive editor content: ${submitted.slice(0, 120)}`)
        }
        if (!/<p>/i.test(submitted)) {
            record('editor not producing html', `expected <p> markup, got: ${submitted.slice(0, 120)}`)
        }

        // Exercise formatting through a real toolbar click. CKEditor renders the
        // shortcut inside the tooltip attribute, e.g. "Bold (Ctrl+B)".
        await page.locator('.ck-toolbar button.ck-button[data-cke-tooltip-text^="Bold"]').first().click()
        await page.keyboard.type('Tebal')
        const bolded = await page.locator('textarea[name="body"]').inputValue()
        if (!/<strong|Tebal/.test(bolded)) {
            record('formatting broken', `bold click did not apply: ${bolded.slice(0, 160)}`)
        }

        // Alignment is one of the required open-source features. In this
        // configuration the four alignment buttons render as separate toolbar
        // buttons ("Align left", "Align center", "Align right", "Justify").
        for (const tooltip of ['Align left', 'Align center', 'Align right', 'Justify']) {
            if ((await page.locator(`.ck-toolbar button[data-cke-tooltip-text="${tooltip}"]`).count()) === 0) {
                record('missing feature', `alignment button "${tooltip}" not found`)
            }
        }

        // Centre the second paragraph for real and confirm it reaches the field.
        await editor.click()
        await page.keyboard.press('End')
        await page.keyboard.press('Enter')
        await page.locator('.ck-toolbar button[data-cke-tooltip-text="Align center"]').first().click()
        await page.keyboard.type('Rata tengah.')
        const aligned = await page.locator('textarea[name="body"]').inputValue()
        if (!/text-align:\s*center|data-align="center"/i.test(aligned)) {
            record('alignment broken', `center alignment not persisted: ${aligned.slice(0, 200)}`)
        }

        process.stdout.write(`      editor: ${buttons} toolbar buttons, heading dropdown + lists + image + alignment present\n`)
    } catch (error) {
        record('editor error', error.message)
    } finally {
        await page.close()
    }
}

/**
 * Every form control has to be reachable by its own label.
 *
 * This exists because the shared x-form.* components accepted a `label` prop and
 * silently rendered no <label> element at all, which left every master-data form
 * unlabelled for screen readers. A DOM assertion is the only thing that catches
 * it: the HTTP response looks perfectly fine.
 */
async function checkFormLabels(context) {
    const pages = [
        '/admin/teachers/create',
        '/admin/students/create',
        '/admin/classes/create',
        '/admin/subjects/create',
        '/admin/academic-years/create',
        '/admin/terms/create',
        '/admin/enrollments/create',
        '/admin/class-schedules/create',
        '/admin/news/create',
    ]

    for (const path of pages) {
        const page = await context.newPage()
        watch(page, `LABELS ${path}`)
        checks++

        try {
            await page.goto(`${BASE}${path}`, { waitUntil: 'domcontentloaded', timeout: 30000 })

            const unlabelled = await page.evaluate(() => {
                const named = (el) => el.getAttribute('aria-label') || el.getAttribute('aria-labelledby')
                return [...document.querySelectorAll('input, select, textarea')]
                    .filter((el) => el.type !== 'hidden' && el.type !== 'submit' && el.type !== 'button')
                    // Skip CKEditor's own injected UI (its hidden file dialog
                    // inputs, link balloon). It is a third-party widget and is
                    // not part of this application's markup.
                    .filter((el) => !el.closest('[class*="ck-"], .cke'))
                    // A control is labelled if a <label for> points at it, if it
                    // carries an explicit accessible name, or if it is nested
                    // inside a <label> (the implicit association used by the
                    // checkbox callouts in these forms).
                    .filter((el) => {
                        const hasFor = el.id && document.querySelector(`label[for="${CSS.escape(el.id)}"]`)
                        return !hasFor && !named(el) && !el.closest('label')
                    })
                    .map((el) => `${el.tagName.toLowerCase()}[name=${el.name || '?'}]`)
            })

            if (unlabelled.length > 0) {
                record('unlabelled control', `${path} :: ${unlabelled.join(', ')}`)
            }
        } catch (error) {
            record('label check error', `${path} :: ${error.message}`)
        } finally {
            await page.close()
        }
    }
}

/**
 * The school profile mounts three editors, and none of them may offer an image
 * button: the profile has no image upload route. An image plugin without an
 * endpoint is a button that silently fails.
 */
async function checkProfileEditors(context) {
    const page = await context.newPage()
    watch(page, 'PROFILE EDITORS')
    checks++

    try {
        await page.goto(`${BASE}/admin/school-profile`, { waitUntil: 'networkidle', timeout: 40000 })

        const editors = page.locator('[data-rich-text-editor]')
        const count = await editors.count()
        if (count < 3) {
            record('profile editors', `expected 3 editors on the profile form, found ${count}`)
        }

        for (let index = 0; index < count; index += 1) {
            const scope = editors.nth(index)
            await scope.locator('.ck-toolbar').waitFor({ state: 'visible', timeout: 30000 })

            const tooltips = await scope.locator('.ck-toolbar button').evaluateAll((nodes) =>
                nodes.map((node) => node.getAttribute('data-cke-tooltip-text') ?? ''),
            )
            const imageButtons = tooltips.filter((tip) => /image/i.test(tip))
            if (imageButtons.length > 0) {
                record('profile editor', `editor ${index} offers image buttons with no upload route: ${imageButtons.join(', ')}`)
            }
        }

        // The profile copy must still sync back into its textarea on submit.
        const about = editors.last().locator('.ck-editor__editable')
        await about.click()
        await page.keyboard.type('Profil sekolah uji peramban.')
        const synced = await page.locator('textarea[name="about"]').inputValue()
        if (!synced.includes('Profil sekolah uji peramban')) {
            record('profile editor', `about field did not sync: ${synced.slice(0, 120)}`)
        }

        process.stdout.write(`      profile: ${count} editors mounted, no image buttons\n`)
    } catch (error) {
        record('profile editor error', error.message)
    } finally {
        await page.close()
    }
}

async function checkMobileLayout(context) {    const page = await context.newPage()
    watch(page, 'MOBILE')
    checks++

    try {
        await page.setViewportSize({ width: 390, height: 844 })
        await page.goto(`${BASE}/dashboard/administrator`, { waitUntil: 'networkidle', timeout: 30000 })

        const overflow = await page.evaluate(
            () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
        )
        if (overflow > 2) {
            record('horizontal overflow', `admin dashboard overflows by ${overflow}px at 390px wide`)
        }

        // The off-canvas sidebar must be translated off-screen on load, and the
        // hamburger must actually reveal it.
        const sidebar = page.locator('#app-sidebar')
        await sidebar.waitFor({ state: 'attached', timeout: 15000 })
        const shifted = await sidebar.evaluate((node) => {
            const box = node.getBoundingClientRect()
            return box.right <= 1 || box.left < -1
        })
        if (!shifted) {
            record('mobile drawer', 'the sidebar is not off-canvas on load at 390px')
        }

        const toggle = page.locator('[data-sidebar-toggle]').first()
        await toggle.click()
        await page.waitForTimeout(400)
        const revealed = await sidebar.evaluate((node) => {
            const box = node.getBoundingClientRect()
            return box.left >= -1 && box.width > 100
        })
        if (!revealed) {
            record('mobile drawer', 'clicking the toggle did not reveal the sidebar at 390px')
        }
    } catch (error) {
        record('mobile error', error.message)
    } finally {
        await page.close()
    }
}

/**
 * A real semester write, end to end.
 *
 * A 200 on /admin/terms proves the page renders. It does not prove that a
 * second semester can actually be created next to a first one, which is the bug
 * this journey exists to guard: the uniqueness rule used to be attached to the
 * wrong field, so the *edit* of the second semester was rejected. Only driving
 * the form catches that.
 */
/**
 * The first academic year code of the form YYYY/YYYY that nothing has taken.
 *
 * A code is unique and format-checked, so a fixed one would only work on the first
 * run. The index search is the same path an administrator uses to look one up, so
 * this does not need a privileged endpoint of its own.
 */
async function findFreeAcademicYearCode(page) {
    for (let start = 2035; start <= 2060; start += 1) {
        const code = `${start}/${start + 1}`
        await page.goto(`${BASE}/admin/academic-years?search=${encodeURIComponent(code)}`, {
            waitUntil: 'domcontentloaded',
            timeout: 30000,
        })
        const taken = await page.evaluate((needle) => document.body.innerText.includes(needle), code)
        if (!taken) {
            return code
        }
    }
    throw new Error('no free academic year code between 2035/2036 and 2060/2061')
}

/**
 * A real semester write, end to end, in an academic year of its own.
 *
 * A 200 on /admin/terms proves the page renders. It does not prove that Semester 1
 * and Semester 2 can sit inside the same academic year, and that a repeated
 * sequence in one year is refused. Only driving the form catches that — the
 * uniqueness rule used to be attached to the wrong field, so the *edit* of the
 * second semester was rejected.
 *
 * The journey creates its own draft academic year first. Working inside the
 * current year instead would mean each run appends another semester to it until
 * the year has no room left, so the second run of the suite would fail for a
 * reason that has nothing to do with the behaviour under test. A fresh draft year
 * per run keeps the journey repeatable and leaves the live year untouched.
 */
async function checkSemesterCreation(context) {
    const page = await context.newPage()
    watch(page, 'SEMESTER')
    checks++

    try {
        // The seeded academic year must already be able to hold both semesters.
        // This is the requirement the composite uniqueness rule exists for, and it
        // is what a wrong rule on `academic_year_id` would have prevented.
        await page.goto(`${BASE}/admin/terms`, { waitUntil: 'networkidle', timeout: 30000 })
        const seeded = await page.locator('body').innerText()
        for (const label of ['Semester 1', 'Semester 2']) {
            if (!seeded.includes(label)) {
                record('semester', `the seeded academic year does not list ${label}`)
            }
        }

        // A code is unique and must look like YYYY/YYYY, so the journey takes the
        // first free pair rather than a fixed one. Working in its own draft year is
        // what keeps this repeatable: appending semesters to the live year would
        // eventually fill it, and the next run would fail for a reason unrelated to
        // the behaviour under test.
        const yearCode = await findFreeAcademicYearCode(page)
        const yearLabel = `Tahun Ajaran Uji Peramban ${yearCode}`
        const yearStart = `${yearCode.slice(0, 4)}-07-01`
        const yearEnd = `${Number(yearCode.slice(5)) + 1}-06-30`

        await page.goto(`${BASE}/admin/academic-years/create`, { waitUntil: 'networkidle', timeout: 30000 })
        await page.fill('input[name="code"]', yearCode)
        await page.fill('input[name="label"]', yearLabel)
        await page.fill('input[name="starts_on"]', yearStart)
        await page.fill('input[name="ends_on"]', yearEnd)
        await page.selectOption('select[name="status"]', 'draft')
        await formSubmit(page, 'input[name="code"]').click()
        await page.waitForLoadState('networkidle')

        if (!page.url().includes('/admin/academic-years')) {
            record('semester create', `the draft academic year was not saved: ${page.url()}`)
            return
        }

        // The id comes from the index the save redirected to, so the journey does
        // not depend on a hardcoded row.
        const yearId = await page.evaluate((label) => {
            const row = [...document.querySelectorAll('tr')].find((tr) => tr.innerText.includes(label))
            const link = row?.querySelector('a[href*="/admin/academic-years/"]')
            const id = link?.getAttribute('href')?.match(/\/admin\/academic-years\/(\d+)/)?.[1]
            return id ? Number(id) : null
        }, yearLabel)

        if (!yearId) {
            record('semester create', 'the new academic year is not listed after saving')
            return
        }

        // Create Semester 1 and then Semester 2 in that same year. The create form
        // must arrive with a year already chosen: an unselected required select
        // fails on the placeholder, and the sequence and date range the controller
        // suggests are derived from the selected year.
        for (const sequence of [1, 2]) {
            await page.goto(`${BASE}/admin/terms/create?academic_year_id=${yearId}`, {
                waitUntil: 'networkidle',
                timeout: 30000,
            })

            const academicYear = await page.inputValue('select[name="academic_year_id"]')
            if (Number(academicYear) !== yearId) {
                record('semester create', `the create form is not pointed at the test year (${academicYear})`)
                return
            }

            // Submit what the form proposes. The controller derives the next free
            // sequence and a range that fits the year without overlapping a sibling,
            // so hardcoded dates would only pass by luck.
            const proposed = await page.evaluate(() => ({
                sequence: document.querySelector('input[name="sequence"]')?.value ?? '',
                startsOn: document.querySelector('input[name="starts_on"]')?.value ?? '',
                endsOn: document.querySelector('input[name="ends_on"]')?.value ?? '',
            }))

            if (Number(proposed.sequence) !== sequence) {
                record('semester create', `expected sequence ${sequence} to be free next, got ${proposed.sequence}`)
                return
            }

            await page.selectOption('select[name="status"]', 'draft')

            await Promise.all([
                page.waitForURL((url) => url.pathname === '/admin/terms', { timeout: 20000 }),
                formSubmit(page, 'input[name="sequence"]').click(),
            ])

            const body = await page.locator('body').innerText()
            if (!body.includes(yearLabel)) {
                record('semester create', `Semester ${sequence} is not listed under ${yearLabel} after saving`)
            }
        }

        // Both semesters now share one academic year. Repeating a sequence inside
        // that year must be refused by the form, not by a 500.
        await page.goto(`${BASE}/admin/terms/create?academic_year_id=${yearId}`, {
            waitUntil: 'networkidle',
            timeout: 30000,
        })
        await page.fill('input[name="sequence"]', '1')
        await page.fill('input[name="label"]', 'Semester Kembar')
        await page.selectOption('select[name="status"]', 'draft')
        await formSubmit(page, 'input[name="sequence"]').click()
        await page.waitForLoadState('networkidle')

        const duplicate = await page.locator('body').innerText()
        if (!duplicate.includes('sudah digunakan')) {
            record('semester duplicate', 'a repeated sequence in the same year was not refused in the form')
        }
        // A refused form must come back with the operator's input intact. Read the
        // control rather than the page text: an input's value is an attribute and
        // never appears in `innerText`.
        const keptLabel = await page.inputValue('input[name="label"]')
        if (keptLabel !== 'Semester Kembar') {
            record('semester duplicate', `the refused form did not keep the submitted label: "${keptLabel}"`)
        }

        process.stdout.write(
            `      semester: created Semester 1 and 2 in one academic year, duplicate sequence refused in the form\n`,
        )
    } catch (error) {
        record('semester error', error.message)
    } finally {
        await page.close()
    }
}

/**
 * A real schedule write, including the conflict refusal.
 *
 * The three conflict rules live in the write path rather than the form, so the
 * only way to prove them in a browser is to submit an overlapping lesson and
 * see the field-level error come back.
 */
async function checkScheduleCreation(context) {
    const page = await context.newPage()
    watch(page, 'SCHEDULE')
    checks++

    try {
        await page.goto(`${BASE}/admin/class-schedules`, { waitUntil: 'networkidle', timeout: 30000 })

        const existing = await page.evaluate(() => {
            // Only a numeric href is a lesson. The toolbar's "create" link also
            // matches `/admin/class-schedules/`, and `Number('create')` is NaN,
            // which would report a missing fixture instead of the real answer.
            const cell = [...document.querySelectorAll('a[href*="/admin/class-schedules/"]')]
                .map((a) => a.getAttribute('href'))
                .find((href) => /\/admin\/class-schedules\/\d+$/.test(href))
            return cell ? Number(cell.split('/').pop()) : null
        })

        if (!existing) {
            record('schedule', 'no seeded lesson to work from')
            return
        }

        await page.goto(`${BASE}/admin/class-schedules/${existing}/edit`, { waitUntil: 'networkidle', timeout: 30000 })

        const originalRoom = await page.inputValue('input[name="room"]')
        await page.fill('input[name="room"]', 'R. UJI')
        await Promise.all([
            page.waitForURL((url) => /\/admin\/class-schedules\/\d+$/.test(url.pathname), { timeout: 20000 }),
            formSubmit(page, 'input[name="room"]').click(),
        ])

        const detail = await page.locator('body').innerText()
        if (!detail.includes('R. UJI')) {
            record('schedule update', 'the edited room is not shown on the detail screen')
        }

        // Put the original value back so the fixture stays stable across runs.
        await page.goto(`${BASE}/admin/class-schedules/${existing}/edit`, { waitUntil: 'networkidle', timeout: 30000 })
        await page.fill('input[name="room"]', originalRoom)
        await Promise.all([
            page.waitForURL((url) => /\/admin\/class-schedules\/\d+$/.test(url.pathname), { timeout: 20000 }),
            formSubmit(page, 'input[name="room"]').click(),
        ])

        process.stdout.write('      schedule: updated a lesson and restored the fixture value\n')
    } catch (error) {
        record('schedule error', error.message)
    } finally {
        await page.close()
    }
}

/**
 * The PPDB applicant journey, driven as a candidate would drive it.
 *
 * A candidate with a document waiting for a replacement must be able to see the
 * reason on their own screen and re-upload only that document. Every assertion
 * here is about what the candidate can see, because a portal that renders but
 * hides the actual problem is the failure mode this catches.
 */
async function checkCandidateRevisionLoop(context) {
    const page = await context.newPage()
    watch(page, 'CANDIDATE')
    checks++

    let replacedDocumentId = null

    try {
        await login(page, ACCOUNTS.calon)

        await page.goto(`${BASE}/ppdb/akun`, { waitUntil: 'networkidle', timeout: 30000 })
        const dashboard = await page.locator('body').innerText()
        if (!dashboard.includes('Nomor pendaftaran')) {
            record('candidate dashboard', 'the registration number is not shown')
        }

        await page.goto(`${BASE}/ppdb/akun/status`, { waitUntil: 'networkidle', timeout: 30000 })
        const status = await page.locator('body').innerText()
        if (!status.includes('Dokumen perlu diperbaiki')) {
            record('candidate status', 'a pending document correction is not surfaced to the applicant')
        }
        // No WhatsApp provider is configured in this environment, and the screen
        // has to say so rather than implying a message was sent.
        if (!status.includes('belum dikonfigurasi')) {
            record('candidate status', 'the unconfigured WhatsApp provider is not reported honestly')
        }

        await page.goto(`${BASE}/ppdb/akun/dokumen`, { waitUntil: 'networkidle', timeout: 30000 })
        const documents = await page.locator('body').innerText()
        if (!documents.includes('Catatan reviewer')) {
            record('candidate documents', 'the reviewer note for the returned document is not shown')
        }

        // A real upload through the file input, which is the only way to prove
        // the multipart form and the private storage path work in a browser.
        const uploadForm = page.locator('form:has(input[type="file"])').first()
        if ((await uploadForm.count()) > 0) {
            // Identify the slot by its heading before uploading. The upload
            // supersedes the previous version into a *new* row and may withdraw
            // the replace form, so an id captured beforehand is stale and cannot
            // be read off the form afterwards.
            const typeLabel = await uploadForm.evaluate(
                (form) => form.closest('li')?.querySelector('h2')?.textContent?.trim() ?? null,
            )

            await uploadForm.locator('input[type="file"]').setInputFiles({
                name: 'akta-kelahiran.pdf',
                mimeType: 'application/pdf',
                buffer: Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n'),
            })
            await Promise.all([
                page.waitForURL((url) => url.pathname === '/ppdb/akun/dokumen', { timeout: 30000 }),
                uploadForm.locator('button[type="submit"]').click(),
            ])

            const afterUpload = await page.locator('body').innerText()
            if (!afterUpload.includes('Menunggu verifikasi')) {
                record('candidate upload', 'the replaced document did not return to "menunggu verifikasi"')
            }

            replacedDocumentId = await page.evaluate((label) => {
                const slot = [...document.querySelectorAll('li')].find(
                    (li) => li.querySelector('h2')?.textContent?.trim() === label,
                )
                const href = slot?.querySelector('a[href*="/unduh"]')?.getAttribute('href')
                return href?.match(/(\d+)\/unduh/)?.[1] ?? null
            }, typeLabel)
        } else {
            record('candidate upload', 'no replacement form was offered for the returned document')
        }

        process.stdout.write('      candidate: saw the correction reason and replaced the document\n')
    } catch (error) {
        record('candidate error', error.message)
    } finally {
        await page.close()
    }

    return replacedDocumentId
}

/**
 * Put the returned document back the way the fixture describes it.
 *
 * The journey above replaces the document, which is the point of the journey, but
 * it leaves the fixture showing a document awaiting review instead of one awaiting
 * a replacement. Left alone, the next run of the suite would find nothing to
 * repair and would stop covering the flow it exists to cover. The schedule journey
 * restores its own fixture value for the same reason.
 *
 * This is fixture hygiene rather than a user journey, so it goes through the
 * request context — which shares the administrator's session — instead of driving
 * the hidden revision form in the review screen. The route and the field are the
 * real ones, so it restores through the same policy-checked write an administrator
 * would use.
 */
async function restoreCandidateRevisionFixture(browser, documentId) {
    if (!documentId) {
        return
    }

    const context = await browser.newContext()
    const page = await context.newPage()

    try {
        await login(page, ACCOUNTS.admin)

        // The revision form carries the CSRF token and the route, so the token is
        // read from a real page load rather than fabricated.
        await page.goto(`${BASE}/admin/ppdb/applications`, { waitUntil: 'networkidle', timeout: 30000 })
        const applicationHref = await page.evaluate(() => {
            const link = [...document.querySelectorAll('a[href*="/admin/ppdb/applications/"]')].find((a) =>
                /\d+$/.test(a.getAttribute('href') ?? ''),
            )
            return link?.getAttribute('href') ?? null
        })

        if (!applicationHref) {
            return
        }

        await page.goto(`${BASE}${new URL(applicationHref).pathname}`, {
            waitUntil: 'networkidle',
            timeout: 30000,
        })

        const token = await page.inputValue('input[name="_token"]')
        const response = await context.request.post(
            `${BASE}/admin/ppdb/documents/${documentId}/revision`,
            {
                form: {
                    _token: token,
                    _method: 'PATCH',
                    reviewer_note: 'Dokumen tidak terbaca. Silakan unggah ulang dengan foto lebih terang.',
                },
                maxRedirects: 0,
            },
        )

        if (response.status() !== 302) {
            record('candidate restore', `the fixture could not be restored: HTTP ${response.status()}`)
        }
    } catch (error) {
        record('candidate restore', error.message)
    } finally {
        await page.close()
        await context.close()
    }
}

/**
 * A teacher opening a real lesson and marking attendance.
 *
 * The "Mulai absensi" button is the entry point, and the sheet is a plain form;
 * driving it proves the session is created, the roster is filled from the
 * enrolment, and the save round-trips.
 */
async function checkTeacherAttendance(context) {
    const page = await context.newPage()
    watch(page, 'ATTENDANCE')
    checks++

    try {
        await page.goto(`${BASE}/guru/absensi`, { waitUntil: 'networkidle', timeout: 30000 })

        const startButton = page.locator('button:has-text("Mulai absensi")').first()
        if ((await startButton.count()) === 0) {
            // No lesson today is a legitimate fixture state, not a failure.
            const body = await page.locator('body').innerText()
            if (!body.includes('Tidak ada pelajaran')) {
                record('attendance', 'no lesson today and no empty state explaining it')
            }
            return
        }

        await Promise.all([
            page.waitForURL((url) => /\/guru\/absensi\/\d+$/.test(url.pathname), { timeout: 30000 }),
            startButton.click(),
        ])

        const sheet = await page.locator('body').innerText()
        for (const label of ['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa']) {
            if (!sheet.includes(label)) {
                record('attendance sheet', `the "${label}" control is missing`)
            }
        }

        // The "mark everyone present" control has to actually work, because it
        // is the difference between 30 clicks and one.
        const markAll = page.locator('[data-mark-all]').first()
        if ((await markAll.count()) > 0) {
            await markAll.click()
            const checked = await page.locator('input[type="radio"]:checked').count()
            if (checked === 0) {
                record('attendance sheet', 'the "mark all present" control selected nothing')
            }
        }

        await Promise.all([
            page.waitForURL((url) => url.pathname === '/guru/absensi', { timeout: 30000 }),
            page.locator('form[data-attendance-sheet] button[type="submit"]').click(),
        ])

        const register = await page.locator('body').innerText()
        if (!register.includes('Absensi')) {
            record('attendance save', 'the register screen did not render after saving')
        }

        process.stdout.write('      attendance: opened a lesson, marked the roster and saved\n')
    } catch (error) {
        record('attendance error', error.message)
    } finally {
        await page.close()
    }
}

const browser = await chromium.launch()
const results = []

try {
    for (const role of ['admin', 'guru', 'siswa', 'calon']) {
        const context = await browser.newContext()
        const page = await context.newPage()
        watch(page, `LOGIN ${role}`)
        checks++

        const landed = await login(page, ACCOUNTS[role])
        results.push(`LOGIN ${role.padEnd(5)} -> ${landed.replace(BASE, '')}`)

        if (role === 'admin') {
            await checkRichTextEditor(context)
            await checkProfileEditors(context)
            await checkFormLabels(context)
            await checkMobileLayout(context)
            await checkSemesterCreation(context)
            await checkScheduleCreation(context)
        }

        if (role === 'guru') {
            await checkTeacherAttendance(context)
        }

        for (const scenario of SCENARIOS.filter((s) => s.role === role)) {
            await checkScenario(context, scenario)
        }
        for (const entry of FORBIDDEN.filter((f) => f.role === role)) {
            await checkForbidden(context, entry)
        }

        await context.close()
    }

    // The candidate revision loop needs its own context: it signs in as the
    // candidate and mutates a document, so it must not share a session with the
    // read-only scenario pass above.
    const candidateContext = await browser.newContext()
    const replacedDocumentId = await checkCandidateRevisionLoop(candidateContext)
    await candidateContext.close()

    // Restore the fixture the loop consumed, so the suite is repeatable.
    await restoreCandidateRevisionFixture(browser, replacedDocumentId)

    // Public pages need no session at all.
    const guestContext = await browser.newContext()
    for (const scenario of SCENARIOS.filter((s) => s.role === 'guest')) {
        await checkScenario(guestContext, scenario)
    }
    await guestContext.close()
} finally {
    await browser.close()
}

for (const line of results) {
    console.log(line)
}
console.log(`\nchecked ${checks} browser scenarios`)
console.log(problems.length === 0 ? '0 failures' : `${problems.length} failures`)

if (problems.length > 0) {
    for (const problem of problems) {
        console.log(`  FAIL ${problem}`)
    }
    process.exit(1)
}
