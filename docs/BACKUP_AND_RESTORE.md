# MariaDB backup and restore — Sekolah Kita

**Scope:** what must be backed up, how to verify a backup, how to restore, and
the RPO/RTO this system can actually claim.

> This document describes procedures. **No restore was executed against a
> production-equivalent dataset in this workspace.** What was verified locally
> is recorded in §6 and is narrower than a real restore rehearsal. A backup
> that has never been restored is a hypothesis, not a control.

---

## 1. What must be in a backup set

A consistent release consists of **two** parts that must be restored together.
Restoring the database without the matching media produces an application where
every PPDB document is missing its object; restoring media without the database
produaves orphaned files no record references.

| # | Component | Why it cannot be regenerated |
| --- | --- | --- |
| 1 | MariaDB database + `migrations` ledger | All business state |
| 2 | `storage/app/private` (admission documents, quarantine objects) | Uploaded PII, never re-obtainable |
| 3 | `storage/app/public` (published media) | Derived from admin uploads; regenerable only by re-uploading |
| 4 | Configuration/secret *reference* for the target environment | Needed to interpret (1) |

`storage/framework/` (sessions, views, logs) is **not** backed up. It is
disposable and, in the Redis-backed production topology, largely not in use.

Redis is **not** backed up as a restore source. Redis holds no business truth.
See §5.

## 2. Backup commands

```bash
#!/usr/bin/env bash
# /usr/local/bin/sekolah-kita-backup
set -Eeuo pipefail

BACKUP_ROOT=/var/backups/sekolah-kita
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
TARGET="${BACKUP_ROOT}/${STAMP}"
umask 077                      # backups contain student PII
mkdir -p "${TARGET}"

# --- 1. Database -------------------------------------------------------
# --single-transaction gives a consistent InnoDB snapshot without locking
# tables, so the backup does not block writes.
mariadb-dump \
  --defaults-file=/etc/sekolah-kita/backup.cnf \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --events \
  --hex-blob \
  --set-gtid-purged=OFF \
  --databases sekolah_kita \
  | gzip -9 > "${TARGET}/sekolah_kita.sql.gz"

# --- 2. Private media (the irreplaceable part) -------------------------
tar --create --gzip --file "${TARGET}/private-media.tar.gz" \
    -C /var/www/sekolah-kita/storage/app private

# --- 3. Public media ---------------------------------------------------
tar --create --gzip --file "${TARGET}/public-media.tar.gz" \
    -C /var/www/sekolah-kita/storage/app public

# --- 4. Manifest -------------------------------------------------------
# Without a manifest, a restore cannot be verified for completeness.
sha256sum "${TARGET}"/* > "${TARGET}/SHA256SUMS"
printf 'created_at=%s\nhost=%s\ndb=%s\nmigrations=%s\n' \
  "$(date -u +%FT%TZ)" \
  "$(hostname)" \
  "sekolah_kita" \
  "$(mariadb --defaults-file=/etc/sekolah-kita/backup.cnf -N -B -e \
      'SELECT COUNT(*) FROM sekolah_kita.migrations;')" \
  > "${TARGET}/MANIFEST"

gzip -t "${TARGET}/sekolah_kita.sql.gz" || { echo "gzip integrity failed"; exit 1; }
echo "backup complete: ${TARGET}"
```

```ini
# /etc/sekolah-kita/backup.cnf — mode 0600, owned by the backup user.
# A dedicated backup account is better than reusing the application account:
# its credentials are stored in exactly one place and it can be given only the
# privileges a dump needs.
[mysqldump]
user=sekolah_kita_backup
password=<generated>
```

```bash
sudo install -o root -g root -m 0600 /dev/null /etc/sekolah-kita/backup.cnf
```

### Why `--single-transaction`

Without it, `mariadb-dump` takes a table-by-table lock. On a live site that
means the admission list is briefly unavailable during the dump, and worse, a
dump assembled from non-transactional reads can be internally inconsistent —
counts that do not match the rows. `--single-transaction` takes one snapshot
and releases no locks, at the cost of holding a consistent read view for the
duration. For this schema size that is the right trade.

## 3. Scheduling

```cron
# /etc/cron.d/sekolah-kita-backup
# Full backup 01:15, off-peak, after the 02:00 audit report slot is clear.
15 1 * * * root /usr/local/bin/sekolah-kita-backup >> /var/log/sekolah-kita-backup.log 2>&1
```

Retention is a **policy decision with a PII dimension**, not a technical
default. The `audit_logs` table is append-only by application design; the media
tarballs contain applicant documents with a consent basis that expires. Before
setting any retention, record: what is retained, for how long, on what legal
basis, and who approves deletion. A backup set that outlives its consent basis
is a compliance exposure, so encrypted offsite retention and expiry must be
designed together, not added later.

```bash
# Suggested shape, pending the retention decision:
# 7 daily, 4 weekly, 6 monthly — then reviewed by the owner.
find /var/backups/sekolah-kita -maxdepth 1 -type d -mtime +14 -exec rm -rf {} +
```

## 4. Verification — a backup that is not verified is not a backup

Three distinct levels. Only the third proves recoverability.

**Level 1 — the file exists and is not corrupt.** Cheap; catches disk failure.

```bash
sha256sum -c /var/backups/sekolah-kita/*/SHA256SUMS
gzip -t /var/backups/sekolah-kita/*/sekolah_kita.sql.gz
```

**Level 2 — the dump is readable and structurally complete.** Catches a dump
that truncated mid-table or lost a table.

```bash
gzip -dc "${TARGET}/sekolah_kita.sql.gz" | grep -c "^CREATE TABLE"
# Compare against the live table count:
mariadb -N -B -e 'SELECT COUNT(*) FROM information_schema.tables
                  WHERE table_schema="sekolah_kita";'
```

**Level 3 — an actual restore into an isolated environment.** The only level
that proves the data is recoverable. §6.

## 5. Redis persistence expectations

| Data | On Redis loss | Recovery |
| --- | --- | --- |
| Public content cache | Public pages fall back to database reads | `php artisan platform:warm-cache --sync` |
| Sessions | Every user is logged out | Users re-authenticate |
| Rate-limit counters | Throttles reset; a brief fail-open window | None needed |
| Scheduler/unique locks | A concurrently running warm-up may duplicate | Warm-up is idempotent by design |
| **Queue payloads** | **Lost** unless AOF is enabled | See below |

With `appendonly yes` and `appendfsync everysec` (see
`docs/production/redis-production.conf` §4), at most one second of writes is
lost. Without persistence, a Redis restart drops in-flight jobs. The visible
consequence is that an uploaded PPDB document remains
`Quarantined`/`Pending` indefinitely — not data loss, but a stuck workflow that
staff will report as a bug.

A reconciliation job that re-queues `ProcessAdmissionDocument` for media assets
stuck in `Pending` beyond a threshold closes that gap without depending on
Redis persistence. That job is **not** implemented in this phase; it is listed
in `docs/PRODUCTION_CAPACITY_GUIDE.md` §8 as a follow-up.

The architectural rule stands regardless: MariaDB is the source of truth, and
Redis must be rebuildable rather than restorable.

## 6. What was actually verified in this workspace

Honest record. This is the local PRoot development environment, not a
production-equivalent host, and the development database was **not** destroyed
to test this.

| Check | Result |
| --- | --- |
| `mariadb-dump --single-transaction` completes | Yes, against `sekolah_kita` |
| Dump gzips and passes `gzip -t` | Yes |
| `CREATE TABLE` count in dump matches live table count | Yes |
| SHA256 manifest generated | Yes |
| Private media tarball created | Yes (empty in dev; no production uploads exist) |
| **Full restore into an isolated MariaDB instance** | **Not performed** |
| **Point-in-time recovery with binlog** | **Not performed** |
| **Restore against production-sized data** | **Not performed** |

The last three are the ones that matter and they require the target VPS. Until
then the correct statement is: *the backup procedure has been exercised
forward, and restore remains unproven.*

## 7. Restore procedure

Never restore over the only copy. Restore into an isolated instance first,
verify, and only then promote.

```bash
#!/usr/bin/env bash
# /usr/local/bin/sekolah-kita-restore — run against an ISOLATED target.
set -Eeuo pipefail
BACKUP_DIR=$1
TARGET_DB=${2:-sekolah_kita_restorecheck}

# 0. Refuse to run against a production-looking name.
case "${TARGET_DB}" in
  sekolah_kita) echo "refusing to restore over the live database"; exit 1 ;;
esac

umask 077
WORK=$(mktemp -d)
trap 'rm -rf "${WORK}"' EXIT

# 1. Database
mariadb --defaults-file=/etc/sekolah-kita/backup.cnf \
        -e "DROP DATABASE IF EXISTS \`${TARGET_DB}\`;
            CREATE DATABASE \`${TARGET_DB}\`
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gzip -dc "${BACKUP_DIR}/sekolah_kita.sql.gz" \
  | sed "s/\`sekolah_kita\`/\`${TARGET_DB}\`/g" \
  | mariadb --defaults-file=/etc/sekolah-kita/backup.cnf

# 2. Media, to a staging tree — never over live storage
mkdir -p "${WORK}/storage"
tar --extract --gzip --file "${BACKUP_DIR}/private-media.tar.gz" -C "${WORK}/storage"
tar --extract --gzip --file "${BACKUP_DIR}/public-media.tar.gz"  -C "${WORK}/storage"

# 3. Integrity of the extracted media
sha256sum -c "${BACKUP_DIR}/SHA256SUMS"

# 4. Structural verification of the database
mariadb --defaults-file=/etc/sekolah-kita/backup.cnf -e "
  SELECT COUNT(*) AS tables_restored FROM information_schema.tables
    WHERE table_schema='${TARGET_DB}';
  SELECT COUNT(*) AS migrations_recorded FROM ${TARGET_DB}.migrations;
  SELECT COUNT(*) AS users FROM ${TARGET_DB}.users;
  SELECT COUNT(*) AS audit_rows FROM ${TARGET_DB}.audit_logs;
  SELECT COUNT(*) AS ppdb_applications FROM ${TARGET_DB}.admission_applications;
  SELECT COUNT(*) AS media_assets FROM ${TARGET_DB}.media_assets;
"
```

**Step 5 — media referential integrity.** This is the check most often skipped,
and it is the one that catches a database/media mismatch:

```bash
# Every private object referenced by a row must exist on disk.
mariadb -N -B --defaults-file=/etc/sekolah-kita/backup.cnf -e "
  SELECT private_object_key FROM ${TARGET_DB}.media_assets
    WHERE private_object_key IS NOT NULL AND deleted_at IS NULL;" \
| while read -r key; do
    [ -f "${WORK}/storage/private/${key}" ] || echo "MISSING OBJECT: ${key}"
  done
```

**Step 6 — application-level smoke test against the restored instance:**

```bash
APP_ENV=production DB_DATABASE="${TARGET_DB}" \
  php artisan migrate:status --no-interaction     # no pending migrations
APP_ENV=production DB_DATABASE="${TARGET_DB}" \
  php artisan platform:readiness                  # all dependencies healthy
php artisan test --compact                        # against the test DB, not this
```

Manually confirm, on the restored instance: an administrator can sign in, the
public homepage renders, a published article is visible, a PPDB application
with its status history is readable, and an authorized document download
returns the expected object.

**Step 7 — promote.** Stop writes, take a final backup of the *current* live
database, swap the database name, restore media into the real storage root, and
re-run step 5 against the promoted data.

## 8. RPO / RTO assumptions

These are **targets to be validated**, not measured facts. No restore has been
timed.

| Objective | Target | Basis | Status |
| --- | --- | --- | --- |
| RPO — database | ≤ 24 h | Nightly `mariadb-dump` | Depends on the cron actually running; unverified |
| RPO — database, with binlog | ≤ 1 min | Binary logs + `sync_binlog=1` | **Requires `log_bin` to be enabled.** Not enabled by default |
| RPO — private media | ≤ 24 h | Nightly tar of `storage/app/private` | Unverified |
| RPO — Redis | ≤ 1 s | AOF `everysec` | Only if `appendonly yes` is enabled |
| RTO — full restore | ≤ 4 h | Single-node restore + verification | **Unmeasured.** Depends on dataset size |
| RTO — database only, no media | ≤ 1 h | Dump restore + smoke test | **Unmeasured** |

Two consequences worth stating plainly:

1. **The 24-hour RPO is the default and it is weak for a school system.** A
   student registration lost at 09:00 and restored from the previous night's
   backup is up to 24 hours of registrations gone. Enabling binary logs
   (`mariadb-production.cnf` §7) reduces this to about a minute and is the
   highest-value change in this document. It is off by default here because binlog
   files contain PII copies whose retention must be agreed first.
2. **RTO is unmeasured.** It scales with dataset size, so the number must come
   from a timed rehearsal on a host with production-like data volume — not from
   this development database, which is orders of magnitude smaller.

## 9. Verification checklist (per rehearsal)

```text
[ ] sha256sum -c SHA256SUMS                                   passes
[ ] gzip -t on the dump                                       passes
[ ] CREATE TABLE count matches the manifest                    matches
[ ] Database restores into an isolated instance                succeeds
[ ] Private media extracts                                    succeeds
[ ] No MISSING OBJECT lines in the referential check           zero
[ ] migrate:status reports no pending migrations              clean
[ ] platform:readiness against the restored instance          ready
[ ] Administrator sign-in                                     works
[ ] Public homepage + one published article                   render
[ ] PPDB application + status history readable                works
[ ] Authorized document download returns the object           works
[ ] Restore duration recorded                                 ______
[ ] Any failure and its remediation owner recorded            ______
```

Record the elapsed time and the dataset size. Without both, the RTO in §8
remains a guess.
