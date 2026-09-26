# Security review — administrator identity and PPDB/media

**Phase:** 8
**Scope:** what is implemented and verified in the application, what is
deliberately not implemented, and what remains a product or infrastructure
decision.
**Posture:** the application is fail-closed. Where a control is missing, the
readiness report says so rather than passing quietly.

---

# Part 1 — Administrator security

## 1.1 What is already implemented

| Control | Implementation | Notes |
| --- | --- | --- |
| Password hashing | bcrypt, `BCRYPT_ROUNDS` (≥ 12 enforced by readiness) | `Password::defaults()` enforces min 12 chars |
| Constant-time-ish login | Dummy hash compared when the account does not exist | Removes user-enumeration timing signal |
| Generic login failure | Single `auth.failed` message for unknown, wrong password, inactive, and role-less | No enumeration in responses |
| Login throttling | `login` limiter: 15/min per IP hash, 5/min per account hash, 10/min per pair | HMAC/SHA-256 hashed keys; raw IPs and emails are not rate-limit keys |
| Password reset throttling | 3/email, 10/IP per minute | |
| Verification throttling | 3/user, 10/IP per minute | |
| Session fixation protection | `regenerate()` on login, `regenerateToken()` on login and logout | |
| Logout invalidation | `invalidate()` + `regenerateToken()` | |
| Password change invalidation | `UserSessionInvalidator` — rotates remember token, deletes database sessions, **and revokes Redis sessions** (Phase 8) | |
| Account lifecycle | Suspend / deactivate / reactivate / role change, all audited, all invalidating sessions | `AccountLifecycleService` |
| Active-status enforcement | `EnsureAccountIsActive` middleware, server-side, on every protected route | Not a hidden-menu control |
| Role authorization | Policies per model, gates (`manage-users`, `view-audit-log`, `access-administrator-area`, `review-ppdb`) | `Gate::authorize` server-side |
| Email verification required | `verified` middleware on all protected routes | |
| Security headers | CSP, HSTS (production), `nosniff`, `DENY` frames, `no-referrer` on private responses, `noindex` on private responses | `SecurityHeaders` |
| Audit trail | Authentication, account lifecycle, PPDB review, document access — all recorded; `AUDIT_FAILURE_MODE=throw` makes audit failure fail the request | Append-only enforced at the model |
| Fail-closed boot | `PlatformRuntimeGuard` refuses a misconfigured production release | New in Phase 8 |

## 1.2 The gap: no multi-factor authentication

**MFA is not implemented. This is the single largest remaining security gap
for this system.**

`platform:readiness` reports it as a **blocking** check:

```
administrator_mfa: [FAIL] Administrator multi-factor authentication is required
                    but not implemented.
```

This is deliberate. A silently-passing readiness report is worse than a known
gap, because the go/no-go record is supposed to be evidence.

### Why it was not implemented in this phase

Implementing MFA correctly requires one of:

- a TOTP/WebAuthn library dependency (a new `composer` requirement), or
- an external identity provider.

`AGENTS.md` §10 requires approval before adding a package, and §12 requires
approval before large feature work. Both are outstanding. Building a
hand-rolled TOTP implementation would be worse than none: rolling a custom
crypto primitive for account takeover defence is not an acceptable trade.

### What is needed before launch

1. **Dependency approval** for a maintained MFA package, or a decision to use an
   external IdP.
2. **TOTP** as the baseline (Google Authenticator / Authy compatible).
3. **Enrolment flow**: QR provisioning, one-time recovery codes stored hashed,
   enrolment only after a password re-authentication.
4. **Enforcement point**: the login controller, after credential validation and
   before `Auth::login()`. A partially enforced second factor is worse than none
   because it looks like coverage.
5. **Step-up authentication** for the highest-risk actions: revealing an
   administrator's account, changing another administrator's role, bulk data
   export, and audit-log access.
6. **Recovery and break-glass**: at least two offline recovery codes per
   administrator, sealed and held by a named owner, plus a documented
   break-glass account procedure. A system where losing a phone locks the
   school out permanently will be bypassed, and the bypass will be
   unaudited.
7. **Session invalidation on MFA change**: removing a factor must invalidate
   existing sessions — which the Phase 8 Redis session registry now makes
   possible.

### Interim mitigation, available now

`ADMIN_MFA_REQUIRED=false` records a named, time-boxed owner exception and
makes readiness pass. It is an **acceptance of risk**, not a mitigation. If it
is used:

- record who accepted it, when, and the review date;
- compensate with strict trusted-proxy/host configuration, the existing login
  throttling, and alerting on `security.authentication` log events;
- do not use it as a permanent setting.

## 1.3 Secure provisioning

**Current state.** `DatabaseSeeder` creates no user accounts at all — it is
deliberately empty with a comment saying so. That is the right instinct (no
default password ever exists in the repository) but it leaves a real gap:

| Gap | Status | Needed |
| --- | --- | --- |
| **No first-administrator provisioning mechanism** | Not implemented | A one-shot Artisan command that creates a single administrator with a randomly generated password delivered out of band, writes an audit record, and refuses to run twice. Not a seeder |
| No default/weak passwords | Enforced by `Password::min(12)` and `BCRYPT_ROUNDS` ≥ 12 | Correct as-is |
| First-login password change | Not enforced | A provisioned administrator should be required to set their own password before reaching the dashboard |
| No break-glass account | Not implemented | Needs a documented, audited procedure; see §1.2 |
| Credential rotation | `APP_PREVIOUS_KEYS` supported for `APP_KEY`; no user password rotation policy | Decide a policy; MFA is the larger control |

The provisioning gap is why the runbook lists administrator provisioning as a
release blocker. Without a controlled path, the first administrator is created
by hand — which means the credential handling is a shell command, and shell
commands end up in shell history.

## 1.4 Password reset and self-service

Implemented: reset link request, throttled, single-use token, generic response
(no enumeration), audited via `PasswordResetLinkSent` and `PasswordReset`, and
sessions invalidated on success.

Gaps:

- **No notification on password change beyond the audit record.** If a
  password is changed without the user requesting it, the only signal is the
  audit log. A direct email to the account address on reset completion is the
  standard control and is a product decision (it needs a real mail transport).
- **No session listing or "sign out everywhere" UI.** The capability exists
  server-side (`UserSessionRegistry::revokeAll`) and is exercised by password
  reset and account suspension, but there is no self-service control.

## 1.5 Session management

Phase 8 added the missing piece. Before this phase, `UserSessionInvalidator`
deleted sessions **only** when `SESSION_DRIVER=database`; under the production
Redis driver, suspending an administrator or resetting a password left their
existing Redis session fully valid for the remaining session lifetime. That is a
real account-lifecycle failure, and it is now fixed by `UserSessionRegistry`,
which records the session ids bound to a user and revokes them.

Current posture:

- Sessions are server-side, Redis-backed, and survive a deploy.
- Session payload encryption is on in production (`SESSION_ENCRYPT`), and
  serialization stays `json` to avoid PHP object-deserialization risk.
- Secure, HTTP-only, `SameSite=lax` cookies in production.
- 120-minute lifetime by default.
- Idle timeout is configured (`SESSION_IDLE_TIMEOUT`, default 30 min) and
  reported by readiness — **but see the gap below**.

### Gap: the idle timeout is configured but not enforced

`sekolah-kita.security.session_idle_timeout_minutes` exists and readiness
validates that it is positive and no longer than the session lifetime, **but no
middleware enforces it.** Reporting a control that does not exist is misleading,
so this is called out explicitly. Enforcement is a small piece of work
(a middleware that compares a last-activity timestamp against the configured
window) and is listed in §1.8.

## 1.6 Audit trail

Implemented and fail-closed. Recorded: authentication events, account lifecycle
transitions, PPDB application state changes and decisions, document upload,
status change, and download, administrator content publication, and audit-log
access itself. Sensitive values are redacted in `changed_fields` by
`AuditLogService`.

Gaps:

- **No alerting on audit-log access.** Reading the audit log is itself an
  audited event; nothing watches for unusual access patterns.
- **Retention is report-only.** `audit:maintenance` counts records past the
  retention boundary and refuses to delete. A hold-aware deletion procedure
  needs a legal/compliance decision first.
- **No SIEM export.** Requires a monitoring stack decision.
- **Correlation is per-request.** `correlation_id` is generated per request,
  which is enough to trace one request, but not to correlate a user action
  across requests. A session-scoped id would be better and is a small change.

## 1.7 `php artisan platform:readiness` and administrator security

| Check | Blocking | Meaning |
| --- | --- | --- |
| `administrator_mfa` | Yes (while required) | MFA is required and not implemented |
| `session_idle_timeout` | No | Configured sensibly; **not enforced** — see §1.5 |
| `audit_failure_mode` | Yes | `AUDIT_FAILURE_MODE` must be `throw` |
| `secure_cookies` | Yes | `SESSION_SECURE_COOKIE=true` |
| `http_only_cookies` | Yes | `SESSION_HTTP_ONLY=true` |
| `same_site_policy` | Yes | `lax` or `strict` |
| `trusted_proxies` | Yes | Explicit list, no wildcard |
| `trusted_hosts` | Yes | Explicit list, no wildcard |
| `bcrypt_cost` | Yes | `BCRYPT_ROUNDS` ≥ 12 |

## 1.8 Administrator security backlog

| Item | Type | Blocked on |
| --- | --- | --- |
| TOTP MFA | Implementation | Package approval + product decision |
| MFA step-up for sensitive actions | Implementation | MFA |
| MFA recovery / break-glass procedure | Process | Owner + MFA |
| Enforce the configured session idle timeout | Implementation — small | Nothing; should be done |
| "Sign out everywhere" self-service | Implementation — small | Nothing; registry exists |
| Alerting on password change and audit access | Operations | Monitoring stack decision |
| First-login password change | Implementation | Provisioning decision |
| Refuse to seed an administrator in production | **Not applicable** — the seeder creates no users | — |
| First-administrator provisioning command | Implementation — small | Provisioning decision |
| Session-scoped audit correlation id | Implementation — small | Nothing |
| Password rotation policy | Process | Owner |

---

# Part 2 — PPDB and media security

## 2.1 What is implemented and verified

### Access tokens

| Control | Implementation |
| --- | --- |
| Token generation | 32 random bytes, hex — 256 bits of entropy from `random_bytes` |
| Token storage | Only `sha256(raw)` is stored; the model marks `token_hash` `#[Hidden]` |
| Lookup | By hash, so the database cannot be dumped into working access tokens |
| Purpose separation | Enum-bound (`status`, `correction`, `withdrawal`); a status token cannot withdraw an application |
| Expiry | `PPDB_ACCESS_TOKEN_TTL_DAYS` (default 7) for status, 2 days for the others |
| Single use | `consumed_at` for correction/withdrawal purposes |
| Revocation | `revokeForApplication` / `rotate`; revoked tokens are rejected |
| Length guard | Inputs longer than 128 bytes are rejected before any query |
| Revocation on state change | See `AdmissionApplicationService` |
| Rate limiting | `ppdb-submit`, `ppdb-status`, `ppdb-upload` — keyed by HMAC of IP/session/token using `APP_KEY` |
| Last use | `last_used_at` recorded, for abuse investigation |

### Document upload

| Control | Implementation |
| --- | --- |
| Size limit | `PPDB_MAX_DOCUMENT_BYTES` (default 10 MB), enforced in the service **and** mirrored in Nginx `client_max_body_size` and PHP `upload_max_filesize` |
| Type allowlist | `application/pdf`, `image/jpeg`, `image/png` only |
| Extension allowlist | `pdf`, `jpg`, `jpeg`, `png` only |
| **Content validation, not just MIME** | `getimagesizefromstring()` must confirm the decoded image type matches the declared MIME; a renamed file fails |
| PDF content check | `%PDF-` magic bytes, and rejects `/JavaScript`, `/JS`, `/OpenAction`, `/AA`, `/Launch`, `/EmbeddedFile`, `/URI` |
| Image decompression-bomb guard | Max 6000×6000 and 20 megapixels |
| Metadata sanitisation | Images are decoded and **re-encoded**, stripping EXIF and any embedded payload; `metadata_sanitized_at` records it |
| Filename sanitisation | `basename` + allowlist substitution, truncated to 180 chars |
| Object key | Server-generated UUID under `admission-documents/`; the client filename never influences the path |
| Storage | Private disk (`storage/app/private`), `serve => false` — no URL is ever generated |
| Quarantine | New uploads are `Quarantined` / `scan_status = Pending`; not downloadable |
| Checksum | `sha256` recorded; re-verified on every inspection |
| Orphan cleanup | The stored object is deleted if the database write fails |
| Fail-closed | Exception if the object cannot be stored; no row is created |

### Document access

| Control | Implementation |
| --- | --- |
| Route-level policy | `ApplicationDocumentPolicy::downloadPrivate` requires an active, verified **administrator** who is the **assigned reviewer** for that application |
| Every request authorized | `Gate::forUser($actor)->authorize(...)` — not a hidden link |
| Status gate | Download refused unless `processing_status = Ready` **and** `scan_status = Clean` **and** `visibility = Private` and not soft-deleted |
| Existence re-check | The object is confirmed present at download time |
| No caching | `Cache-Control: private, no-store` |
| Filename | The sanitised original name is used for the download, not the object key |
| Audited | `admission_document.downloaded` recorded with document type, fail-closed |
| Not web-accessible | Private disk is outside the document root; Nginx `location ^~ /storage/` is `internal` |
| No public exposure | `MediaAsset::publicReady()` requires `processing_status = ready`, `scan_status = clean`, `visibility = public`, and `published_at <= now()` |

## 2.2 What is NOT implemented — read this before claiming malware scanning

**There is no malware scanner. There is no antivirus. There is no content
disarm and reconstruction.**

`MediaScanStatus::Clean` does **not** mean "scanned by a malware engine". It
means the application's own deterministic content checks passed:

- the file begins with `%PDF-` and contains none of a fixed list of dangerous
  PDF constructs, or
- the file decodes as an image whose real type matches the declared type and
  whose dimensions are within limits, and was then re-encoded.

### What that boundary actually means

| Detected | Not detected |
| --- | --- |
| Extension/MIME mismatch | Novel malware in a structurally valid PDF |
| Renamed non-image as image | Macro-free but exploit-bearing PDF objects |
| The specific PDF keywords listed | PDF features outside that keyword list |
| Images over the size/pixel caps | A logically malicious but well-formed image |
 | Anything requiring semantic analysis |

A determined attacker can get a file past these checks. The controls are
**worthwhile input validation and defence in depth**; they are not a malware
boundary, and this project must not describe them as one.

### The correct external control

An approved content-disarm-and-reconstruction (CDR) service or AV service
integrated at the `ProcessAdmissionDocument` job boundary. Requirements:

1. Documents remain `Quarantined`/`Pending` until the **external** service
   reports clean. The application must not self-release.
2. The service is on private networking; the application sends a reference, not
   the file over the public internet.
3. Failures leave the document quarantined. A scanner outage must not default
   to "clean".
4. Scan results are recorded on the `media_assets` row with the engine
   version and timestamp.
5. Replacement of the local content check with CDR is a decision, not a
   refactor — a CDR service will reject files the current checks accept, which
   changes the applicant-facing behaviour.

Until that exists, the honest statement is: **uploads are validated and
quarantined, but not malware-scanned.** Quarantine is what makes this
acceptable in the interim — an unreleased file cannot be downloaded, so an
undetected malicious file is inert as long as quarantine holds.

## 2.3 Remaining PPDB/media gaps

| Gap | Impact | Needed |
| --- | --- | --- |
| **No malware scanning** | An undetected malicious document is stored; inert only while quarantined | External CDR/AV service — product + infrastructure decision |
| **No PDF sanitisation** | PDFs are stored as submitted; only dangerous keywords are rejected | Replace with real PDF parsing/re-encoding |
| **No applicant contact verification** | Anyone can submit an application claiming any email/phone | Verification workflow — product decision. Also a runbook release blocker |
| **No antivirus on the public media path** | Admin-uploaded images go through the same checks; risk is lower and public by intent | Same CDR service |
| **No retention policy enforcement** | Applicant documents are retained indefinitely | Retention period, legal basis, deletion procedure — owner decision |
| **No duplicate-review merge procedure** | `duplicate_flag` exists and is indexed; the merge path is a code review | Documented reviewer procedure |
| **No per-download watermarking** | A reviewer who exfiltrates a document is not distinguishable from one who views it | Only meaningful with an external service; low priority |
| **No rate limit on administrator document download** | An assigned reviewer can bulk-download their caseload | Low risk (they are authorized for their caseload); consider a per-reviewer cap |
| **Object storage is local disk** | Backups are the only offsite copy | S3-compatible storage for private media — infrastructure decision |
| **Slow-query logging may capture PII** | `long_query_time=0.5` can log bound values | Retention and access policy for the slow log |

## 2.4 Positive note: the tests are adversarial, not nominal

`tests/Feature/MediaPrivacyTest.php` and `tests/Feature/AdmissionWorkflowTest.php`
exercise the negative cases — unauthenticated access, cross-reviewer access,
expired/revoked/wrong-purpose tokens, quarantined download refusal, MIME
spoofing, oversized files, and enumeration-shaped responses. This is what makes
the controls in §2.1 credible rather than aspirational.

## 2.5 PPDB/media readiness checks

| Check | Blocking | Meaning |
| --- | --- | --- |
| `storage_writable` | Yes | Private and public roots exist and are writable |
| `session_secure` / `http_only` | Yes | Document downloads must not traverse an insecure session |
| `trusted_hosts` | Yes | Prevents Host-header abuse of download URLs |
| `mail_transport` | Yes | Status mail to applicants needs a real transport |
| `audit_failure_mode` | Yes | Document access must be attributable |

**Not covered by any check, and cannot be:** whether a scanner exists. That is a
process attestation, recorded in the go/no-go document, not something software
can assert about itself.

---

# Part 3 — Rich text article body (added with the rich text editor)

The news body is now authored as HTML by a browser editor, which makes it an
untrusted HTML producer. The controls below are additive; nothing in Parts 1 and
2 changed. Full detail is in [`RICH_TEXT_EDITOR.md`](RICH_TEXT_EDITOR.md).

## 3.1 What is implemented and verified

| Control | Implementation | Notes |
| --- | --- | --- |
| Server-side sanitization | `RichTextSanitizer` on `symfony/html-sanitizer`, allowlist of 16 elements and 8 attributes | Applied on write *and* re-applied on read inside the cache closure |
| No inline script or style | `style` is not in the allowlist; alignment and image sizing are stored as `data-align` / `data-width` | A stored body never carries a `style` attribute |
| Event handler attributes | Not in the allowlist; `on*` is dropped | Verified for `onclick`, `onerror`, `onmouseover` |
| Link scheme control | `http`, `https`, `mailto`, `tel` only, relative links refused, `rel` forced on every link | `javascript:` and `data:` are dropped, link text preserved |
| Embedding elements | `script`, `iframe`, `object`, `embed`, `svg`, `form`, `style` and friends dropped with their content | |
| No inline image data | Base64 is not accepted and the image extension is configured with `allowBase64: false` | Images only ever exist as a media URL |
| Image source allowlist | `img[src]` is rewritten to the canonical public URL of a `media_assets` row that belongs to this article or to this editor session | A rewritten host, a foreign article's image, another administrator's staged image, and `data:` are all removed |
| Upload validation | Form request rules plus `getimagesize` plus decode/re-encode in `MediaAssetService` | Extension, sniffed MIME and real image header must agree |
| Upload limits | 5 MB, 6.000 x 6.000 px, 20 megapixels, JPG/PNG/WEBP only | |
| Storage naming | Random `media/<uuid>.<ext>`; the original filename is never trusted and never returned | |
| Upload authorization | Administrator only, server side, via `NewsArticlePolicy::uploadBodyImage` and `MediaAssetPolicy::create` | The endpoint is not reachable by a guest, a guru, or a siswa |
| Upload throttling | `news-image-upload`: 30/min and 120/hour per user, 60/min per IP | Each accepted image is decoded, re-encoded and written twice |
| Response minimalism | `id`, public `url`, `width`, `height`, `mime_type`, `max_bytes` | No private object key, no disk name, no original filename |
| Reference tracking | `news_article_media` | Prevents a still-referenced image from losing its public derivative |
| Legacy content safety | A body that is not rich text is stored and rendered exactly as before | Articles written before the editor existed are untouched |

## 3.2 Known, accepted boundaries

| Boundary | Why it is acceptable |
| --- | --- |
| A body image is published as soon as it is uploaded, including while the article is still a draft | The body stores a public URL, and every republication mints a new object key, so a stable URL requires the derivative to exist. Mitigated by the mandatory privacy review checkbox, the administrator-only endpoint, and `news:prune-staged-article-images` for abandoned uploads |
| The body limit of 20.000 characters applies to stored markup, not to prose | Pre-existing limit, unchanged. The editor shows a live character count so the limit is visible before saving |
| No markdown, no code block, no tables | The allowlist deliberately contains only what a school news article needs |

## 3.3 Tests

`tests/Unit/RichTextSanitizerTest.php` covers the sanitizer contract in
isolation, including a regression test for the boolean scoping of the image
allowlist. `tests/Feature/NewsRichTextEditorTest.php` covers the end-to-end
flow: create and edit with rich HTML, script and handler removal, `javascript:`
rejection, preserved headings and lists and images, upload authorization for
guests and non-administrators, upload rejection for bad type and bad size,
plain-text backward compatibility, the unchanged publication workflow, and
public rendering of sanitized rich content.
