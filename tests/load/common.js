/**
 * Sekolah Kita — shared k6 configuration and helpers.
 *
 * Thresholds are pass/fail conditions, not observations. They are defined here
 * once so every scenario is judged against the same bar. Lowering a threshold
 * to make a run pass converts the harness into decoration; if a target is
 * genuinely wrong, change it deliberately and record who accepted it and why.
 */

// ---------------------------------------------------------------------------
// Target
// ---------------------------------------------------------------------------

export const BASE_URL = __ENV.BASE_URL || 'https://staging.sekolah.example';

export const THRESHOLDS = {
  // Cache-hit public reads: the number a visitor actually feels.
  publicP95: 300,
  publicP99: 1000,

  // Authenticated pages hit the session store and MariaDB on every request.
  authP95: 500,

  // PPDB submission is a multi-row transaction.
  writeP95: 1500,

  // A PHP error page is a failure, not a slow success.
  errorRate: 0.01,

  // If public reads are not served from cache the worker pool cannot scale.
  // Not directly measurable until the application emits an X-Cache header;
  // see the README.
  cacheHitRate: 0.8,
};

// ---------------------------------------------------------------------------
// Custom metrics
// ---------------------------------------------------------------------------

export const publicP95 = new Trend('public_latency', true);
export const authP95 = new Trend('auth_latency', true);
export const writeP95 = new Trend('write_latency', true);
export const appErrorRate = new Rate('app_error_rate');
export const serverErrors = new Counter('server_errors');
export const cacheHits = new Counter('cache_hits');
export const cacheMisses = new Counter('cache_misses');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

export function headers(extra = {}) {
  return {
    Accept: 'text/html,application/xhtml+xml,application/json',
    'X-Request-Id': `k6-${__VU}-${__ITER}`,
    ...extra,
  };
}

export function formHeaders(extra = {}) {
  return headers({ 'Content-Type': 'application/x-www-form-urlencoded', ...extra });
}

/**
 * Classify a response and record it against the right trend.
 *
 * A status of 0 means the request never completed (connection reset, timeout).
 * That is a failure, and treating it as a pass is the single easiest way to
 * produce a load test that reports success while the site is down.
 */
export function record(res, trend) {
  const failed = res.status === 0 || res.status >= 500;

  if (failed) {
    serverErrors.add(1);
  }
  appErrorRate.add(failed);

  if (res.headers['X-Cache'] === 'HIT') {
    cacheHits.add(1);
  } else if (res.headers['X-Cache'] === 'MISS') {
    cacheMisses.add(1);
  }

  if (typeof res.timings.duration === 'number') {
    trend.add(res.timings.duration);
  }

  return !failed;
}

export function checkSecurityHeaders(res) {
  check(res, {
    'X-Content-Type-Options is nosniff': (r) =>
      r.headers['X-Content-Type-Options'] === 'nosniff',
    'X-Frame-Options denies framing': (r) => r.headers['X-Frame-Options'] === 'DENY',
    'HSTS is present': (r) => r.headers['Strict-Transport-Security'] !== undefined,
    'no PHP error page': (r) => !String(r.body || '').includes('Whoops'),
    'no vendor path disclosure': (r) => !String(r.body || '').includes('vendor/laravel'),
  });
}

/**
 * Think time.
 *
 * Browsing with no delay measures a far more brutal request rate than any
 * visitor generates, and reports failures nobody would experience. The spread
 * keeps arrival from being perfectly uniform, which is closer to reality.
 */
export function humanThink(min = 0.5, max = 3) {
  sleep(min + Math.random() * (max - min));
}

export function thinkHeavy() {
  humanThink(3, 10);
}

export function cookieHeader(cookies) {
  return Object.values(cookies || {})
    .filter((c) => c && c.value)
    .map((c) => `${c.name}=${c.value}`)
    .join('; ');
}

/** Extract the CSRF token from a rendered form. */
export function csrfFrom(html) {
  const match = /name="_token"[^>]*value="([^"]+)"/.exec(html || '');
  return match ? match[1] : '';
}

export function pick(list) {
  return list[Math.floor(Math.random() * list.length)];
}

/** Standard threshold block so every scenario is judged the same way. */
export function standardThresholds() {
  return {
    'public_latency': [`p(95)<${THRESHOLDS.publicP95}`],
    'auth_latency': [`p(95)<${THRESHOLDS.authP95}`],
    'write_latency': [`p(95)<${THRESHOLDS.writeP95}`],
    'app_error_rate': [`rate<${THRESHOLDS.errorRate}`],
    http_req_failed: [`rate<${THRESHOLDS.errorRate}`],
    checks: ['rate>0.99'],
  };
}
