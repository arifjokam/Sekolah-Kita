# Production capacity guide — Sekolah Kita

**Purpose:** the sizing methodology and its limits, so that a capacity decision
is a calculation rather than a guess, and so that the 500–1000 concurrent user
target is treated as a hypothesis to be tested.

> ## No capacity claim is made here
>
> The application architecture required for the target has been implemented and
> verified locally, including real Redis connectivity. **Whether this system
> serves 500–1000 concurrent users has not been measured**, because the
> workspace is an ARM64 PRoot container with no reverse proxy, no TLS, and no
> production hardware. Every number in this document is a *starting point to be
> recomputed* for the VPS that gets provisioned, or a *target to be validated*
> by the load test.
>
> Passing 190 automated tests proves the code is correct. It says nothing about
> throughput, and this document does not ask it to.

---

## 1. What "500–1000 concurrent users" actually means

The number is ambiguous in a way that changes the required hardware by an order
of magnitude, so it must be pinned down before any sizing.

**Concurrent users ≠ concurrent requests.** A teacher reading a notice for
30 seconds issues maybe 10 requests in that time. 1000 concurrent *users*
generating human-paced traffic is roughly:

```
1000 users ÷ (avg 15 s per page × ~4 requests per page) ≈ 270 requests/second
```

270 rps of mostly-cached, indexed, server-rendered HTML is a comfortable load
for a modest VPS **if** the cache is warm and the database queries are indexed.

1000 *simultaneous in-flight requests* is a different problem entirely — 20×
the load, and one that a single 4 vCPU PHP-FPM pool will not absorb.

| Interpretation | Load | Reasonable target hardware |
| --- | --- | --- |
| 1000 concurrent users, human-paced browsing | ~200–300 rps | 4 vCPU / 8 GB, one node, CDN in front of public pages |
| 1000 concurrent in-flight requests | ~1000 rps of PHP | 8+ vCPU / 16 GB, two app nodes, read replica, CDN |
| 1000 concurrent users **and** a PPDB open day | Bursty writes, uploads | As above plus worker capacity for the `media` queue |

**A PPDB intake window is the real spike.** A school opening registration
generates a burst of submissions and document uploads concentrated into a few
hours, from a much smaller population than the daily readership. Capacity must
be planned for that burst, not for the daily average.

**This document assumes interpretation 1, with interpretation 2 as the
stress case.**

## 2. Request cost profile

Worker sizing follows from how long a request holds a worker.

| Request class | DB queries | Cache | Target p95 | Notes |
| --- | --- | --- | --- | --- |
| Public home/news/gallery/teachers | 0 on hit | Yes | < 300 ms | The dominant class |
| Public page, cache miss | 2–6 indexed | No | < 1500 ms | Includes a `COUNT` for pagination |
| PPDB list | 1 + paginator count | Partly | < 800 ms | |
| PPDB submission | Transaction, ~6 writes | No | < 1500 ms | Burst class |
| PPDB document upload | 1 write + **image re-encode** | No | < 5 s | The most expensive request in the system |
| Authenticated dashboard | 2–4 | No | < 500 ms | |
| Notifications | 1 (+ count) | No | < 500 ms | Index added in Phase 8 |
| Admin CRUD | 2–8 writes + audit | No | < 800 ms | Audit write is fail-closed |

The row that matters for sizing is **document upload**. A 20 MP image needs
roughly 160 MB in an `imagecreatetruecolor` canvas before encoding. A handful of
simultaneous uploads can exhaust a 256 MB-per-worker pool and force MariaDB and
Redis into swapping. This is why `PPDB_MAX_DOCUMENT_BYTES` and the
6000×6000/20 MP dimension cap in `PrivateDocumentService` exist, and why upload
concurrency is budgeted separately from read concurrency
(`php-fpm-pool.conf` §5).

## 3. Architecture that has to be in place

Implemented and verified in this phase:

| Concern | Implementation | Verified by |
| --- | --- | --- |
| Cache | Redis, `cache` connection (DB 1), environment-scoped prefix | Real put/get against Redis 8.0.5 |
| Sessions | Redis, `default` connection (DB 0) | Real session write/read against Redis |
| Queue | Redis, `queue` connection (DB 2), named queues | Real dispatch, `queues:maintenance` key observed |
| Rate limiting | Redis, `rate_limits` connection (DB 3) via `CACHE_LIMITER` | Real `RateLimiter::hit()` landed in DB 3 |
| Distributed locks | Cache locks, bounded TTL (3600 s scheduler, 10 min overlap) | `CacheSchedulingMutex` 3600 s; `ShouldBeUnique` + `uniqueFor` |
| Cache warming | `ShouldBeUnique`, `uniqueFor=3600`, `withoutOverlapping(10)`, `onOneServer` | `schedule:list`, job definition |
| Public content cache | Versioned keys, 300 s TTL, group invalidation | `PublicContentCacheTest` |
| Query indexes | Reviewed against query shapes; notification listing index added (Phase 8) | `EXPLAIN` — filesort eliminated |
| Health / readiness | Dependency-free liveness, dependency-checked readiness | `ProductionRuntimeTest` |
| Fail-closed boot | `PlatformRuntimeGuard` refuses a misconfigured production release | Guard tests + manual run |

**Not implemented, and therefore not verified:**

- No CDN or Nginx micro-caching in front of the public pages. The single
  largest latency and PHP-load reduction available for a read-heavy school
  site, and it is infrastructure work on the VPS.
- No read replica. `config/database.php` supports it (`DB_READ_HOST`), but no
  replica is provisioned and the read-after-write caveats in
  `mariadb-production.cnf` §8 must be honoured before switching reads over.
- No multi-node deployment. Everything here assumes one application host.
- No vertical or horizontal autoscaling.

## 4. Sizing methodology

The formulas are in `docs/production/php-fpm-pool.conf` §2 and
`docs/production/mariadb-production.cnf` §1. Recomputed for a 4 vCPU / 8 GB
baseline:

```
FPM memory bound:   8192 MB × 0.30 headroom ÷ (256 MB × 1.15) = 8.3   → 12 children
FPM CPU bound:      4 cores × 0.65 ÷ 0.015 s                 = 173    → not binding
Connections:        (12 + 2 + 1 + 3) × 1.2                     = 22     → max_connections 40
Buffer pool:        8 GB × 0.25                                = 2 GB
Redis maxmemory:    8 GB × 0.19                                = 1.5 GB
```

Memory is the binding constraint, not CPU. That is the normal case for
server-rendered PHP: the work per request is small, but every worker holds a
baseline footprint plus its peak allocation.

**Do not "fix" this by raising `memory_limit`.** The worker count is *derived
from* that value; raising it silently reduces the safe pool, and overshooting
physical memory means the OOM killer chooses between MariaDB, Redis, and
PHP-FPM. The two have to be recomputed together, or not at all.

## 5. Scaling path, in the order to try it

Roughly in order of cost-to-benefit for this workload:

1. **CDN in front of the public pages.** Removes the largest share of traffic
   at the origin. The public pages are already cache-shaped for it. Cheapest
   large win; infrastructure only.
2. **Warm the cache and keep it warm.** `platform:warm-cache` hourly. A cold
   public cache is the difference between 0 and ~4 database queries per page
   view.
3. **Increase `pm.max_children`** — only after confirming CPU headroom and
   re-deriving the MariaDB connection budget. This is the step most often taken
   too early and it produces memory thrash.
4. **Add a read replica** for the public read paths, honouring the
   read-after-write caveats.
5. **Add a second application node** with a shared Redis. Sessions and rate
   limits are already in Redis, and `onOneServer()` scheduler locks are
   designed for it, so this is architecturally supported. Nginx upstream
   becomes the load balancer.
6. **Vertical scale** — simplest, and the right first move on a single node.

Steps 3 and 4 in the wrong order are how a school site ends up slower after
"scaling up".

## 6. What must be measured, and what it will decide

| Question | Answered by | Decides |
| --- | --- | --- |
| Does 12 FPM children saturate before 1000 VU? | `tests/load/scenarios/concurrency.js` + FPM status | §4 worker count |
| Where is the knee — 250, 500, or 750? | Per-stage results, §8 of the load plan | Whether a CDN or a bigger host is needed |
| Is p95 under 300 ms at target? | Public read scenario | Accept/reject |
| Does the cache actually absorb traffic? | Redis `keyspace_hits` ratio | Cache TTL and warming interval |
| Does MariaDB hold connections? | `Threads_running`, `Max_used_connections` | `max_connections`, replica need |
| Do uploads degrade reads? | Mixed scenario with upload load | Whether uploads move fully to the queue |
| Does anything leak over a 10-minute soak? | Memory series during the hold | `pm.max_requests`, `--max-jobs` |
| Can PPDB absorb an open day? | Write scenario at realistic burst | Worker count, DB capacity, rate limits |

## 7. Performance guards already in the codebase

- Bounded public-content cache TTL (300 s) with group invalidation, so a stale
  projection cannot persist indefinitely and a content change does not require
  a full flush.
- Paginator page clamped to `1..10000` and per-page clamped to `1..100` in
  `PublicContentReader`, so a hostile `?page=` cannot force an unbounded query.
- Rate limits on every write-shaped public endpoint.
- `ShouldBeUnique` + `uniqueFor` on cache warming, so a scheduler that fires
  repeatedly cannot stack warmers.
- `afterCommit()` on queued jobs, so a job never observes a transaction that
  later rolls back.
- Fail-closed audit writes, so a write path cannot appear to succeed while
  accountability was lost.

## 8. Follow-ups, explicitly not done in Phase 8

| Item | Why not | Impact |
| --- | --- | --- |
| `X-Cache: HIT/MISS` response header | Small, safe, but a behavioural change to public responses | Cache hit ratio currently only measurable from Redis counters |
| Reconciliation job for media stuck in `Pending` | Needs a product decision on the threshold | Redis restart leaves uploads quarantined indefinitely |
| Load balancer / multi-node Nginx config | Requires knowing the target topology | Blocks the second application node |
| CDN configuration | Provider choice is a product decision | Largest available latency win |
| Prometheus / metrics endpoint | No monitoring stack chosen | Saturation currently observed by manual collection |
| Read/write split adoption | Replica not provisioned | Optional `DB_READ_HOST` support is present and tested |
| PPDB applicant email/phone verification | Product and infrastructure decision | Contact verification is still a runbook blocker |
| Malware/content-disarm scanning | Requires an external scanning service | See `docs/SECURITY_REVIEW.md` |

## 9. Capacity statement

**What is established:** the architecture required for the target is
implemented and was exercised against a real Redis server — cache, sessions,
queue, and rate limiting on four isolated logical databases, verified by
observing actual keys. Query plans were reviewed and one real index gap was
found and fixed. Health, readiness, and a fail-closed boot guard are in place.

**What is not established:** any throughput or latency figure. The load harness
exists and is documented, but it has not been run, because the environment
cannot produce a meaningful result. The 500–1000 concurrent user target remains
**unvalidated**.

**What is required to validate it:** a VPS matching §4, `platform:readiness`
reporting READY, the smoke test passing, and a full pass through
`docs/LOAD_TEST_PLAN.md` §8 with host metrics collected in parallel. Until then,
report the project as **not production-ready** for the stated target.
