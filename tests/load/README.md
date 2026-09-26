# Load test harness — Sekolah Kita

[k6](https://k6.io) scripts for validating the 500–1000 concurrent user target.

> **No load test has been run.** These scripts are the harness, not the
> results. The methodology, acceptance criteria, and the (empty) results
> tables are in [`docs/LOAD_TEST_PLAN.md`](../../docs/LOAD_TEST_PLAN.md).

## Before you run anything

1. **Never run against production.** Staging only, with synthetic data.
2. **Do not run the load generator on the application host.** It competes for
   the same cores and understates capacity by a large, hard-to-quantify margin.
3. **Run `smoke.js` first.** A load test that renders error pages produces
   meaningless latency numbers.
4. **Confirm `php artisan platform:readiness` prints READY.** Testing a
   misconfigured build measures the misconfiguration.

## Install k6

k6 is deliberately **not** an application dependency. Install the release
binary for the load host:

```bash
# https://github.com/grafana/k6/releases — pick linux/amd64 or linux/arm64
tar -xzf k6-v*-linux-*.tar.gz
sudo mv k6-v*-linux-*/k6 /usr/local/bin/k6
k6 version
```

## Files

| File | Purpose |
| --- | --- |
| `common.js` | Thresholds, metrics, helpers, shared assertions |
| `data.js` | Routes, slugs, synthetic identities |
| `smoke.js` | Correctness gate — run before anything else |
| `public-read.js` | Cache-hit public browsing, staged ramp |
| `public-mixed.js` | Public + authenticated dashboard + notifications |
| `auth.js` | Login success/failure, rate-limiter verification |
| `ppdb.js` | PPDB public read, submission, upload, upload rejection |
| `scenarios/concurrency.js` | The 100 → 1000 ladder |

## Running

```bash
export BASE_URL=https://staging.sekolah.example

# 1. Correctness
k6 run tests/load/smoke.js

# 2. One stage at a time, so a failure is attributable
k6 run -e STAGE=100  --out json=/tmp/k6-100.json  tests/load/scenarios/concurrency.js
k6 run -e STAGE=250  --out json=/tmp/k6-250.json  tests/load/scenarios/concurrency.js
k6 run -e STAGE=500  --out json=/tmp/k6-500.json  tests/load/scenarios/concurrency.js
k6 run -e STAGE=750  --out json=/tmp/k6-750.json  tests/load/scenarios/concurrency.js
k6 run -e STAGE=1000 --out json=/tmp/k6-1000.json tests/load/scenarios/concurrency.js

# 3. Full ladder
k6 run --summary-export=/tmp/k6-summary.json tests/load/scenarios/concurrency.js

# 4. Slug-driven detail pages (otherwise detail coverage is skipped)
k6 run -e SLUGS='berita-1,berita-2' tests/load/public-read.js

# 5. Authenticated and write scenarios
k6 run -e TEST_EMAIL=loadtest+admin@staging.sekolah.example \
       -e TEST_PASSWORD=... \
       tests/load/public-mixed.js

k6 run -e PPDB_TOKEN=... -e PPDB_ACCESS_TOKEN=... \
       -e PPDB_DOCUMENT_PATH=/tmp/fixtures/sample.pdf \
       tests/load/ppdb.js
```

## Rate limits are enforced, not decorative

| Limiter | Limit |
| --- | --- |
| `ppdb-submit` | 5/hour/IP, 10/day/session, 3/day/contact |
| `ppdb-status` | 60/hour/IP, 30/hour/token |
| `ppdb-upload` | 20/day/IP, 10/day/token |
| `login` | 15/min/IP, 5/min/account, 10/min/pair |
| `contact` | 20/hour/IP, 5/hour/identity |

Write scenarios therefore need a **staging environment with limits raised by
explicit configuration**, and the fact that they were raised must be recorded
with the result. Do not defeat the limiter with spoofed `X-Forwarded-For`
headers — that also invalidates the result, because it changes the trust
boundary being measured.

## Collecting host metrics during a run

k6 measures the client. The numbers that explain *why* a stage failed come from
the server. See `docs/LOAD_TEST_PLAN.md` §7 for the full collection set:
PHP-FPM saturation, CPU, MariaDB connections and slow queries, Redis latency
and evictions, queue depth.

## Recording a result

Fill in the results tables in `docs/LOAD_TEST_PLAN.md` §8 with: date, commit
SHA, target host specification, k6 version, whether caches were warm, and the
per-stage figures. A latency number without those is not evidence.
