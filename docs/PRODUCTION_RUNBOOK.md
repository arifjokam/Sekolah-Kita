# Sekolah Kita — Production Operations Runbook

**Document status:** Minimum operational baseline; not a production approval  
**Scope:** Ubuntu VPS, Nginx, PHP-FPM, MariaDB, Redis, queue workers, scheduler, HTTPS  
**Last updated:** 2026-09-25

> This runbook describes the minimum safe operating procedure. It does not prove that the current workspace is production-ready. Redis, mail, TLS, backups, MFA, contact verification, malware scanning, monitoring, and ownership must be provisioned and verified in the target environment.

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
php -m
composer validate --strict
composer audit
php artisan migrate:status --no-interaction
php artisan route:list --except-vendor
php artisan schedule:list
php artisan platform:readiness --json
```

Verify the release environment separately with the dedicated test database:

```text
php artisan test --compact
```

Do not use `composer setup` for deployment. It is a development bootstrap script and includes destructive/general-purpose setup behavior that is not an approved release procedure.

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
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_REQUIRED_IN_PRODUCTION=true
AUDIT_FAILURE_MODE=throw
MAIL_MAILER=<approved non-log transport>
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
```

Use separate credentials, Redis prefixes/databases, mail identities, storage buckets/paths, application keys, and logs per environment. The application key must be generated and rotated through the approved secret process; it must not be logged.

Build configuration, route, event, and view caches only after the environment is verified:

```text
php artisan optimize
```

If a deployment must roll forward, do not use `migrate:fresh`, `migrate:reset`, `db:wipe`, `DROP`, or `TRUNCATE`. Review migrations and take a fresh verified backup first.

## 4. Redis and queue operation

Redis is infrastructure, not business truth. Verify each configured connection (default, cache, queue, and rate-limit isolation) from the release environment. The local workspace currently has no reachable Redis service and no PHP Redis extension; that is an environment blocker, not an application fallback.

Run workers under Supervisor, systemd, or the hosting platform's process manager. A worker must consume all application queues:

```text
php artisan queue:work redis --queue=notifications,media,maintenance --tries=3 --timeout=150 --sleep=1
```

Keep worker timeout below the configured Redis `retry_after` value. Restart workers gracefully after deployment. Monitor:

- worker process state and restart count;
- queue depth and oldest job age;
- failed jobs and terminal failure reasons;
- Redis memory, latency, evictions, and connection errors.

The document-processing job is quarantine-oriented and its local content check is not a malware scanner. Keep private documents unavailable until an approved scanner/parser marks them clean.

## 5. Scheduler

Run the scheduler every minute from the operating-system scheduler or hosting scheduler:

```text
* * * * * cd /path/to/release && php artisan schedule:run
```

The repository defines hourly cache warming and daily audit-maintenance reporting with overlap/one-server locks. Monitor the last successful scheduler run. `audit:maintenance` is report-only; it does not delete audit history.

## 6. Backup and restore

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
