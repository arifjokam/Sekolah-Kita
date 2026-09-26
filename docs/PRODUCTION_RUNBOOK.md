# Sekolah Kita — Production Operations Runbook

**Document status:** Minimum operational baseline; not a production approval  
**Scope:** Ubuntu VPS, Nginx, PHP-FPM, MariaDB, Redis, queue workers, scheduler, HTTPS  
**Last updated:** Phase 8 (production hardening, Redis runtime, scalability readiness)

> This runbook describes the minimum safe operating procedure. It does not prove that the current workspace is production-ready. TLS, real mail, verified backups, MFA, contact verification, malware scanning, monitoring, and ownership must be provisioned and verified in the target environment, and the 500–1000 concurrent-user target must be validated by load testing.

## 0. Phase 8 companion documents

| Document | Covers |
| --- | --- |
| `docs/PRODUCTION_CAPACITY_GUIDE.md` | Sizing methodology, request cost profile, scaling path, what is and is not validated |
| `docs/LOAD_TEST_PLAN.md` | k6 harness, concurrency ladder, acceptance criteria, failure injection |
| `docs/BACKUP_AND_RESTORE.md` | Backup procedures, restore rehearsal, RPO/RTO |
| `docs/SECURITY_REVIEW.md` | Administrator identity, MFA gap, PPDB/media boundary |
| `docs/production/nginx.conf` | Nginx site, TLS, buffering, timeouts, access-log format |
| `docs/production/php-fpm-pool.conf` | Worker sizing methodology, pool template |
| `docs/production/php-production.ini` | OPcache, limits, security ini |
| `docs/production/redis-production.conf` | Redis client, auth, memory, persistence, isolation |
| `docs/production/mariadb-production.cnf` | Connections, buffer pool, slow log, replica caveats |
| `docs/production/supervisor-queue.conf` | Workers, scheduler, systemd equivalents |
| `tests/load/` | The k6 scripts themselves |

## 1. Release blockers and ownership

Do not send production traffic until the responsible owner records evidence for:

- Redis cache, session, queue, and rate-limit connectivity, authentication/TLS policy, persistence/recovery, and eviction monitoring.
- Supervised queue workers for `notifications,media,maintenance` and a functioning scheduler heartbeat.
- HTTPS, trusted proxy/host configuration, secure cookies, PHP-FPM settings, and upload limits.
- Real mail transport and delivery/failure monitoring.
- Encrypted offsite backups covering MariaDB and private media, followed by a successful isolated restore exercise.
- Administrator MFA, enrollment, recovery, and break-glass policy.
- PPDB contact verification, document retention, duplicate review/merge policy, and an approved malware/content-disarm service.
- Monitoring and incident ownership for HTTP errors, latency, queue age/failures, Redis, MariaDB, storage capacity, mail, and security events.

The application intentionally remains fail-closed for missing production prerequisites. A successful local test suite is not a substitute for this evidence.

## 2. Read-only preflight

Run against the intended environment, never against an unapproved production database:

```text
php -v
php -m | grep -E '^(redis|igbinary|opcache)$'
php -r 'echo phpversion("redis");'          # must print a version, not nothing
composer validate --strict
composer audit
npm audit --omit=dev
php artisan migrate:status --no-interaction
php artisan route:list --except-vendor
php artisan schedule:list
php artisan platform:readiness --json
```

`platform:readiness` reports grouped, individually-named checks covering
application, HTTP/trust, Redis, cache, session, queue, rate limiting, database,
storage, mail, and administrator security. Each check is marked `blocking` or
advisory, and the command exits non-zero if any blocking check fails. It is
exempt from the boot guard, so it still runs in a misconfigured production
release — otherwise the misconfiguration could not be diagnosed.

Verify the release environment separately with the dedicated test database:

```text
php artisan test --compact
```

Do not use `composer setup` for deployment. It is a development bootstrap script and includes destructive/general-purpose setup behavior that is not an approved release procedure.

## 2a. Health endpoints

| Endpoint | Purpose | Dependency behaviour |
| --- | --- | --- |
| `/health` | Liveness | **Never** touches MariaDB or Redis. Stays 200 during a dependency outage so a load balancer does not remove every node and turn a partial degradation into a total outage |
| `/health/ready` | Readiness | Checks database, cache, and queue. Returns 503 when a request cannot be served, so the instance leaves rotation |
| `/up` | Framework-provided health route | Also dependency-free; retained for platform defaults |
| `php artisan platform:readiness` | Pre-traffic configuration gate | Exit code 0 = ready, 1 = not ready |

Point monitoring at `/health` for liveness and `/health/ready` for readiness. Point the release gate at `platform:readiness`.

## 3. Environment and secret gate

Inject secrets through the hosting platform or a protected process environment. Never commit or copy `.env`, `.env.testing`, `.env.backup`, database dumps, Redis dumps, or private-media backups into the repository or CI artifacts.

Minimum production values include:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<approved-host>
TRUSTED_PROXIES=<explicit proxy CIDRs/IPs; never *>
TRUSTED_HOSTS=<explicit host patterns>
CACHE_STORE=redis
CACHE_LIMITER=rate_limits
SESSION_DRIVER=redis
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
QUEUE_CONNECTION=redis
REDIS_REQUIRED_IN_PRODUCTION=true
REDIS_CLIENT=phpredis
REDIS_HOST=<host>
REDIS_PASSWORD=<secret>
REDIS_CACHE_CONNECTION=cache
REDIS_QUEUE_CONNECTION=queue
REDIS_RATE_LIMIT_CONNECTION=rate_limits
REDIS_QUEUE_RETRY_AFTER=180
QUEUE_WORKER_TIMEOUT=150
QUEUE_WORKER_QUEUES=notifications,media,maintenance
AUDIT_FAILURE_MODE=throw
MAIL_MAILER=<approved non-log transport>
BCRYPT_ROUNDS=12
ADMIN_MFA_REQUIRED=true
```

`CACHE_LIMITER` names the rate-limiter backing store and defaults to
`CACHE_STORE`. Setting it to `rate_limits` puts authentication and throttling
counters on their own Redis connection so a cache flush does not reset limits
and a cache problem does not take throttling with it.

`PlatformRuntimeGuard` refuses to **boot** a production release where
`APP_DEBUG=true`, where cache/session/queue have fallen back to the database
driver, where the Redis client is missing, where cookies are not secure and
HTTP-only, where `APP_URL` is not https, where `TRUSTED_PROXIES`/`TRUSTED_HOSTS`
are wildcards, or where `AUDIT_FAILURE_MODE` is not `throw`. This is
deliberate fail-closed behaviour: a silently degraded release is how a Redis
incident becomes a database outage.

> **Trusted proxy/host caveat.** The values are read from **cached
> configuration** in a service provider's `boot()` method, not from `env()` in
> `bootstrap/app.php`. `withMiddleware()` runs via
> `Application::afterResolving(HttpKernel::class)`, which is *before* the
> framework bootstrappers load `.env`, so an `env()` call there silently
> returns its default and the configuration never takes effect. This was a real
> defect in this codebase; it is now covered by
> `tests/Feature/ProductionRuntimeTest.php`. Do not "simplify" the wiring back
> to `bootstrap/app.php`.

Use separate credentials, Redis prefixes/databases, mail identities, storage buckets/paths, application keys, and logs per environment. The application key must be generated and rotated through the approved secret process; it must not be logged.

Build configuration, route, event, and view caches only after the environment is verified:

```text
php artisan optimize
```

If a deployment must roll forward, do not use `migrate:fresh`, `migrate:reset`, `db:wipe`, `DROP`, or `TRUNCATE`. Review migrations and take a fresh verified backup first.

## 4. Redis and queue operation

Redis is infrastructure, not business truth. Verify each configured connection (default, cache, queue, and rate-limit isolation) from the release environment.

**PHP client.** The production client is phpredis. On Ubuntu that is
`php8.5-redis`, which **depends on `php8.5-igbinary`** — installing the
extension alone fails at extension-load time with
`undefined symbol: igbinary_serialize`, on every request:

```bash
sudo apt-get install -y php8.5-redis php8.5-igbinary
php -m | grep -E '^(redis|igbinary)$'
```

Configuration and hardening guidance, including authentication, memory limits,
eviction policy, and persistence expectations, is in
`docs/production/redis-production.conf`.

**Logical database isolation.** Sessions use DB 0, cache DB 1, queue DB 2, and
rate limits DB 3. This is an operational separation, not a security boundary —
`FLUSHDB` on the cache connection cannot take out the queue. For real
isolation, use separate instances.

**Persistence.** With `appendonly yes` and `appendfsync everysec`, at most one
second of writes is lost. Without persistence, a Redis restart drops in-flight
queue payloads, and an uploaded PPDB document remains `Quarantined`/`Pending`
indefinitely. See `docs/BACKUP_AND_RESTORE.md` §5.

Run workers under Supervisor, systemd, or the hosting platform's process manager. Full templates with directive rationale are in `docs/production/supervisor-queue.conf`. A worker must consume all application queues:

```text
php artisan queue:work redis --queue=notifications,media,maintenance --tries=3 --timeout=150 --sleep=1 --memory=128 --max-jobs=1000 --max-time=3600
```

Keep worker timeout strictly below the configured Redis `retry_after` value. If the timeout exceeds `retry_after`, Redis considers the job abandoned while the first worker is still executing it, and the job runs twice. `php artisan platform:readiness` fails closed on this mismatch; the Supervisor and systemd templates must pass the same `QUEUE_WORKER_TIMEOUT` / `REDIS_QUEUE_RETRY_AFTER` values.

Restart workers gracefully after deployment. Monitor:

- worker process state and restart count;
- queue depth and oldest job age (`queue:monitor` runs every five minutes from the scheduler and dispatches an event above `QUEUE_MONITOR_THRESHOLD`);
- failed jobs and terminal failure reasons — terminal failures are persisted to `failed_jobs` **and** emit a structured `queue.job_failed` log line containing only connection, queue, job name, job id, attempt count, and exception class (never the payload, PII, or the exception message);
- Redis memory, latency, evictions, and connection errors.

The document-processing job is quarantine-oriented and its local content check is **not a malware scanner**. See `docs/SECURITY_REVIEW.md` §2.2 for exactly what that boundary detects and what it does not. Keep private documents unavailable until an approved scanner/parser marks them clean.

## 5. Scheduler

Run the scheduler every minute from the operating-system scheduler or hosting scheduler:

```text
* * * * * cd /path/to/release && php artisan schedule:run
```

The repository defines the following scheduled work. Every task is `withoutOverlapping()` with a bounded TTL and `onOneServer()`, and every `onOneServer`/`withoutOverlapping` mutex uses a 3600-second (or per-task) cache lock — no unbounded distributed lock exists in the application.

| Task | Schedule | Purpose |
| --- | --- | --- |
| `platform:warm-cache` | Hourly | Dispatch the `ShouldBeUnique` public-content warmer |
| `audit:maintenance` | Daily 02:00 | Report audit retention candidates; never deletes |
| `queue:monitor` | Every 5 min | Emit an event when a queue backlog exceeds the threshold |
| `queue:prune-failed` | Daily 03:30 | **Only runs if `QUEUE_PRUNE_FAILED_AFTER_DAYS` is set** — pruning removes failure evidence, so it is off by default |
| `platform:heartbeat` | Every minute | Record that the scheduler is running |

Run the scheduler from cron, or use the systemd timer in `docs/production/supervisor-queue.conf` §5. Do **not** run both: two schedulers mean every task's overlap mutex is contended by two processes and `onOneServer()` becomes meaningless.

A stopped cron entry is otherwise silent. Alert on a missing `platform:scheduler:last-run` cache key, which has a one-hour TTL and therefore means "no successful run in the last hour" rather than accumulating forever.

```bash
php artisan tinker --execute 'var_dump(cache()->get("platform:scheduler:last-run"));'
```

## 6. Backup and restore

Full procedures, the restore rehearsal script, and the RPO/RTO table are in `docs/BACKUP_AND_RESTORE.md`. Summary of the essentials:

The backup set must contain, as one consistent release:

1. MariaDB database and migration ledger.
2. Private application/media objects.
3. The application configuration/secret reference needed to interpret the data, without copying plaintext secrets into the repository.

Encrypt backups, restrict restore permissions, store an offsite copy, and record the owner, timestamp, retention, RPO, and RTO. A backup is not accepted until it has been restored in an isolated environment and verified.

Restore rehearsal:

1. Provision an isolated MariaDB and private-storage target.
2. Restore the database backup without using `migrate:fresh` on the source.
3. Restore the matching private-media set.
4. Verify representative users, academic records, PPDB rows/status history/documents, audit rows, and object paths.
5. Run `php artisan migrate:status --no-interaction` and the relevant smoke tests.
6. Record the restore duration, failures, and remediation owner.

Never test a restore by overwriting the only production copy.

## 7. Release sequence

1. Confirm the change is reviewed and the migration list is additive/approved.
2. Take and verify a fresh encrypted database/private-media backup.
3. Build a clean release artifact with locked dependencies and a trusted `npm ci && npm run build`.
4. Run reviewed migrations only after backup and approval.
5. Build and verify cached configuration/routes/events/views.
6. Reload PHP-FPM and gracefully restart workers.
7. Run `php artisan platform:readiness --json`; a failure blocks traffic.
8. Run public, authentication, authorization, PPDB, private-media, queue, audit, and notification smoke tests with synthetic/staging data.
9. Observe errors, latency, queue age, storage, database, mail, and security events during stabilization.

## 8. Smoke and failure-injection checklist

- Public pages, static assets, TLS, security headers, and no unintended indexing of private status pages.
- Login success/failure, logout, reset, verification, rate limits, role/status enforcement, and session invalidation.
- Positive and negative authorization for administrator, Guru, and Siswa, including direct resource IDs.
- PPDB submission, status read, correction purpose token, deadline, withdrawal purpose token, document upload, quarantine/scan, authorized download, history, and audit.
- Contact submission, administrator notification, queued mail, retry, and failure logging without raw PII.
- Redis outage, MariaDB outage, worker interruption, scheduler interruption, mail failure, storage failure, and disk-full behavior.
- Verify no passwords, raw tokens, private object paths, applicant PII, or provider payloads appear in ordinary logs, URLs, cache keys, or error output.

## 9. Incident and rollback

For an incident, preserve evidence, revoke affected credentials/tokens, stop or isolate the affected worker/public route as appropriate, notify the named owner, and record the timeline. Do not delete audit rows to simplify recovery.

For a failed release, prefer forward recovery after a verified backup or a reviewed application rollback. Do not run a generic migration rollback against data whose migration intentionally has no destructive `down()` implementation. Validate the restored database/media pair before reopening traffic.

## 10. Go/no-go record

The go/no-go record must include:

- release commit/artifact identifier;
- migration ledger and schema/index evidence;
- readiness JSON;
- test/build/audit results;
- Redis/worker/scheduler evidence;
- TLS/Nginx/PHP-FPM evidence;
- backup and restore evidence;
- MFA and owner approvals;
- load-test/SLO result or explicit owner acceptance;
- open incidents, rollback owner, and monitoring confirmation.

Until every required item has evidence, report the project as **not production-ready**.

## 11. Known implementation gaps at the end of Phase 8

Recorded here so the go/no-go record does not have to rediscover them. Full
detail in `docs/SECURITY_REVIEW.md` and `docs/PRODUCTION_CAPACITY_GUIDE.md` §8.

**Blocking (readiness fails):**

- Administrator MFA is not implemented. Provision it, or set
  `ADMIN_MFA_REQUIRED=false` and record a named, time-boxed owner exception.
  Do not describe the second factor as covered when it is not.

**Not implemented, must be closed by process or product decision:**

- No malware/content-disarm scanning. Uploads are validated and quarantined;
  they are **not** malware-scanned. `MediaScanStatus::Clean` means the
  application's own content checks passed.
- No PPDB applicant email/phone verification.
- No first-administrator provisioning command; `DatabaseSeeder` creates no
  users.
- The configured `SESSION_IDLE_TIMEOUT` is reported by readiness but is **not
  enforced** by any middleware.
- No monitoring/alerting stack, so "monitor the last successful scheduler run"
  is currently a manual instruction.
- No CDN, no read replica, no multi-node deployment, no autoscaling.

**Explicitly not claimed:**

- 500–1000 concurrent users has **not** been validated. The architecture
  required for it is in place and was exercised against a real Redis, but no
  throughput or latency number exists. The k6 harness is in `tests/load/`; the
  results tables in `docs/LOAD_TEST_PLAN.md` §8 are empty.
