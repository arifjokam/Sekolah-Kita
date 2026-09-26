# Load test plan — Sekolah Kita

**Purpose:** a maintainable HTTP load-test harness for the 100–1000 concurrent
user target, plus the acceptance criteria a result must meet.

> ## Nothing in this document is a measurement
>
> **No load test was executed.** The development environment is Android
> PRoot on an ARM64 VPS-less container with no reverse proxy, no TLS, no
> supervised workers, and no production hardware. Numbers produced there would
> describe the emulator, not the platform.
>
> Every latency, throughput, CPU, and saturation figure below is a **target to
> be validated or explicitly waived by the owner** — never a result. The
> "Acceptance criteria" tables define the pass condition; the "Results" tables
> are empty because there are no results yet.

**Tool:** [k6](https://k6.io) — maintained by Grafana Labs, scriptable in
JavaScript, no agent to install on the load host, and it produces a summary
that can be attached to a go/no-go record.

**Placement:** `tests/load/` (see §2). The harness is versioned with the
application so a test can be replayed against a future release.

---

## 1. Prerequisites before any test is meaningful

A test run is only interpretable if the environment is stated. Record all of
these alongside the result; a number without them is not evidence.

```bash
# --- Application ---
php -v
php -m | grep -E '^(redis|igbinary|opcache)$'
php artisan platform:readiness          # MUST print READY
php artisan route:list | wc -l
php artisan config:show cache | grep -E "default|limiter"

# --- Runtime topology ---
php-fpm8.5 -tt | grep -E "pm.max_children|pm = "
nginx -v
mariadb --version
redis-server --version
redis-cli -a "$REDIS_PASSWORD" info clients | grep connected_clients

# --- Host capacity — the numbers the sizing formulas need ---
nproc
free -m
df -h /var/lib/mysql /var/lib/redis
cat /sys/fs/cgroup/memory.max 2>/dev/null || true   # container limit, if any

# --- Warm the caches, or measure cold-start, deliberately and say which ---
php artisan platform:warm-cache --sync
```

**The load host must not be the application host.** Running k6 on the same
4 vCPU box competes for the same cores and will understate capacity by a large
and hard-to-quantify margin. Use a separate machine, ideally with more cores
than the target host, or subtract the load generator's measured overhead.

## 2. Harness layout

```
tests/load/
├── README.md
├── common.js              # thresholds, helpers, config, correlation
├── smoke.js               # correctness before performance
├── public-read.js         # cache-hit public traffic
├── public-mixed.js        # realistic mix incl. cache misses
├── auth.js                # login + authenticated dashboard
├── ppdb.js                # public PPDB flow (rate-limit aware)
└── scenarios/
    └── concurrency.js     # staged 100 → 250 → 500 → 750 → 1000
```

## 3. Common configuration

```javascript
// tests/load/common.js
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { randomItem } from './data.js';

export const BASE_URL = __ENV.BASE_URL || 'https://staging.sekolah.example';

// Thresholds are pass/fail, not observations. Adjust only with a recorded
// owner decision — lowering a threshold to make a run pass converts the test
// into decoration.
export const THRESHOLDS = {
  // p95 for cache-hit public reads. This is the number a visitor feels.
  publicP95:      300,
  publicP99:     1000,
  // Authenticated pages touch the session store and the database every time.
  authP95:        500,
  // Writes: PPDB submission is a multi-row transaction.
  writeP95:      1500,
  // A page that renders a PHP error is a failure, not a slow success.
  errorRate:     0.01,
  // A cache hit that misses the target means the cache is not doing its job.
  cacheHitRate:  0.80,
};

export const publicP95  = new Trend('public_p95',  true);
export const authP95    = new Trend('auth_p95',    true);
export const errorRate  = new Rate('app_error_rate');
export const cacheHits  = new Counter('cache_hits');
export const serverErrors = new Counter('server_errors');

export function headers() {
  return {
    'Content-Type': 'application/json',
    'X-Request-Id': `k6-${__VU}-${__ITER}`,
    'Accept':       'text/html,application/xhtml+xml',
  };
}

/**
 * Records a result and classifies it.
 *
 * `X-Cache` is not emitted by the application today. Until it is, cache hit
 * ratio must be inferred from the admin cache warmer plus a Redis key count
 * (see §7), and the hit-rate threshold cannot be evaluated directly.
 */
export function record(res, trend) {
  const ok = res.status === 200;
  const failed = res.status >= 500 || res.status === 0;

  if (failed) {
    serverErrors.add(1);
    errorRate.add(true);
  } else {
    errorRate.add(false);
  }

  if (res.headers['X-Cache'] === 'HIT') {
    cacheHits.add(1);
  }

  trend.add(res.timings.duration);

  check(res, {
    'status is 200':      (r) => r.status === 200,
    'no PHP warning':     (r) => !String(r.body || '').includes('Whoops'),
    'no stack trace':     (r) => !String(r.body || '').includes('vendor/laravel'),
    'security headers':   (r) => r.headers['X-Content-Type-Options'] === 'nosniff'
                              && r.headers['X-Frame-Options'] === 'DENY',
    'HSTS present':       (r) => r.headers['Strict-Transport-Security'] !== undefined,
  });

  return ok;
}

/**
 * Thinks like a person. Browsing without think time measures an unrealistically
 * brutal RPS and will report a failure that no real visitor experiences.
 */
export function humanThink() {
  sleep(randomItem([0.5, 1, 1.5, 2, 3]));
}
```

## 4. Smoke test — run first, always

Correctness gates performance. A fast test that silently 404s is worthless.

```javascript
// tests/load/smoke.js
import http from 'k6/http';
import { check } from 'k6';
import { BASE_URL, headers, THRESHOLDS } from './common.js';

export const options = {
  vus: 1,
  iterations: 1,
  thresholds: {
    http_req_failed: [`rate<${THRESHOLDS.errorRate}`],
  },
};

const PUBLIC_PAGES = [
  '/',
  '/berita',
  '/prestasi',
  '/galeri',
  '/guru',
  '/profil-sekolah',
  '/ppdb',
  '/kontak',
  '/login',
  '/health',
  '/health/ready',
];

export default function () {
  for (const path of PUBLIC_PAGES) {
    const res = http.get(`${BASE_URL}${path}`, { headers: headers() });

    check(res, {
      [`${path} returns 200`]: (r) => r.status === 200,
      [`${path} has no error`]: (r) => !String(r.body).includes('Whoops'),
    });
  }
}
```

```bash
k6 run tests/load/smoke.js
```

A non-zero error rate here is a **release blocker**; do not proceed to load
testing a build that cannot pass smoke.

## 5. Scenario: public cache-hit traffic

The dominant real-world pattern for a school site.

```javascript
// tests/load/public-read.js
import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL, headers, publicP95, THRESHOLDS, humanThink } from './common.js';
import { PAGES, DETAIL_SLUGS } from './data.js';

export const options = {
  scenarios: {
    public_read: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '2m', target: 100 },
        { duration: '5m', target: 100 },   // hold — steady state at 100
        { duration: '3m', target: 250 },
        { duration: '5m', target: 250 },
        { duration: '3m', target: 500 },
        { duration: '5m', target: 500 },
        { duration: '3m', target: 750 },
        { duration: '5m', target: 750 },
        { duration: '3m', target: 1000 },
        { duration: '10m', target: 1000 }, // hold at target
        { duration: '2m', target: 0 },     // drain
      ],
    },
  },
  thresholds: {
    'public_p95': [`p(95)<${THRESHOLDS.publicP95}`],
    'app_error_rate': [`rate<${THRESHOLDS.errorRate}`],
    http_req_failed: [`rate<${THRESHOLDS.errorRate}`],
  },
};

export default function () {
  // 80% list/browse, 20% detail — mirrors real navigation.
  if (Math.random() < 0.8) {
    const res = http.get(`${BASE_URL}${PAGES[Math.floor(Math.random() * PAGES.length)]}`,
                        { headers: headers() });
    publicP95.add(res.timings.duration);
  } else {
    const slug = DETAIL_SLUGS[Math.floor(Math.random() * DETAIL_SLUGS.length)];
    const res = http.get(`${BASE_URL}/berita/${slug}`, { headers: headers() });
    publicP95.add(res.timings.duration);
  }

  humanThink();
}
```

### Staging note

Do not jump straight to 1000. Run and record each stage separately
(`--stage public_read` with a single stage, or by trimming `stages`) so a
failure at 500 is attributable rather than inferred. A run that only ever
reports "failed at 1000" has not identified the knee.

## 6. Scenario: mixed traffic

Where the real risk is. Authenticated pages and writes are far more expensive
than cached reads, and they are what saturates PHP-FPM first.

```javascript
// tests/load/public-mixed.js  (abridged: login + dashboard + PPDB read)
import http from 'k6/http';
import { check } from 'k6';
import { BASE_URL, headers, authP95, publicP95, THRESHOLDS, humanThink } from './common.js';

export const options = {
  scenarios: {
    mixed: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '2m', target: 50 },
        { duration: '5m', target: 50 },
        { duration: '3m', target: 100 },
        { duration: '5m', target: 100 },
        { duration: '3m', target: 250 },
        { duration: '5m', target: 250 },
      ],
    },
  },
  thresholds: {
    'auth_p95': [`p(95)<${THRESHOLDS.authP95}`],
    'public_p95': [`p(95)<${THRESHOLDS.publicP95}`],
    app_error_rate: [`rate<${THRESHOLDS.errorRate}`],
  },
};

export function setup() {
  // Seed a synthetic account in the TEST environment only. Never against
  // production data, and never with a real person's credentials.
  const res = http.post(`${BASE_URL}/login`, JSON.stringify({
    email: __ENV.TEST_EMAIL,
    password: __ENV.TEST_PASSWORD,
  }), { headers: headers() });

  check(res, { 'login redirects': (r) => r.status === 302 || r.status === 200 });

  return { session: res.cookies, token: csrfFrom(res) };
}

export default function (data) {
  // Authenticated dashboard: session read + database, not a cache hit.
  const dash = http.get(`${BASE_URL}/dashboard`,
    { headers: { ...headers(), Cookie: cookieHeader(data) } });
  authP95.add(dash.timings.duration);

  // Notifications: exercises the notification listing index.
  const notes = http.get(`${BASE_URL}/notifications`,
    { headers: { ...headers(), Cookie: cookieHeader(data) } });
  authP95.add(notes.timings.duration);

  // Public page interleaved, as a real session would.
  const pub = http.get(`${BASE_URL}/`, { headers: headers() });
  publicP95.add(pub.timings.duration);

  humanThink();
}

function cookieHeader(data) {
  return Object.entries(data.session).map(([k, v]) => `${k}=${v.value}`).join('; ');
}

function csrfFrom(res) {
  const m = /name="_token"[^>]*value="([^"]+)"/.exec(res.body || '');
  return m ? m[1] : '';
}
```

### PPDB write testing is deliberately constrained

`ppdb-submit` is limited to **5 per hour per IP**, `ppdb-status` to 60 per
hour. A load test that honours the limits cannot generate meaningful write
load, and one that ignores them tests a configuration the application will
never run in.

The correct approach: run the write scenario against a **staging
environment** with the rate limits raised by explicit configuration, and
record that the limits were altered. Do not test writes against production
limits by spoofing `X-Forwarded-For` — that also invalidates any result
obtained, because it changes the trust boundary being measured.

## 7. What to collect during a run

Metrics from k6 alone are not enough. Collect these **simultaneously**; a
latency number without saturation data cannot be interpreted.

```bash
# --- PHP-FPM saturation (the primary signal) ---
watch -n 2 'echo "active=$(pgrep -c -f php-fpm) "; \
            curl -s http://127.0.0.1/status?full 2>/dev/null | \
            grep -E "active processes|total processes|listen queue|max listen queue"'

# Simpler and dependency-free:
watch -n 2 'curl -s http://127.0.0.1/fpm-status | grep -E "Active processes|Idle processes|Listen queue"'

# --- Host CPU / memory / IO ---
vmstat 2
mpstat -P ALL 2
iostat -xz 2
free -m

# --- Redis latency and memory ---
redis-cli -a "$REDIS_PASSWORD" --latency-history -i 2
redis-cli -a "$REDIS_PASSWORD" info memory | grep -E "used_memory_human|maxmemory_human"
redis-cli -a "$REDIS_PASSWORD" info stats | grep -E "evicted_keys|keyspace_hits|keyspace_misses"

# --- MariaDB ---
mariadb -e "SHOW FULL PROCESSLIST;"
mariadb -e "SHOW GLOBAL STATUS WHERE Variable_name IN
  ('Threads_connected','Threads_running','Max_used_connections','Slow_queries',
   'Created_tmp_disk_tables','Created_tmp_tables','Innodb_buffer_pool_reads',
   'Innodb_buffer_pool_read_requests','Handler_read_next','Table_locks_waited');"
watch -n 2 'mariadb -e "SELECT TIME, COUNT(*) FROM information_schema.PROCESSLIST
                      WHERE COMMAND <> \"Sleep\" GROUP BY TIME;"'

# --- Queue depth and age ---
redis-cli -a "$REDIS_PASSWORD" -n 2 llen queues:notifications
redis-cli -a "$REDIS_PASSWORD" -n 2 llen queues:media
redis-cli -a "$REDIS_PASSWORD" -n 2 llen queues:maintenance
php artisan queue:failed

# --- Cache effectiveness ---
redis-cli -a "$REDIS_PASSWORD" -n 1 dbsize
redis-cli -a "$REDIS_PASSWORD" info stats | grep keyspace_hits
mariadb -e "SELECT @@slow_query_log, @@long_query_time;"
tail -f /var/log/mysql/slow.log

# --- Application error rate ---
grep -c "ERROR\|CRITICAL" /var/log/sekolah-kita/*.log
```

**Cache hit ratio** cannot currently be measured from the response, because the
application does not emit an `X-Cache` header. Until it does, derive it from
`keyspace_hits / (keyspace_hits + keyspace_misses)` on the cache connection.
Emitting `X-Cache: HIT|MISS` on public responses is a small, safe change and
would make this directly measurable — listed as a follow-up in
`docs/PRODUCTION_CAPACITY_GUIDE.md` §8.

## 8. Acceptance criteria — to be measured, not assumed

Pass requires **all** rows. A partial pass is a partial result and must be
recorded as one.

### Latency and throughput

| Metric | Target | Status |
| --- | --- | --- |
| Public read p95 (cache hit) | < 300 ms | Not measured |
| Public read p95 (cold / after purge) | < 1500 ms | Not measured |
| Public read p99 | < 1000 ms | Not measured |
| Authenticated page p95 | < 500 ms | Not measured |
| PPDB submission p95 | < 1500 ms | Not measured |
| 5xx rate, all scenarios | < 1% | Not measured |
| Sustained throughput at 1000 VU | Recorded, target ≥ 200 rps | Not measured |
| Queue depth under load | Returns to 0 within 5 min | Not measured |

### Saturation

| Signal | Healthy | Unhealthy | Status |
| --- | --- | --- | --- |
| FPM active processes | < 70% of `pm.max_children` at steady state | ≥ 90% sustained | Not measured |
| FPM listen queue | 0 | > 0 sustained | Not measured |
| Host CPU | < 60% sustained | > 85% sustained | Not measured |
| MariaDB `Threads_running` | < 25 | > 50 (thrashing) | Not measured |
| MariaDB `Max_used_connections` | < 70% of `max_connections` | ≥ 90% | Not measured |
| Redis latency (loopback) | p99 < 1 ms | > 5 ms | Not measured |
| Redis `evicted_keys` | 0 | increasing | Not measured |
| `Innodb_buffer_pool_reads` / `read_requests` | < 5% miss | > 20% miss | Not measured |
| Error rate during drain | Returns to 0% | stays elevated | Not measured |

### The concurrency ladder

Record **each stage separately**. A single "1000 VU" pass hides the knee.

| Stage | Result | p95 | 5xx | FPM peak active | CPU | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| 100 concurrent | Not run | — | — | — | — | — |
| 250 concurrent | Not run | — | — | — | — | — |
| 500 concurrent | Not run | — | — | — | — | — |
| 750 concurrent | Not run | — | — | — | — | — |
| 1000 concurrent | Not run | — | — | — | — | — |

## 9. Failure injection

Run separately, with the blast radius understood and a rollback ready. Each of
these should degrade in a **defined** way; "undefined" is the finding.

| Injection | Method | Expected safe behaviour | Observed |
| --- | --- | --- | --- |
| Redis unavailable | `systemctl stop redis-server` | `/health` stays 200 (liveness is dependency-free); `/health/ready` returns 503; instance leaves rotation; **no** silent fallback to database cache/session | Not run |
| MariaDB unavailable | `systemctl stop mariadb` | `/health/ready` 503; 500s on DB-backed pages; no data corruption | Not run |
| Queue worker killed | `supervisorctl stop` | Web requests unaffected; queue depth grows; no job loss after restart | Not run |
| Worker killed mid-job | `kill -9` | Job retried after `retry_after`, not run twice | Not run |
| Scheduler stopped | remove cron | `platform:scheduler:last-run` goes stale → alert fires | Not run |
| Mail transport down | point `MAIL_HOST` at a dead port | Notifications retry, then land in `failed_jobs` with a structured log line | Not run |
| Storage full | fill the volume | Uploads fail loudly; reads keep working; no partial `media_assets` row | Not run |
| Sustained CPU starvation | `stress-ng` | Latency degrades gracefully; no 502 flood | Not run |

The Redis case is the important one. The architecture decision record requires
that a Redis failure must **not** silently fall back to database-backed sessions
or rate limiting. `PlatformRuntimeGuard` and the readiness report enforce this at
boot; this test confirms the running system also does not drift into a fallback
after startup.

## 10. Running a test

```bash
# Install k6 (do not add it as an application dependency)
# Debian/Ubuntu: https://github.com/grafana/k6/releases → k6-vX-linux-arm64.tar.gz
k6 version

# 1. Smoke first — a fast failure blocks everything else
BASE_URL=https://staging.sekolah.example k6 run tests/load/smoke.js

# 2. Single stage, to locate the knee
BASE_URL=https://staging.sekolah.example k6 run \
  --out json=/tmp/k6-100.json tests/load/public-read.js

# 3. Full ladder, once the individual stages behave
BASE_URL=https://staging.sekolah.example \
  k6 run --summary-export=/tmp/k6-summary.json tests/load/scenarios/concurrency.js

# 4. Mixed, against staging with adjusted rate limits
BASE_URL=https://staging.sekolah.example \
  TEST_EMAIL=loadtest+admin@staging.sekolah.example TEST_PASSWORD=... \
  k6 run tests/load/public-mixed.js
```

Keep the `--out json` export. A go/no-go record needs the raw series, not
just a summary table, because the summary cannot show a latency cliff that
occurred for 30 seconds in the middle of a 20-minute run.

## 11. What this harness deliberately does not do

- **No writes against production.** Staging only, with synthetic accounts.
- **No load generator on the target host.** See §1.
- **No test data resembling real people.** Synthetic names and NIKs.
- **No 1000-user run from the PRoot workspace.** The environment cannot
  generate meaningful concurrency and the result would be actively misleading.
- **No threshold relaxation to obtain a pass.** A failed target is a finding
  and goes to the owner as one.
