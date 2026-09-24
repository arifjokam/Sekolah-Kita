# Sekolah Kita — Phase 0 Approval

**Document status:** Phase 0 architecture approval and migration decision register  
**Date:** 2026-09-24  
**Project:** Sekolah Kita  
**Primary sources:** `AGENTS.md`, `PROJECT_SPEC.md`, `docs/AI_PROJECT_ASSESSMENT.md`, and `docs/ARCHITECTURE_DECISIONS.md`

> This document records the architecture decisions approved for the first release and the decisions that remain open. It is a planning and approval artifact, not an implementation or migration specification. No application code, migration, database, `.env`, package, or existing application file is changed by this document.

## How to read this document

- **Approved** means a binding first-release boundary.
- **Open** means a decision still needs an owner and an explicit answer.
- **Working default** means a safe assumption for planning or non-schema work; it does not authorize a migration.
- **Migration-blocking** means the corresponding migration must not be created until the decision is approved.
- **Deferred** means the capability is not part of the first release and must not create placeholder schema.

The decisions in Section A close the architecture portion of Phase 0. Sections B–E preserve the remaining decision work. Section F defines the gate for starting implementation and, especially, for creating domain migrations.

## A. Approved architecture decisions

The following decisions are approved for the first release.

| ID | Approved decision | First-release implication and guardrail |
|---|---|---|
| **A-01** | **Single-school application.** | All records belong implicitly to one school. A singleton school profile may be modeled, but tenant isolation and a `school_id` on domain tables are not introduced. |
| **A-02** | **Modular Laravel monolith.** | Organize the application into clear domain modules. Controllers remain thin; Form Requests, Policies/Gates, services/actions, Jobs, Events, and Notifications own their appropriate responsibilities. Do not introduce microservices. |
| **A-03** | **Web-first application using Blade with Vite/Tailwind.** | The first release is a server-rendered web application. Progressive JavaScript may support UX, but a separate SPA, mobile application, or public API is not an initial dependency. |
| **A-04** | **MariaDB is the primary development and production database.** | MariaDB/InnoDB is the source of business truth. SQLite may be used only for isolated fast tests or experiments and must not be the primary application database. |
| **A-05** | **Redis is used in production for cache, sessions, queues, and rate limiting.** | Redis is shared infrastructure, not a permanent business store. Its connections, isolation, failure behavior, and operational setup must be defined before production use. |
| **A-06** | **Initial internal roles are `administrator`, `guru`, and `siswa`.** | Use stable role codes and default-deny authorization. Do not infer permissions from display labels or add custom roles without a later decision. |
| **A-07** | **Guardian records exist, but guardians have no login accounts in the first release.** | Guardians are domain records linked to students/applicants as appropriate. They must not be provisioned as `users` or receive a guardian role/portal. |
| **A-08** | **PPDB applicants are separate from internal user roles.** | An applicant is not automatically an administrator, guru, or siswa. An applicant may have a controlled, separate application-access mechanism, but that mechanism must not grant internal dashboard access. |
| **A-09** | **The first release is web-only.** | Do not add a public API, mobile application, API authentication, CORS contract, or mobile-specific data boundary unless a new architecture decision is approved. |
| **A-10** | **Authorization uses native Laravel mechanisms.** | Use authentication middleware, appropriate role middleware, Form Requests, Policies, Gates, ownership checks, and query scoping. UI visibility is never an authorization control. |
| **A-11** | **Student and PPDB personal data is never publicly exposed.** | There is no public student directory or public PPDB application/document access. Public projections must use an explicit, minimal allowlist; approved student achievements may not disclose student PII. |
| **A-12** | **Private documents use private storage and controlled access.** | PPDB documents, identity evidence, private exports, and similar files must not be placed on a public disk. Require an active authorization decision and short-lived signed or controlled access at download time. |
| **A-13** | **No multi-tenancy or `school_id` is introduced.** | Do not add speculative tenant columns, tenant-aware cache keys, or tenant filters. A second school is a product/architecture change, not a configuration toggle. |
| **A-14** | **The existing database is preserved; future schema changes use additive migrations.** | Do not edit, remove, or replace migrations already used in an environment. New migrations may add tables, columns, indexes, and constraints without destructive data loss. |
| **A-15** | **Destructive database commands are prohibited against the existing database.** | Never run `DROP`, `TRUNCATE`, `migrate:fresh`, `migrate:reset`, `db:wipe`, or equivalent against the existing database without an explicit, separately approved destructive-operation process. Phase 0 does not run database commands. |

### A.1 Consequences that are not optional

- No public API or mobile data surface is implied by the public website.
- No `school_id` may be added “just in case” to a table.
- No public student name, photograph, number, class, contact detail, academic record, or PPDB information may be exposed.
- A public achievement may describe an achievement only in a form that does not identify a student. A change to that boundary requires a new privacy decision.
- Private-document authorization must be checked before a signed URL is generated; possession of a URL is not permission.
- MariaDB remains the only durable business source. Redis cache/session/queue data may be rebuilt or recovered according to the operational plan.
- A role name is not a policy. Every protected operation needs a server-side authorization decision and a negative-access test.

## B. Decisions still requiring clarification

The following items remain open. They are not rejected; they are decisions that must be recorded before dependent work begins. The items marked **migration-blocking** affect the corresponding schema and must be answered before that migration file is created.

### B.1 Academic scope and people

#### B-01 — Academic scope and academic calendar (**migration-blocking**)

Confirm the first-release academic scope and answer:

- Which grade levels and school programs are supported?
- Is the academic model based on one year with one or more semesters/terms, or a different calendar?
- What are the start/end rules, active-period rules, and timezone for academic dates?
- Are `subjects`, homeroom teachers, teaching assignments, and enrollments sufficient for the first release?
- Are grades, attendance, schedules, extracurricular activities, report cards, and gradebooks explicitly out of scope?
- What promotion, completion, and academic-year rollover rules apply?

The first release may use the smaller academic context needed for identity, teacher scope, class placement, and enrollment, but that boundary must be explicit.

#### B-02 — Class structure (**migration-blocking**)

Confirm:

- Whether a class is identified by academic year plus grade plus section/name, and how it is named.
- Whether a class has one homeroom teacher, multiple homeroom teachers, or no homeroom teacher.
- Whether class capacity, status, start/end dates, and class history are required.
- Whether a class can be reused across academic years or must be year-specific.
- How a class is closed, merged, split, or renamed without losing history.

A conceptual `academic year + grade + section` model is a reasonable planning default, but it is not a final schema decision.

#### B-03 — Teacher assignment scope (**migration-blocking**)

Confirm:

- Whether access is granted by homeroom relationship, subject teaching assignment, or both.
- The exact dimensions of a teaching assignment: teacher, class, subject, academic year, and/or term.
- Whether a teacher may have multiple assignments, co-teach a class, or be replaced temporarily.
- Start/end dates and historical validity for assignments.
- Which student fields an assigned guru may view and which actions are permitted.
- Whether access is based on the current period only or includes historical academic records.

The authorization rule must be narrower than “all students.” A guru must have an explicit, time-bounded assignment or homeroom scope.

#### B-04 — Student enrollment and movement (**migration-blocking**)

Confirm:

- Whether a student has one active enrollment per academic year, per term, or per class assignment.
- How promotion, class transfer, withdrawal, graduation, re-enrollment, and leave are represented.
- Whether a student can be enrolled in multiple classes or activities at once.
- Who may place, change, approve, or revoke an enrollment.
- How the student number is allocated and whether it is permanent.
- Whether an enrollment is created manually, by PPDB conversion, or by both.
- How historical enrollment is retained and how current enrollment is selected.

The uniqueness rule and status transitions must be decided before an `enrollments` table is designed.

#### B-05 — Teacher/student identity and account linkage (**migration-blocking**)

Confirm:

- Whether a teacher or student profile has zero or one linked `user` account, and how a link is created or replaced.
- Required identifiers and uniqueness rules for NIP, student number, email, and other official numbers.
- Whether email can change after account creation and how the change is verified.
- Account status and deactivation behavior for teachers and students.
- How a person with historical records is represented after account deactivation.
- Duplicate-person detection and administrator correction procedures.

Authentication data must remain separate from teacher, student, and guardian domain data.

#### B-06 — Guardian records and relationships (**migration-blocking**)

The absence of guardian login is approved, but the domain model still needs decisions:

- Whether a guardian is linked to one or many students/applicants.
- Required relationship/authority fields and how primary contact is represented.
- Which identity/contact fields are collected and which are optional.
- Whether an applicant guardian record is copied, linked, or transformed into a student guardian record on acceptance.
- Retention, correction, and audit rules for guardian information.

### B.2 PPDB workflow and conversion

#### B-07 — Applicant identity, access, and consent (**migration-blocking**)

Confirm:

- Whether an applicant may save a draft, resume later, or view status through a limited applicant account.
- If no account is used, how a secure one-time status link or registration-number flow works.
- Which contact channel verifies the applicant and how duplicate submissions are prevented.
- Whether an applicant can edit data after submission and which corrections require a new version.
- Required consent/version capture and the lawful/operational basis for collecting applicant and guardian data.
- Which messages an applicant may receive and where they are delivered.

The applicant mechanism must remain separate from all three internal roles.

#### B-08 — PPDB workflow and status state machine (**migration-blocking**)

Approve an explicit state machine, including the names and meanings of all states, for example (not as a final decision): draft, submitted, validation, under review, accepted, rejected, waitlisted, withdrawn, and expired.

For each transition, define:

- The actor allowed to perform it.
- Required documents, conditions, and notes.
- Whether the transition is reversible.
- Applicant-visible versus administrator-only status and messages.
- Correction, resubmission, cancellation, and expiry behavior.
- Concurrency/locking behavior when two administrators act at the same time.
- The immutable status-history record and audit event.

Do not use a mutable “current status” alone to represent the workflow; the history model must be approved before the application table is migrated.

#### B-09 — PPDB documents (**migration-blocking**)

Confirm the document requirements and controls:

- Required document types by grade/program and whether conditional documents are allowed.
- Whether one file or multiple versions per document type are accepted.
- Allowed formats, MIME/content verification, maximum size, and naming rules.
- Whether documents have an expiry/validity date and must be replaced.
- Upload/replace/delete permissions, review status, and reviewer notes.
- Private-disk access, signed-URL lifetime, download auditing, and anti-enumeration rules.
- Malware scanning, quarantine, failed-processing behavior, and rejected-document cleanup.
- Retention and secure deletion after acceptance, rejection, withdrawal, or admission-cycle closure.

The storage policy is approved; these access and lifecycle details are not yet final.

#### B-10 — PPDB quotas and capacity (**migration-blocking**)

Confirm:

- Whether quota is defined per admission period, grade/program, class, track, or a combination.
- Whether quotas are hard limits, targets, or reservation limits.
- Whether there are reserved seats, waitlists, overflow rules, or priority categories.
- When quota is evaluated and whether a review decision can exceed it.
- How quotas interact with class capacity and student enrollment.
- How a quota changes after a period opens and whether changes are audited.
- Whether a period can have multiple intake rounds and how applications from earlier rounds are handled.

Quota data and status transitions must be designed together; a total quota alone is insufficient if selection or class placement is required.

#### B.11 — Review, ranking, and decision rules (**migration-blocking**)

Confirm:

- Whether one administrator or a panel reviews an application.
- Whether applications are ranked, score-based, quota-based, chronological, or manually decided.
- Whether internal scores/notes are stored, who can see them, and whether applicants can see any decision rationale.
- How conflicts of interest and reviewer reassignment are handled.
- What event constitutes final acceptance, rejection, or waitlisting.
- How changes to a decision are recorded and communicated.

#### B-12 — Acceptance and conversion to student (**migration-blocking**)

Define the complete, idempotent acceptance transaction:

1. Validate the application and selected admission period.
2. Re-check quota and class capacity.
3. Record the acceptance decision and status history.
4. Create or link the student profile.
5. Create or link guardian records as approved.
6. Allocate a student number and create enrollment/class placement.
7. Provision or invite a siswa account, if approved.
8. Record audit events and queue notifications.

Also decide:

- Whether acceptance automatically places the student or requires a later administrator placement step.
- Whether the applicant is converted immediately, only after parent confirmation, or after an enrollment deadline.
- What happens when account invitation, enrollment, or notification delivery fails.
- How duplicate acceptance, cancellation, withdrawal, or reversal is handled without losing history.
- Which data is copied from the application and which remains only in the admission record.

No conversion workflow may be inferred from a status label alone.

### B.3 Public visibility and media privacy

#### B-13 — Public teacher profiles (**migration-blocking for people/media fields**)

Confirm the public teacher-directory policy:

- Which professional fields are public: name, photo, subject, position, biography, and professional contact, for example.
- Whether NIP, employment status, internal address, personal phone/email, qualifications, or account data are public.
- Whether a teacher must opt in, whether an administrator may publish, and how withdrawal from publication works.
- Whether profiles are searchable, indexable, and linked from public pages.
- Photo consent, replacement, metadata removal, and accessibility requirements.

Until an allowlist is approved, teacher profiles remain internal and no public directory page is enabled.

#### B-14 — Public visibility of student achievements (**privacy-constrained and migration-blocking for achievement recipient data**)

The approved boundary is that student personal data is never publicly exposed. The remaining decision is what **non-identifying** achievement information may be published.

Confirm:

- Whether only achievement title, category, level, date, and school-level description may be public.
- Whether any student name, photo, class, student number, quotation, or other identifier is prohibited in public views and metadata.
- Whether achievements are published only as school-level or anonymized records.
- What internal recipient records and consent/approval flags are needed without exposing them publicly.
- How a recipient withdraws, a publication is corrected, or an achievement is removed.
- Whether a future decision to identify a student would be a change to A-11, rather than an implementation detail.

The safe default is anonymized public achievement content only. An identifiable student achievement is not authorized by this document.

#### B-15 — Gallery and media privacy (**migration-blocking for media/recipient schema**)

Confirm:

- Whether gallery albums are public, authenticated-only, or both, and whether unlisted/private albums exist.
- Who can upload, edit, publish, feature, or delete an album/item.
- Whether student or staff images require consent or administrator approval.
- Whether original-resolution files can be downloaded publicly.
- Whether captions, filenames, EXIF, alt text, and embedded metadata are treated as public data.
- Whether private media is streamed, served with signed URLs, or served through a separate download policy.
- Retention and deletion rules for originals, variants, abandoned uploads, and replaced assets.
- Moderation, abuse reporting, and access auditing requirements.

The safe baseline is: private by default, public only after explicit publication approval, and no student PII in a public asset or its metadata.

#### B.16 — Other public content visibility

For Berita, school profile, and public announcements, confirm publication roles, draft/approval states, author permissions, correction/archiving rules, and which fields are public. A public page may use a safe published-content default, but the final publication and moderation policy must be known before public-content migrations.

### B.4 Communication, privacy, and operations

#### B-17 — Notification channels (**migration-blocking for notification tables**)

Confirm:

- Which channels are in the first release: in-app/database, email, or both.
- Whether WhatsApp, SMS, push, or other channels are explicitly deferred.
- Which events notify administrators, teachers, students, applicants, or guardians.
- Whether users can opt out per channel/category and which notifications are mandatory security notices.
- Sender identity, templates, language, delivery timing, retries, bounce handling, and failure visibility.
- Whether applicant messages may contain personal data and how they are authenticated.
- Notification read-state and delivery-record retention.

#### B-18 — Audit log scope and retention (**migration-blocking for audit storage**)

Confirm:

- The minimum event catalog for authentication, account lifecycle, sensitive reads, private downloads, exports, PPDB, media, content, and administrative actions.
- Required actor, action, target, result, timestamp, request/correlation ID, IP/user-agent treatment, and redacted before/after fields.
- Whether failed/denied events are retained and for how long.
- Legal hold, correction, export, and administrator-access rules.
- What happens when an actor is deactivated or deleted.
- Backup copies, archival storage, and secure deletion of audit data.

#### B-19 — Data retention and deletion (**migration-blocking for personal-data storage**)

Approve a retention schedule for:

- Student and guardian profiles and historical enrollments.
- PPDB applications, reviews, status histories, documents, and rejected files.
- Student/teacher images, achievements, gallery media, and derivatives.
- Contact messages, notifications, exports, and operational logs.
- Audit records, backups, and temporary files.

For each class, define the retention period or event trigger, legal/operational hold, anonymization versus deletion, who authorizes deletion, and how backup copies expire. No real personal data should be collected until this schedule and the private-document process are approved.

#### B-20 — Backup, RPO, RTO, and restore ownership (**production gate; migration gate when retention metadata is stored**)

Confirm:

- Backup frequency and scope: MariaDB, private media, configuration, Redis durability needs, and application keys/secrets handling.
- **RPO (Recovery Point Objective):** the maximum acceptable data loss window.
- **RTO (Recovery Time Objective):** the maximum acceptable restoration time.
- Encryption, offsite location, access control, retention, and secure destruction of backups.
- Restore-test frequency and the exact person/team responsible for a restore.
- Whether a backup can be considered valid until a successful restore exercise is recorded.
- Recovery behavior when both application and object storage must be restored together.

A backup procedure without a tested restore is not a recovery plan.

#### B-21 — Administrator MFA (**migration-blocking for identity/security schema**)

Confirm:

- The required factor: TOTP authenticator app, WebAuthn/passkey, or another approved factor.
- Whether MFA is mandatory for every administrator before any administrative access.
- Enrollment, reset, recovery codes, device loss, administrator takeover, and audit requirements.
- Whether a helpdesk bypass exists and how it is controlled and reviewed.
- The behavior of an administrator whose MFA is not enrolled or temporarily unavailable.
- Whether staff accounts require MFA in the first release or only administrators.

MFA is a production launch requirement for administrators. The selected factor and recovery model must be fixed before identity/factor migrations are created.

#### B-22 — Performance workload and SLOs (**migration-blocking for final indexes and capacity architecture**)

Convert the headline “approximately 1,000 concurrent users” into measurable values. Confirm:

- Whether the target means active sessions, simultaneous browser users, in-flight requests, or another definition.
- Steady-state request rate, concurrent in-flight requests, burst profile, duration, and traffic mix.
- Representative data volumes for users, students, classes, news, achievements, gallery items, and PPDB applications.
- p50/p95/p99 latency targets by request class.
- Error-rate, availability, queue-age, and recovery targets.
- Whether public static/media traffic is served outside PHP-FPM.
- The VPS/PHP-FPM/MariaDB/Redis assumptions and the pass criteria for staging.

A capacity claim cannot be made until this workload is approved and load-tested.

#### B-23 — Redis, mail, storage, and failure behavior (**implementation/operations gate**)

The approved purpose of Redis does not decide every operational detail. Before production work, confirm:

- Redis connection/prefix isolation for cache, sessions, queues, and rate limits, and the behavior during Redis failure.
- Whether separate Redis instances are required for reliability or isolation.
- Mail provider, sender identity, queue behavior, and safe failure handling.
- Public and private object-storage topology, credentials, lifecycle rules, and provider failure behavior.
- Queue worker supervision, retry limits, idempotency, and failed-job handling.

These decisions may be implemented in later phases, but no production path may silently fall back to unsafe behavior.

#### B-24 — Locale, timezone, and data-entry conventions

Confirm Indonesian locale/display rules, `Asia/Jakarta` presentation, UTC storage and conversion, date/number formats, phone/address validation, name normalization, and official identifier validation. These conventions should be settled before date, identifier, or audit schemas are finalized.

## C. Decisions that can safely use reasonable defaults

These defaults are suitable for planning, documentation, and low-risk non-schema work. They do not remove a migration gate in Section D and must not be treated as silent stakeholder approval.

### C-1 Scope defaults

- Keep grades, attendance, schedules, extracurricular activities, report cards, gradebooks, finance, and billing out of the first release unless separately approved.
- Keep the academic implementation to the minimum context needed for academic periods, classes, assignments, and enrollment.
- Do not create tables for deferred modules merely because they are foreseeable.
- Keep the application web-only; do not create API routes, mobile models, or API-only resources.

### C-2 Domain and access defaults

- Keep authentication (`users`) separate from teacher, student, and guardian domain records.
- Plan for one stable internal role code per user rather than normalized custom RBAC; confirm the persistence strategy before the user migration.
- Model a class conceptually as an academic-year-scoped grade/section, with homeroom represented separately from subject teaching assignments until B-02/B-03 are decided.
- Default to deny: policies, query scoping, and ownership checks are required even where a role middleware also applies.
- No public student directory, student profile search, or student face gallery is included.
- Public student achievements are anonymized or school-level only; no student PII is included in public output, metadata, alt text, filenames, or links.

### C-3 Media and privacy defaults

- Private documents, identity evidence, exports, and unapproved media are private by default.
- Public media is published only after an explicit publication state; a public disk link is not an approval decision.
- Use random storage keys, server-side content validation, metadata removal for public images, and short-lived signed access for private files.
- Deny active content such as SVG unless a reviewed sanitizer is explicitly introduced.
- Use field-level projections for public views rather than loading a full sensitive model and hiding fields in Blade.
- Do not expose raw IDs, storage paths, registration numbers, or guessable filenames in public URLs.

### C-4 Security and workflow defaults

- Staff accounts are administrator-invited/provisioned; there is no unrestricted public registration.
- Account lifecycle starts with invitation/verification and uses active, suspended, and deactivated states; deactivation preserves history.
- Sessions regenerate after login and privilege changes; secure cookies and rate limiting are required in production.
- No administrator receives normal administrative access without MFA. A TOTP authenticator is a reasonable candidate default, but B-21 must still be approved before schema work.
- PPDB acceptance/conversion is a transaction with state history, audit, and after-commit notifications; it is never a direct update of a student row.
- PPDB applicants receive no internal role or dashboard access, regardless of the access mechanism chosen for the applicant flow.

### C-5 Communication and platform defaults

- Use in-app notifications and email as the initial channel set; defer WhatsApp, SMS, push, and other channels until requirements and providers are approved.
- Queue email, media processing, exports, and PPDB side effects; do not make the web request depend on an unbounded background task.
- Cache public, read-heavy data only. Do not globally cache personalized dashboards, student records, PPDB data, or private media.
- Use append-only audit behavior with redaction and no automatic hard deletion until the retention policy is approved.
- Do not make a production capacity claim until a staging load test and restore test pass.

### C-6 Planning defaults for non-functional work

A reasonable provisional workload for design and test planning is the hypothesis already documented in `docs/ARCHITECTURE_DECISIONS.md`:

- 1,000 active sessions in a rolling five-minute window, not 1,000 simultaneous in-flight requests.
- 100 dynamic requests/second sustained for 30 minutes, with up to 100 concurrent dynamic requests.
- A 250 requests/second burst for five minutes.
- An initial traffic hypothesis of approximately 70% public reads, 20% authenticated reads/dashboards, and 10% writes/contact/PPDB activity.
- p95 targets of approximately 250 ms for cache-hit public pages, 500 ms for dynamic public pages, 800 ms for authenticated lists, and 1,500 ms for writes/PPDB transitions; error rate below 0.1% under the target load.

These are **test hypotheses**, not an approved SLO or a promise that one VPS supports the target. B-22 must confirm or replace them before final index and capacity decisions.

## D. Decisions that must be finalized before database migrations

No migration file may be created for a domain area while its corresponding open decision is unresolved. A later phase may implement an already-approved area, but it must not create placeholder tables or nullable “maybe” columns to avoid making the decision.

### D-1 Migration gate by area

| Migration area | Must be approved first | Gate before creating the migration |
|---|---|---|
| **Existing `users` table and identity lifecycle** | B-05, B-21, account-status/deactivation rules, role persistence, email verification/invitation behavior, and administrator provisioning | Do not alter `users` or create role/factor/invitation tables until the identity and MFA model is approved. |
| **Teacher, student, and guardian records** | B-05, B-06, B-13, B-14, B-19, and the field-level privacy classification | Do not create people or guardian tables until profile/account links, relationships, public projections, and retention are approved. |
| **Academic years, terms, classes, and subjects** | B-01 and B-02, including level names, period boundaries, class status, and history rules | Do not create academic or class tables based only on conceptual names. |
| **Teaching assignments and homeroom relationships** | B-02, B-03, and the exact time-bounded access scope | Do not create assignment/relationship tables until server-side student scope can be expressed safely. |
| **Enrollments** | B-04, B-01, B-12, and the student-number/placement decision | Do not create enrollment tables or uniqueness constraints before promotion, transfer, withdrawal, and PPDB conversion rules are known. |
| **PPDB periods and applications** | B-07, B-08, B-10, B-11, B-12, and applicant consent/version rules | Do not create PPDB period/application tables before statuses, transitions, quotas, review ownership, and access boundaries are approved. |
| **PPDB guardians, documents, and reviews** | B-06, B-09, B-12, B-19, and private-download policy | Do not create document/review tables before required types, validation, retention, and reviewer access are approved. |
| **Public content and achievements** | B-13, B-14, B-16, publication states, recipient/display rules, and retention | Do not create public content or achievement-recipient tables with an unapproved public projection. |
| **Gallery and media** | B-15, B-12 where relevant, disk/visibility model, consent, processing, and retention | Do not create media metadata or visibility columns before public/private ownership and deletion rules are approved. |
| **Notifications and delivery records** | B-07, B-17, B-20, channel preferences, failure behavior, and retention | Do not create notification preference/delivery tables before the first-release channel set and applicant delivery model are approved. |
| **Audit log** | B-18, B-20, event catalog, redaction, append-only behavior, and retention | Do not create the audit store until its security fields and lifecycle are approved. |
| **Contact messages** | Contact moderation/anti-spam workflow and B-19 retention | Do not store real contact data before submission, moderation, abuse, and retention rules are approved. |
| **Performance-dependent indexes or partitioning** | B-22 workload/data volumes/query patterns and the resulting SLO | Base tables may be designed, but final indexes, partitioning, and capacity-specific choices wait for the approved workload. |
| **Backup/retention metadata or cleanup tables** | B-19 and B-20, including RPO/RTO, legal holds, and backup expiry | Do not encode retention or cleanup behavior that has not been approved. |

### D-2 Explicit migration prohibitions until the gates pass

- Do not create a migration for optional academic features (grades, attendance, schedules, extracurriculars, or report cards) while they are deferred.
- Do not create a public student directory, student face gallery, or identifiable public achievement relationship.
- Do not add `school_id` to any table.
- Do not add applicant accounts to the internal role model.
- Do not add guardian login/role fields for the first release.
- Do not add public API or mobile tables/routes.
- Do not add a normalized permissions system before a real custom-role requirement is approved.
- Do not add placeholder columns for a future PPDB state, quota, retention, or acceptance behavior.

### D-3 Additive migration rules for the eventual implementation

When a gate is approved, the migration plan must still:

1. Preserve the existing framework tables, migrations, and data.
2. Add schema through new migrations only; never edit or remove a migration already used in an environment.
3. Use MariaDB/InnoDB, `utf8mb4`, explicit foreign keys, deliberate delete behavior, and indexes for relationships and common filters.
4. Use transactions for multi-step workflows and make acceptance/conversion idempotent.
5. Use explicit status transitions and deactivation/soft-retention patterns for personal records.
6. Never place secrets, raw documents, or unnecessary PII in business/audit snapshots.
7. Include a data backfill, rollback/recovery, backup, and MariaDB integration-test plan without destructive reset commands.
8. Verify foreign keys, uniqueness, indexes, queue/notification side effects, and authorization with MariaDB-backed tests.

## E. Decisions that can be deferred until later phases

The following capabilities are explicitly outside the first-release baseline unless a new decision is approved. They must not be represented by speculative migrations.

| Capability | Safe first-release behavior | Revisit when |
|---|---|---|
| Grades, gradebook, attendance, schedules, extracurricular activities, report cards | Omit the feature and its tables; expose only the approved academic context. | A detailed academic curriculum and assessment requirement is approved. |
| Public student directory or student face gallery | Do not publish. | A separate privacy, consent, and publication decision changes the approved boundary. |
| Identifiable public student achievements | Publish only anonymized/non-identifying achievement content, if any. | Only through an explicit change to the student-privacy decision. |
| Guardian login, guardian portal, or parent account | Store guardian domain records only; no account. | A separate identity/privacy requirements decision is approved. |
| Public API, mobile application, or third-party integration | Web routes only. | A new API/mobile architecture decision defines authentication, versioning, rate limits, and data exposure. |
| Custom roles, delegated administration, fine-grained permissions, or permission packages | Use the three fixed internal roles and native policies. | A documented role/permission requirement cannot be met safely with the fixed model. |
| WhatsApp, SMS, push, or other notification channels | Use in-app and email only if approved; no provider-specific schema. | Channel providers, consent, delivery guarantees, and privacy requirements are approved. |
| Advanced PPDB scoring, automated ranking, payment, or external testing | Keep the workflow manual and auditable within the approved scope. | Selection rules and integrations are specified. |
| Public/private advanced media processing, video, CDN architecture, or multi-region storage | Use the minimum approved local/object-storage separation. | Media volume, formats, geography, and cost requirements are measured. |
| Read replicas, sharding, multi-school support, or horizontal redesign | Keep MariaDB source-of-truth and single-school scope. | Measured load or an approved product change requires a new architecture decision. |
| Advanced exports, data-subject request portal, and self-service deletion | Administrator-only controlled operations only after audit/retention rules exist. | Privacy operations and legal process requirements are defined. |
| Automatic hard deletion of personal data | Use deactivation, archival, and retention controls; do not delete historical records by default. | A formally approved retention/legal-hold policy defines when and how deletion occurs. |

Actual public pages and operational features may also be deferred even when their underlying data model is approved. For example, a public teacher directory page can follow the people migration, but the public field allowlist must still be decided before the people/media schema is finalized.

## F. Phase 0 exit criteria

Phase 0 is considered closed at the architecture level because the 15 decisions in Section A are approved. The **domain migration gate remains closed** until the applicable items in Section D have an explicit decision record. An open item may be deferred only when it does not affect the migration being created.

### F.1 Architecture approval

- [x] A-01 through A-15 are recorded as approved first-release decisions.
- [x] The single-school/no-`school_id` boundary is explicit.
- [x] Web-only, Blade/Vite/Tailwind, modular-monolith, MariaDB, and Redis boundaries are explicit.
- [x] Guardian and PPDB applicant account boundaries are explicit.
- [x] Native Laravel authorization and private-document requirements are explicit.
- [x] Additive-only migration and no-destructive-command safeguards are explicit.

### F.2 Domain and authorization approval

Before any people, academic, or PPDB migration is created, there must be an approved record covering:

- [ ] Academic scope, level/calendar, class structure, teacher assignment scope, and enrollment lifecycle.
- [ ] Teacher/student/guardian identity, relationships, identifiers, deactivation, and privacy classification.
- [ ] Final role/resource policy matrix, including assigned-class access and all denial paths.
- [ ] PPDB applicant access, consent, status state machine, review ownership, quotas, documents, and acceptance/conversion transaction.
- [ ] Public teacher fields, anonymized achievement policy, gallery/media visibility, and publication/consent rules.
- [ ] Data classification, retention, deletion/hold, and sensitive-read rules.

### F.3 Platform and operations approval

Before the corresponding implementation is released, there must be an approved record covering:

- [ ] Notification channels, recipients, preferences, sender, retries, and failure behavior.
- [ ] Audit event catalog, redaction, access, append-only behavior, retention, and legal holds.
- [ ] Data retention schedule and secure deletion/archival process.
- [ ] Backup scope, encryption/offsite policy, **RPO**, **RTO**, restore-test frequency, and operational owner.
- [ ] Administrator MFA factor, enrollment, recovery, and bypass policy.
- [ ] Redis topology/failure behavior, queue supervision, mail/storage provider choices, and monitoring.
- [ ] Performance workload, data volume, p95/p99 SLOs, error/availability/queue targets, and staging pass criteria.
- [ ] Locale, timezone, date/number formats, and official-identifier validation rules.

### F.4 Migration and implementation readiness

- [ ] Every planned migration has a decision-to-schema traceability record.
- [ ] No unresolved decision in Section D is a dependency of the migration being created.
- [ ] The migration is additive, MariaDB-compatible, indexed, foreign-key safe, and has a backup/restore and recovery plan.
- [ ] Authorization, privacy, negative-access, and transaction tests are specified for the feature.
- [ ] MariaDB and Redis integration-test environments are planned for the relevant phase.
- [ ] No public API, mobile surface, multi-tenancy, guardian login, or deferred academic module is introduced accidentally.
- [ ] The responsible product/technical/privacy owners approve the release scope before implementation begins.

### F.5 Phase 0 handoff statement

The architecture phase is approved, but **no business-domain migration is authorized by this document alone**. The next implementation increment may begin only for an approved area whose dependencies in Section D are resolved. Until then, unresolved decisions remain explicit planning work rather than implicit schema assumptions.

## Approval record

| Role | Decision owner | Status | Date |
|---|---|---|---|
| Product/school owner | To be assigned | Pending | — |
| Technical owner | To be assigned | Pending | — |
| Privacy/security reviewer | To be assigned | Pending | — |

The sign-off record does not replace the requirement that each migration has a documented, current decision reference before it is created.
