# Browser verification

`tests/Browser/smoke.mjs` drives a real Chromium against a running server. It
exists because an HTTP status check cannot tell you whether CKEditor mounted,
whether a toolbar button responds, or whether the browser logged an error while
the page hydrated. Every defect it found in this project was invisible to the
PHP test suite.

## Running it

```bash
php artisan serve --host=127.0.0.1 --port=8123
npm run build
php artisan sekolah:seed-demo          # the three fixture logins
npm run test:e2e                       # or: node tests/Browser/smoke.mjs <baseUrl>
```

The script exits non-zero on any failure, so it is usable as a check.

## What it asserts

- **Login and role redirects** for administrator, guru and siswa.
- **Every admin, teacher, student and public screen** renders its expected
  content with no page error, no console error, no failed request and no 5xx.
- **Authorization is enforced server-side**: guru and siswa are refused from
  administrator routes, an administrator is refused from teacher routes, and
  guests are redirected.
- **CKEditor really works**: the toolbar mounts, the textarea is hidden only
  afterwards, typing reaches the submitted field as HTML, Bold applies through a
  real toolbar click, and all four alignment buttons persist their alignment.
- **The school profile editors** mount three times and offer no image button,
  because the profile has no image upload route.
- **Every form control is labelled**, either by `<label for>`, an explicit
  accessible name, or an enclosing `<label>`.
- **Mobile layout** at 390px: no horizontal overflow, the sidebar starts
  off-canvas, and the toggle reveals it.

## Filtered noise

Two console messages are ignored on purpose, and neither is a reason to relax
the application:

- `laravel/boost` injects an inline browser logger. It is a `require-dev`
  package that registers only when `app()->environment('local')` or debug is on,
  and the app ships a strict `script-src 'self'`, so the browser blocks it. It
  is absent from a production install.
- A 403 is logged by the browser as a console error. On the FORBIDDEN scenarios
  that 403 is the correct result.

## Running Chromium without root

On a host where `apt-get install` is unavailable, Chromium still needs about
fifteen shared libraries. They can be fetched unprivileged and unpacked into a
private prefix:

```bash
mkdir -p /tmp/chromium-libs /tmp/debs && cd /tmp/debs
apt-get download libglib2.0-0t64 libnss3 libnspr4 libatk1.0-0t64 libatk-bridge2.0-0t64 \
    libcups2t64 libdrm2 libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 \
    libgbm1 libasound2t64 libatspi2.0-0t64 libxext6 libx11-6 libxcb1 libxext6
for d in *.deb; do dpkg-deb -x "$d" /tmp/chromium-libs; done

export LD_LIBRARY_PATH="/tmp/chromium-libs/usr/lib/aarch64-linux-gnu:/tmp/chromium-libs/lib/aarch64-linux-gnu"
npx playwright install chromium
```

Adjust the library list until `ldd` on the Playwright Chromium binary reports no
missing entries. `npx playwright install-deps` does the same job when root is
available.
