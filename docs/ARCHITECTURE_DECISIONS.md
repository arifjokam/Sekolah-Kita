# Sekolah Kita — Architecture Decisions

**Document status:** Phase 0 planning artifact — proposed decisions for approval  
**Date:** 2026-09-24  
**Project:** Sekolah Kita  
**Primary sources:** `PROJECT_SPEC.md`, `docs/AI_PROJECT_ASSESSMENT.md`, and `AGENTS.md`

> This document defines architecture and implementation boundaries before feature code, migrations, configuration changes, or database changes are made. It is not an implementation plan containing completed work. No application code, migration, database, `.env`, package, or deployment configuration is changed by this document.

## 1. Decision summary

The recommended direction is a **single-school, server-rendered modular monolith** built on Laravel 13, with MariaDB as the source of truth and Redis introduced as shared production infrastructure.

| ID | Decision | Status |
|---|---|---|
| D-001 | First release is a web application with a public website and role-based dashboards. | Proposed |
| D-002 | The current product is a single-school application; do not add multi-tenancy. | Recommended; must be explicitly confirmed |
| D-003 | Use a modular monolith, not microservices. | Proposed |
| D-004 | Initial access model is one fixed internal role per user: `administrator`, `guru`, or `siswa`. | Proposed |
| D-005 | PPDB applicants do not automatically receive a general application account or one of the three internal roles. | Proposed |
| D-006 | Authorization uses native Laravel Gates, Policies, middleware, Form Requests, and query scoping. | Proposed |
| D-007 | Student, guardian, and PPDB personal data is private by default and is never exposed as a public directory. | Proposed |
| D-008 | Public media and private documents use separate filesystem disks; production is ready for S3-compatible object storage. | Proposed |
| D-009 | Redis is the production target for sessions, cache, queues, rate limiting, and short-lived distributed coordination. | Proposed |
| D-010 | Multi-step or non-request side effects are eventually dispatched to supervised queues after the database transaction commits. | Proposed |
| D-011 | Security-sensitive actions and sensitive reads are recorded in an append-only, redacted audit trail. | Proposed |
| D-012 | “Approximately 1,000 concurrent users” means 1,000 active sessions, not 1,000 simultaneous in-flight requests; capacity remains unverified until load testing. | Proposed performance definition |
| D-013 | Production requires HTTPS, secure cookies, security headers, least-privilege access, tested backups, monitoring, and a hardened deployment. | Required baseline |

The decisions marked **proposed** should be approved or amended before the phase that depends on them begins. Decisions that affect the first domain migrations should be approved before any migration is written.

---

## 2. PROJECT SCOPE

### 2.1 Product objective

Sekolah Kita is a school information system and school website for one school. The first release must provide:

1. A public, mobile-friendly, accessible school website.
2. Authenticated dashboards whose content and actions vary by role.
3. Controlled management of school, people, public content, and admissions data.
4. A secure, auditable PPDB process for applicants and administrators.
5. A maintainable path to future school operations without introducing premature infrastructure complexity.

The implementation should use Laravel conventions and remain a modular monolith. The assessment correctly identifies that the current repository is a Laravel skeleton, not a completed application.

### 2.2 In scope for the first product increment

| Area | Initial scope |
|---|---|
| Public website | Home page, school profile, published Berita, Prestasi, Galeri, approved teacher information, PPDB information, and Contact form. |
| Identity and access | Administrator-managed user accounts, login/logout, password reset, email verification, account activation/deactivation, and role-scoped dashboards. |
| School profile | One school identity, contact information, vision/mission, branding assets, and public-facing school information. |
| Guru | Teacher profile and public professional directory fields; internal administrative data where explicitly approved. |
| Siswa | Student profile, private enrollment data, account linkage, and self-service access to only their own records. |
| Guardians | Guardian records and relationships to students; no guardian login in the initial release unless separately approved. |
| PPDB | Admission period information, public application flow, private document upload, review workflow, status history, and acceptance-to-student conversion. |
| Content management | Berita, Prestasi, Galeri, publishing status, moderation, and media metadata. |
| Communication | Contact submissions, moderation, in-app notifications, and queued email delivery where approved. |
| Platform | Audit logging, cache policy, queue jobs, media processing, security controls, and operational health checks. |
| Quality | Unit, feature, authorization, integration, browser/security, and performance tests appropriate to each phase. |

### 2.3 Explicitly deferred until requirements are approved

These are not rejected permanently, but they are not part of the initial implementation baseline:

- Multi-school or multi-tenant support.
- Public unrestricted user registration.
- A separate guardian/parent portal or guardian user role.
- A mobile application or public API.
- Full learning-management functionality such as grades, attendance, schedules, extracurricular activities, report cards, and gradebooks.
- Online payments, billing, payroll, procurement, and other finance functions.
- Native mobile applications.
- Complex organizational delegation, custom roles, or a package-based permission system.
- Public student directories or student face galleries.

Academic context needed to assign a Guru to a class or to place a student in a class may be included. The broader academic features above require a separate requirements decision and should not be inferred from this document.

### 2.4 Scope boundaries

- The public website is a presentation layer over domain modules; it does not own duplicate copies of domain data.
- Identity data is separate from teacher and student profile data.
- Redis is infrastructure, not the permanent source of business truth.
- Media binaries are stored outside MariaDB; only metadata and references are stored in the database.
- A dashboard reads domain data but does not become a second source of truth.
- No implementation should begin for a deferred feature merely because a database relationship could support it.

---

## 3. SINGLE-SCHOOL ARCHITECTURE

### 3.1 Decision

**Use a single-school architecture for the current project.**

All records belong implicitly to the one school represented by Sekolah Kita. The application will have a singleton `school_profiles` record (conceptually), but will not add a `school_id` tenancy column to every domain table. Authorization and queries do not need a tenant scope because there is one tenant.

This is the recommended architecture because the current product represents one school website and the specification does not identify a business requirement for hosting multiple independent schools.

### 3.2 Trade-offs

| Option | Benefits | Costs and risks |
|---|---|---|
| Single-school | Simpler queries and policies; fewer joins and indexes; lower operational complexity; straightforward local development; less risk of accidental cross-school data exposure. | A future multi-school product would require a broad, expensive data migration, policy review, cache-key change, job change, and media segregation change. |
| Multi-school / multi-tenant from day one | Supports future isolation and multiple customers without a foundational tenancy retrofit. | Adds `school_id` scope, composite uniqueness, tenant resolution, tenant-aware policies, tenant cache keys, tenant jobs, tenant media prefixes, and operational complexity before any second school is known to exist. |

### 3.3 Guardrails for the single-school decision

- Do not add `school_id` merely as speculative flexibility; unused tenancy is still a correctness burden.
- Keep the school profile behind a service or configuration boundary so school-wide settings are not scattered through the codebase.
- Use stable primary keys and avoid embedding the school name into business identifiers or storage paths.
- Keep authorization policies explicit even though there is one school; one school does not mean one role or one audience.
- Treat adding a second school as a product change requiring a new architecture decision, not an incremental configuration change.
- If multi-school support becomes a committed requirement before meaningful production data exists, revisit the decision in Phase 0 and design the tenancy migration before feature migrations are created.

### 3.4 Future migration warning

Retrofitting multi-tenancy after real data exists would affect, at minimum:

- `users`, teachers, students, guardians, content, admissions, notifications, and audit records.
- Foreign keys and unique constraints, which may need tenant-aware composite indexes.
- Every policy and query scope.
- Cache keys, rate-limit keys, queue payloads, exports, and reports.
- Public media paths and object-storage access policies.
- Existing audit history and migrations already deployed.

This cost is a reason to confirm the product decision now, not a reason to build multi-tenancy prematurely.

---

## 4. USER ROLES

### 4.1 Initial role model

The first release uses one stable internal role code per user:

- `administrator`
- `guru`
- `siswa`

Display labels may be localized, but authorization must use the stable codes. A custom role or fine-grained permission system should be introduced only when a documented requirement cannot be met safely with these three roles.

The role belongs to the user account. Teacher and student domain records are separate profiles linked to the user. A teacher or student profile may exist before a login account is activated, and a student account may be linked later during admission or administration.

### 4.2 Responsibilities and boundaries

#### Administrator

Responsible for:

- School profile and public branding.
- User account provisioning, role assignment, invitations, activation, suspension, and deactivation.
- Teacher, student, guardian, and relevant academic directory data.
- PPDB periods, applications, review, decisions, and conversion to student records.
- Berita, Prestasi, Galeri, and publication workflows.
- Contact message moderation.
- Notification broadcasts and system-level configuration.
- Audit-log review, operational status, and approved exports.
- Security and data-retention administration.

Boundaries:

- Administrator access is broad but not a reason to expose passwords, raw tokens, private documents publicly, or unnecessary PII in logs.
- Destructive actions and sensitive exports require explicit authorization, a reason where appropriate, and an audit record.
- Administrator access does not replace the need for input validation, least privilege, or retention rules.

#### Guru

Responsible for:

- Their own account and teacher profile.
- Viewing and updating the fields explicitly approved for self-service.
- Viewing students in assigned classes or teaching assignments, using the minimum data needed for their work.
- Creating or editing content drafts only where the content policy grants that ability.
- Viewing published school content and their own notifications.
- Accessing academic information assigned to them, if academic modules are approved.

Boundaries:

- No general user-management or system-settings access.
- No access to all students merely because a user is a Guru.
- No PPDB review or access to PPDB documents unless a later, explicit exception is approved.
- No access to other teachers’ private profile data or administrative audit records.
- Assigned-class scope must be enforced server-side, not only in the interface.

#### Siswa

Responsible for:

- Their own account, profile, notifications, and approved personal data.
- Viewing their own enrollment and academic information, if those modules are implemented.
- Viewing published public content.
- Managing their own password and account security where supported.

Boundaries:

- No access to another student’s private record.
- No access to guardian records beyond information the school explicitly permits.
- No access to staff administration, PPDB review, audit logs, or system settings.
- No public student account registration that bypasses administrator or admission workflows.

### 4.3 Account types outside the initial roles

- **PPDB applicant:** an applicant identity and application, not automatically an administrator, Guru, or Siswa account. The applicant may receive status notifications through a controlled, privacy-preserving flow.
- **Guardian:** a person record linked to one or more students. Guardians do not receive an initial login role.
- **Public visitor:** an unauthenticated visitor with access only to explicitly public content.

---

## 5. AUTHENTICATION

Authentication is a platform capability. It must be completed and tested before protected dashboards or administrative routes are added.

### 5.1 Login and logout

- Use Laravel's native session authentication for the first web release.
- Login uses a normalized email address and password.
- Successful login must regenerate the session ID to prevent session fixation.
- Logout is a state-changing POST request protected by CSRF and invalidates the session and remember token where applicable.
- Public registration must not create a general unrestricted user. Staff accounts are administrator-invited or provisioned through a controlled process. Students are provisioned by an authorized administrator or accepted PPDB workflow.
- Login responses must not reveal whether an email address exists.

### 5.2 Password hashing and password policy

- Store only a one-way password hash using Laravel's `Hash` facade and the existing `hashed` model cast pattern.
- Prefer an adaptive algorithm such as Argon2id when the production PHP and application configuration support it; otherwise use a securely configured bcrypt policy.
- Never log, audit, queue, export, or display plaintext passwords or password hashes.
- Initial minimum password policy: a non-default password of at least 12 characters, with a configurable maximum and no arbitrary composition rule that makes passwords less memorable.
- Passwords must be changed through an authenticated flow or a short-lived reset flow. Existing password entry may be required for a normal password change.
- Password reset tokens must be cryptographically random, stored hashed, single-use, and expire after a short period (proposed: 60 minutes).
- A successful password reset must invalidate existing sessions and remember tokens and notify the account owner.

### 5.3 Session handling

Proposed production baseline:

- Server-side sessions, with Redis as the production driver and the existing database driver retained only for low-traffic development fallback until Redis is available.
- Session ID cookie is `HttpOnly`, `Secure` in production, scoped to the application path, and uses an appropriate `SameSite` policy (initial proposal: `Lax`).
- Session payload contains identifiers and minimal state only. Do not place student records, identity documents, passwords, tokens, or unnecessary PII in sessions.
- Idle session lifetime: proposed 120 minutes for authenticated users.
- Absolute session lifetime: proposed 12 hours, with reauthentication required afterward.
- Regenerate the session after login, role changes, privilege changes, and password reset.
- Invalidate sessions when an account is suspended or deactivated.
- Do not use local-file sessions when more than one application node is used.
- A `remember me` feature, if enabled, must use secure token rotation and account-wide revocation.

These lifetimes are initial security proposals and must be confirmed before production configuration is finalized.

### 5.4 Email verification

- Administrator and Guru accounts must verify their email address before receiving normal protected access.
- Siswa accounts must verify their email before viewing or changing private personal data.
- A pending or unverified account may receive only the minimum verification/explanation page.
- Verification links and notifications must be rate limited, short-lived, single-use, and generic where account enumeration is a concern.
- PPDB email verification is part of the admission flow and must not silently create a general dashboard account.
- If a mail provider is unavailable, the flow must fail safely and provide a controlled administrator resend mechanism rather than bypassing verification.

### 5.5 Rate limiting

Rate limiting is required before exposing login, reset, contact, PPDB, or upload endpoints. Redis-backed rate limiting is the production target. Initial limits are policy values to be tuned using monitoring, not hard-coded product promises.

| Operation | Initial protection direction |
|---|---|
| Login | Per-IP and normalized-account key, short burst limit, exponential backoff or temporary lockout, and a generic 429 response. |
| Password reset | Per-email and per-IP limits to prevent enumeration and abuse. |
| Email verification/resend | Per-user and per-IP limits. |
| Contact submission | Per-IP and, when appropriate, a message fingerprint; require moderation and spam controls. |
| PPDB submission | Per-IP/device/session and application/period limits, with duplicate detection and bot protection considered. |
| Uploads | Per-user/IP quotas, file-size limits, and a daily storage/throughput guardrail. |
| Search or expensive public queries | Per-IP and per-session limits, with pagination and query budgets. |

Rate-limit keys must not contain raw email addresses, identity numbers, or other unnecessary PII. Use a one-way digest or a privacy-safe key where possible.

### 5.6 Account status

The initial lifecycle is:

```text
invited -> pending_verification -> active -> suspended -> deactivated
```

Rules:

- Only `active` accounts may use normal protected access.
- Invitations are administrator-controlled, expire, and store tokens hashed.
- Suspension blocks new sessions and protected requests.
- Deactivation preserves historical academic, content, notification, and audit references.
- Personal records are not hard-deleted as a substitute for deactivation.
- Status and role changes are audited.
- Account recovery is an administrator-assisted, rate-limited process; it must not allow an unverified email address to take over an existing account.

### 5.7 Administrator authentication hardening

Multi-factor authentication (MFA) is a production launch requirement for administrator accounts, with a documented temporary compensating control if the selected mail/provider setup cannot yet support it. The implementation mechanism must be decided before identity hardening is complete; no public API token should be introduced as a substitute for administrator security.

---

## 6. AUTHORIZATION / RBAC

### 6.1 Native Laravel authorization model

Authorization must be enforced in layers:

1. **Middleware:** authentication, verified email, active account, and coarse role checks for route groups.
2. **Form Requests:** input validation plus authorization for create/update operations.
3. **Policies:** resource-level abilities such as `view`, `create`, `update`, `delete`, `publish`, `review`, and `downloadPrivate`.
4. **Gates:** cross-cutting capabilities such as `manage-school-profile`, `manage-users`, `view-audit-log`, `manage-system-settings`, and `review-ppdb`.
5. **Query scoping and ownership checks:** ensure list queries and route model bindings cannot fetch an unauthorized record before rendering it.
6. **Tests:** prove both allowed and denied behavior for every role and sensitive resource.

The default is deny. Hiding a button or menu is never an authorization control.

### 6.2 Proposed role/resource matrix

| Resource or capability | Administrator | Guru | Siswa | Public |
|---|---|---|---|---|
| School profile | Manage | View | View | View approved public fields |
| User accounts | Manage | Own account only | Own account only | None |
| Teacher professional directory | Manage | View | View approved public fields | View approved public fields |
| Teacher private/admin fields | Manage | Own approved fields | None | None |
| Student personal data | Manage | Assigned students only, minimum necessary | Own record only | None by default |
| Guardian data | Manage | Only if explicitly required for an assignment | Only explicitly permitted own-linked information | None |
| Berita | Create/edit/publish/archive | Create/edit assigned drafts if granted | View published only | View published only |
| Prestasi | Create/edit/publish/archive | Create/edit assigned entries if granted | View published only | View published without sensitive recipient details |
| Galeri | Manage albums and media | Upload/edit if granted | View published items | View approved published items |
| PPDB information | Manage | None | Own application only if linked | View public information; submit application |
| PPDB review and documents | Manage/review | None | Own status/limited messages only | None |
| Contact messages | Moderate/manage | None unless assigned | None | Submit only |
| Dashboard | All role-specific views | Assigned classes and own work | Own data | Public landing page |
| Notifications | Own and broadcast | Own | Own | None |
| Audit log | View | None | None | None |
| System settings/security operations | Manage | None | None | None |

The final policy must be written per resource before implementation. The table is a starting decision, not permission to infer access from a role name in a controller.

### 6.3 Ownership and scope rules

- A Guru may access a student only through an approved assignment or class relationship and only the fields required for the task.
- A Siswa may access a record only when its linked user identity matches the authenticated user.
- A content contributor may edit only records assigned to them or explicitly created by them; publication remains a separate ability.
- A PPDB reviewer may access an application only through an administrator-authorized review scope.
- Private document download authorization must be checked before a signed URL is generated.
- Query results must be paginated and filtered before rendering; direct IDs must not bypass scope.
- Administrative access to sensitive reads and exports must be audited.

### 6.4 Role middleware

Role middleware may protect a route group, but it is not sufficient for a model operation. For example, `role:administrator` may guard `/admin`, while the `UserPolicy` still decides whether that administrator can operate on a particular user. Conversely, a policy may allow a Guru to view an assigned student without granting broad Guru middleware access to all student routes.

---

## 7. DOMAIN MODULES

### 7.1 Module principles

Use a modular monolith. Each module owns its domain behavior and has explicit boundaries:

- Controllers remain thin.
- Form Requests own validation and request authorization.
- Policies own resource authorization.
- Services/actions own multi-step workflows.
- Eloquent models represent domain data.
- Jobs own non-request side effects.
- Notifications own user-facing messages.
- Events may be used to decouple modules without creating hidden synchronous dependencies.
- Cross-module reads should use stable read models or narrowly defined services; cross-module writes should go through the owning module.

A module must not silently write another module's tables or duplicate its business rules.

### 7.2 Module boundaries

| Module | Owns | Does not own | Main dependencies |
|---|---|---|---|
| **Identity and Access / User Management** | User accounts, roles, account status, invitations, authentication profile, session-related account lifecycle | Teacher/student profile facts, PPDB application content, academic records | School configuration for branding; audit and notifications |
| **School Profile** | Singleton school identity, public contact details, vision/mission, branding metadata | News, people records, system secrets | Media; user-managed authorship; public website |
| **People: Guru, Siswa, Guardians** | Teacher, student, guardian profiles, links, privacy classification, directory projections | Grades, attendance, schedules unless separately approved | Identity, School Profile, Media, Academic context |
| **Academic context (supporting module)** | Academic years, terms, classes, subjects, teaching assignments, enrollments, class scope | Optional grades, attendance, schedules until approved | People, School Profile; feeds Guru scope and dashboard |
| **Public Content** | Berita, categories, Prestasi, recipients, Galeri albums/items, drafts, publication state | Private documents, unrestricted people records | School Profile, People projections, Media, Identity for authors |
| **Media** | Media metadata, disks, visibility, variants, checksums, processing state, signed-download policy | Business meaning of a news item or PPDB document | Filesystem, Queue, Identity for uploader, owning domain module |
| **PPDB** | Admission periods, applications, applicant/guardian data, document references, status history, review and acceptance workflow | General user administration, public news | School Profile, People, Identity, Media, Notifications, Audit |
| **Contact** | Contact submissions, spam/moderation state, response workflow | School identity, user accounts | School Profile, Notifications, Audit, rate limiting |
| **Dashboard** | Role-specific read models, summaries, and navigation | Canonical domain records or cross-module write rules | All relevant domain modules; policy/permission services |
| **Notifications** | Notification center, read state, preferences, delivery records and channel adapters | Canonical business state | Identity, domain events, Mail/Queue |
| **Audit Log** | Append-only security and business-event records, sensitive-read/export records | Domain source data; it must not become a second copy of a record | Identity, all protected modules, request context |
| **Platform / Operations** | Cache invalidation, queue jobs, scheduled tasks, reports/exports, health and operational commands | School domain rules | Redis, MariaDB, filesystem, mail, observability |

### 7.3 Public Website composition

The Public Website is a read-oriented composition of School Profile, Public Content, the approved Guru projection, PPDB public information, and Contact submission. It should not create a separate public-content database. Public query models must select only public-safe fields and apply publication status.

### 7.4 Dependency order

The intended dependency direction is:

```text
Identity and Access
        |
        v
School Profile ----> People / Academic ----> Dashboard
        |                    |                  ^
        v                    v                  |
Public Content <------ Media                 |
        ^                    ^                 |
        |                    |                 |
        +------ PPDB ---------+-----------------+
                 |
                 v
       Notifications / Audit / Platform
```

The arrows indicate allowed high-level dependencies, not permission to bypass a module's policy or service boundary. Audit and notification integrations must be available at the minimum level before PPDB and other sensitive workflows go live.

---

## 8. DATABASE ARCHITECTURE

This is a **logical model only**. It is not a migration specification, and no migration should be created until the tenancy, privacy, PPDB, academic, and retention decisions are approved.

### 8.1 General database decisions

- MariaDB/MySQL is the primary source of truth in development and production.
- Use InnoDB, `utf8mb4`, strict SQL mode, explicit foreign keys, and indexed foreign-key columns.
- Use append-only migrations after the existing framework migrations. Do not edit or remove migrations already used in an environment.
- Use transactions for multi-step writes such as PPDB acceptance, enrollment creation, and related audit/notification events.
- Use explicit status transitions and soft deletion or deactivation for personal records instead of destructive deletion.
- Store media binaries outside the database; store metadata, ownership, visibility, and references in the database.
- Use unique constraints for normalized emails, NIP, student numbers, registration numbers, slugs, and tenant-independent business identifiers.
- Plan indexes for status/date filters, search columns, foreign keys, and common sort orders. Search implementation must be selected after measuring MariaDB behavior; do not assume SQLite or a simple `LIKE` query is sufficient.
- Do not store secrets, plaintext passwords, reset tokens, or raw document binaries in business tables or audit snapshots.
- Redis data may be derived and disposable; it must not be the only copy of business state.

### 8.2 Conceptual entity map

```text
users 1 ---- 0..1 teachers
users 1 ---- 0..1 students
students * ---- * guardians (through student_guardians)

school_profiles (singleton)

academic_years 1 ---- * terms
academic_years 1 ---- * classes
classes * ---- * subjects (through teaching_assignments)
teachers 1 ---- * teaching_assignments * ---- 1 classes
students 1 ---- * enrollments * ---- 1 classes

news_articles * ---- * news_categories
news_articles 1 ---- * media_assets (cover/content references)
achievements 1 ---- * achievement_recipients * ---- 0..1 students/teachers
gallery_albums 1 ---- * gallery_items * ---- 1 media_assets
media_assets 1 ---- * media_variants

admission_periods 1 ---- * admission_applications
admission_applications 1 ---- * application_guardians
admission_applications 1 ---- * application_documents
admission_applications 1 ---- * application_status_histories
admission_applications 0..* ---- 0..1 admission_reviews
accepted admission_application 0..1 ---- 1 student/enrollment

contact_messages
users 1 ---- * notifications
users 0..1 ---- * audit_logs (actor)
```

The diagram is conceptual. Some relationships may use explicit join tables or carefully constrained references; an unconstrained polymorphic relation is not acceptable where referential integrity or privacy isolation matters.

### 8.3 Identity and access entities

| Entity | Important logical fields and relationships | Classification and lifecycle |
|---|---|---|
| `users` | Existing identity columns plus internal role, account status, last-login metadata, locale/timezone preference if approved, and lifecycle timestamps. Has optional one-to-one teacher/student profile. | PII for name/email; password hash is credential data; deactivation preserves history; never hard-delete by default. |
| `user_invitations` | Email, role, token hash, expiry, inviter, accepted/revoked timestamps. | PII and authentication metadata; single-use and expiring. |
| `password_reset_tokens` | Existing framework infrastructure; token hash, email, expiry semantics. | Credential/security data; never expose in logs or audit snapshots. |
| `sessions` | Framework session records if the database driver is used in development. | Security/session data; not a business record and not a place for PII. |
| `account_audit` or equivalent events | Account status, role, verification, invitation, and recovery history may be represented in the central audit log rather than duplicated here. | Append-only security history. |

A fixed role code is sufficient initially. `roles`, `permissions`, `role_user`, and `permission_role` are deferred until a real custom-role requirement is approved.

### 8.4 School and people entities

| Entity | Important logical fields and relationships | Classification and lifecycle |
|---|---|---|
| `school_profiles` | Singleton identity, legal/public name, address, public contact, vision/mission, logo/hero references, social links, active dates. | Some fields public; internal contact/configuration fields restricted. Exactly one active profile is an application invariant. |
| `teachers` | One-to-one optional `user_id`, NIP, approved identity/contact fields, employment status, biography, photo reference. | PII; private fields role-restricted; deactivate rather than hard-delete. |
| `students` | One-to-one optional `user_id`, unique student number, identity and enrollment fields, legal name, birth data, address/contact fields where necessary, status, photo reference. | Highly sensitive PII; no public exposure by default; retain according to approved policy. |
| `guardians` | Name, relationship, phone/email, address, identity information only if required. | Sensitive PII; no public access; no initial account. |
| `student_guardians` | Student/guardian relationship, authority/primary-contact flag, effective dates. | PII link; restrict and audit reads. |
| `academic_years` | Start/end dates, label, active status. | Internal academic data. |
| `terms` | Academic year, start/end dates, active status. | Internal academic data. |
| `subjects` | Name, code, description, active status. | Internal academic data. |
| `classes` | Academic year, grade level, name, homeroom teacher reference where approved, status. | Internal student/teacher data; class membership is authorization-sensitive. |
| `teaching_assignments` | Teacher, class, subject, academic year, active dates. | Internal scope data; controls Guru access. |
| `enrollments` | Student, class, academic year, status, enrollment date, leaving date if applicable. | Sensitive student data; history must be retained. |
| `class_teachers` | Additional class/teacher relationship only if homeroom and teaching scope cannot be represented clearly by assignments. | Internal access-control data. |

Grades, attendance, schedules, extracurricular activities, and report cards are intentionally not included in the initial entity commitment. Their existence must be decided before related migrations.

### 8.5 Public content and media entities

| Entity | Important logical fields and relationships | Classification and lifecycle |
|---|---|---|
| `news_categories` | Name, slug, description, active status. | Public metadata. |
| `news_articles` | Title, slug, sanitized body, excerpt, status (`draft`, `scheduled`, `published`, `archived`), author, published timestamp, SEO metadata. | Public only when published; draft content restricted. |
| `news_category_article` | Article/category join with explicit constraints. | Public metadata when article is published. |
| `achievements` | Title, description, category, level, date, publisher, status, sanitized body/media references. | Public only when approved/published. |
| `achievement_recipients` | Explicit student/teacher recipient relationship, display consent/approval state, visibility. | PII-sensitive; public display must be explicit and minimal. |
| `gallery_albums` | Title, description, cover, status, published timestamp, sort order. | Public only when published. |
| `gallery_items` | Album, media asset, caption, event date, sort order, publication status. | Public only when approved. |
| `media_assets` | Disk, path/key, original name, MIME, byte size, checksum, visibility, uploader, scan state, metadata state, timestamps. | Metadata can contain PII; private asset metadata is restricted. |
| `media_variants` | Source asset, transformation, path/key, width/height, format, checksum, processing status. | Derived asset metadata; same visibility as source unless explicitly approved. |

Use explicit foreign keys or well-controlled join tables for critical ownership. Do not use a raw user-provided path or filename as an authorization or storage key.

### 8.6 PPDB entities

| Entity | Important logical fields and relationships | Classification and lifecycle |
|---|---|---|
| `admission_periods` | Academic year, opening/closing timestamps, quota, status, public information. | Public for approved period information; operational details restricted. |
| `admission_applications` | Opaque registration number, period, applicant identity/contact data, status, submission timestamp, consent/version information, source/channel. | Highly sensitive PII; never public. |
| `application_guardians` | Application, guardian identity/contact/relationship, primary-contact flag. | Highly sensitive PII; never public. |
| `application_documents` | Application, document type, private media asset reference, verification status, uploaded-by, reviewed metadata. | Private document; signed access only and audited. |
| `application_status_histories` | Application, previous/new status, actor, reason/note, timestamp, request correlation ID. | Sensitive workflow history; append-oriented. |
| `admission_reviews` | Application, reviewer, decision, internal note, review timestamp. | Role-restricted; notes must not leak to applicants. |
| `admission_announcements` | Approved public PPDB information or announcement, status, publication time. | Public only after publication. |
| Accepted applicant link | Optional link from an accepted application to the created/linked student and enrollment. | Sensitive cross-domain link; created transactionally. |

A PPDB application should not be considered a general user account. If an accepted applicant becomes a Siswa, the workflow must explicitly create or link the student profile and account, with consent and audit records.

### 8.7 Communication, platform, and operational entities

| Entity | Important logical fields and relationships | Classification and lifecycle |
|---|---|---|
| `contact_messages` | Name, contact details, subject, message, moderation status, received timestamp, assigned reviewer. | PII; never public; retention policy required. |
| `notifications` | Laravel database notification type/data, recipient user, read state, timestamps. | User-restricted; minimize PII in payload. |
| `notification_preferences` | User, category, channel, enabled state. | User-restricted; avoid storing secrets. |
| `audit_logs` | Actor, action, target type/id, result, redacted before/after data, request/correlation ID, timestamp, privacy-safe network and client metadata. | Security data; administrator-only, append-only, retention-controlled. |
| `site_settings` | Only settings not represented by the school profile, with type, scope, and safe validation rules. | Administrator-only; never store secrets in plain settings. |
| `jobs`, `job_batches`, `failed_jobs` | Existing Laravel queue infrastructure, operationally replaced or complemented by Redis queues. | Operational data; monitor and retain according to policy. |
| `cache` and `cache_locks` | Framework cache tables if used in development; not business source of truth. | Disposable operational data. |

### 8.8 Required indexes and constraints

Plan indexes for:

- Normalized unique user email and invitation email.
- User role, status, and last-login filters.
- Unique teacher NIP and student number.
- Student/guardian relationship and primary-contact lookups.
- Academic year, class, enrollment status, and teaching assignment scope.
- News status and publication date, plus unique slug.
- Achievement date/category and recipient relationships.
- Gallery album, publication status, and sort order.
- Media disk/path uniqueness and processing/scan status.
- Opaque PPDB registration number, period, status, and submission time.
- Notification recipient and unread state.
- Audit actor/timestamp and target/timestamp.

All foreign keys should be indexed and have deliberate delete behavior. Personal records should generally use `restrict`, `nullOnDelete` only where the relationship permits it, or explicit deactivation/retention workflows rather than cascading deletion.

### 8.9 PII and sensitive data inventory

PII includes, but is not limited to:

- Names, email addresses, phone numbers, addresses, and birth dates/places.
- NIK or other national identity numbers, NIP, student numbers, and registration numbers when linkable.
- Guardian identity and contact information.
- Student photos, identity documents, birth certificates, transcripts, and other uploaded evidence.
- Login metadata such as IP address, device/user-agent information, and session history when it can identify a person.
- PPDB answers, selection decisions, reviewer notes, and status history.

Credential or security data includes:

- Password hashes, reset tokens, verification tokens, remember tokens, and session identifiers.
- Application keys, database credentials, mail credentials, Redis credentials, and API secrets.

Credential data must not be public, cached, included in exports, or placed in ordinary logs. Sensitive document binaries and exports belong in private storage.

### 8.10 Data lifecycle and deletion

Before production, define retention periods and deletion/archival rules for:

- Student and guardian records.
- PPDB applications and rejected documents.
- Private media and derivatives.
- Contact messages.
- Notifications.
- Audit logs and request metadata.
- Backups and exports.

Use status transitions, legal/operational holds where necessary, and documented deletion jobs. A hard delete must not remove an audit record needed to explain a historical action. Backups must follow their own retention and secure-destruction policy.

---

## 9. PRIVACY AND DATA ACCESS

### 9.1 Classification model

| Classification | Examples | Access rule |
|---|---|---|
| **Public** | School name and approved profile, published Berita, approved Prestasi, public Galeri, approved teacher professional fields, PPDB period information | May be shown to unauthenticated visitors, but only from explicitly public fields and published records. |
| **Authenticated-user only** | Own account settings, own notifications, own dashboard summary, own approved profile | Requires an active authenticated session; ownership and resource policy are still required. |
| **Role restricted** | Student personal data, guardian data, class membership, assigned academic data, content drafts, PPDB applications and reviews, contact messages | Requires the correct role plus explicit resource scope; Guru access is limited to assignments where applicable. |
| **Administrator only** | User management, account status, PPDB review, audit logs, system settings, data exports, security operations | Administrator policy and audited operation; not automatically visible in the UI or logs. |
| **Private storage** | Student identity documents, PPDB documents, private exports, backups, sensitive diagnostic data | Stored outside the public web root; accessed through an authorization check and short-lived signed URL or controlled stream. |

### 9.2 Public student-data rule

There is **no public student directory or student PII in the initial release**.

A future public achievement or gallery item may identify a student only when the school has an explicit publication approval/consent process, the minimum necessary fields are shown, and the image/document contains no embedded metadata. Student number, address, birth data, identity documents, guardian details, and academic records are not public by default.

### 9.3 Access principles

- Apply field-level projections for public and role-restricted responses; do not load a full sensitive model and hide fields only in Blade.
- Never expose IDs, filenames, storage paths, or signed URLs that allow enumeration of private records.
- Do not use public cache keys or URLs containing student, guardian, or PPDB identifiers.
- Keep private data out of browser-readable API/debug responses and error pages.
- Use pagination, minimum necessary columns, and query-level filters to prevent bulk disclosure.
- Require a fresh authorization decision when issuing a private download URL; possession of a URL is not a substitute for an active permission check.
- Log sensitive reads and exports, not merely writes.
- Apply retention and deletion rules to public and private data alike.

---

## 10. MEDIA STORAGE

### 10.1 Disk separation

Use Laravel filesystem abstractions and explicit disk configuration. The application must not hard-code filesystem paths.

| Storage class | Development | Production | Access |
|---|---|---|---|
| Public images and approved public derivatives | Local `public` disk or equivalent | Public object-storage bucket/prefix served through Nginx/CDN | Publicly cacheable after publication and moderation |
| Private student images/documents | Local private disk outside `public` | Private S3-compatible bucket/prefix | Authenticated, authorized, short-lived signed access only |
| PPDB documents and identity evidence | Local private disk outside `public` | Private S3-compatible bucket/prefix | Administrator/reviewer authorization and audited download |
| Temporary processing files | Local temporary disk | Temporary object-storage prefix with lifecycle rules | Short-lived; never treated as permanent storage |
| Exports/backups | Private local or managed storage | Encrypted private object storage/offsite storage | Explicit administrator authorization; no public URL |

The public disk may contain only content that has passed publication and privacy review. `public/storage` being linked is not a security control; it only enables serving files from that disk.

### 10.2 Media metadata and processing

- Store original filename, MIME type, byte size, checksum, disk/key, uploader, visibility, scan status, and processing state in the database.
- Store binaries outside MariaDB.
- Use random, non-identifying storage keys; do not include names, NIK, student numbers, or applicant registration numbers in paths.
- Generate thumbnails and responsive variants asynchronously after upload.
- Strip EXIF and other unnecessary metadata from public images.
- Use separate source and derivative visibility rules; a private source must not produce a public derivative by accident.
- Make deletion explicit and retention-aware, including database metadata, variants, object versions, and caches.

### 10.3 Upload controls

Every upload path must apply:

- Authentication and resource authorization where appropriate.
- File-size and request-size limits.
- Extension allowlist plus server-side MIME/content inspection; extension alone is insufficient.
- Image decoding/validation and dimension limits for images.
- Randomized filenames and safe storage keys.
- Malware scanning for PPDB documents and any other untrusted document type.
- Metadata sanitization for public images.
- Safe handling of formats that can execute content, such as SVG, or deny them unless a documented sanitizer is in place.
- Download auditing and short-lived signed URLs for private files.

If validation fails, the file must be removed or quarantined according to a documented policy and the request must not leave a usable media reference.

### 10.4 Object-storage readiness

The domain should depend on a media service and Laravel disk abstraction rather than an S3-specific model. Production may use separate public and private buckets, or isolated prefixes with separate access policies. The public bucket/prefix may be served by a CDN; private objects must not be exposed through a public CDN path.

---

## 11. REDIS

Redis is a production infrastructure target, not a business database. The current assessment reports that Redis is not running locally, the PHP Redis extension is not installed, and sessions/cache/queues currently use the database driver. This document does not change that state.

### 11.1 Intended production uses

| Use | Design |
|---|---|
| **Cache** | Cache public, read-heavy data such as school profile, published content lists, public navigation, and gallery metadata. Use explicit TTLs, versioned keys, and deterministic invalidation on publish/update. |
| **Sessions** | Store server-side sessions in Redis for shared application nodes. Keep payloads small and free of sensitive PII. |
| **Rate limiting** | Use Laravel rate limiters backed by Redis for login, reset, contact, PPDB, search, and upload protection. |
| **Queues** | Dispatch mail, notifications, media processing, exports, and maintenance work to Redis-backed queues. |
| **Distributed coordination** | Use short-lived locks or deduplication markers for narrowly defined concurrent work. Do not use Redis as a replacement for database transactions or durable business state. |

### 11.2 Isolation and reliability

- Use separate configured connections, prefixes, and credentials for cache, sessions, queues, and rate limits.
- Logical Redis databases are not strong security isolation. Use separate instances or managed services where reliability and isolation require it.
- Choose queue persistence and eviction behavior separately from cache behavior; a cache eviction policy must not silently discard queued jobs.
- Configure Redis authentication, private networking or TLS, memory limits, persistence appropriate to the workload, and health monitoring.
- Never place raw student, guardian, or PPDB PII in Redis keys, values, or queue payloads when an ID and a protected database lookup suffice.
- Do not silently fall back to a database session or rate-limit store in production after a Redis failure unless the failure behavior has been explicitly designed and tested.
- Redis must have a documented backup/recovery or rebuild strategy because its data is disposable.

### 11.3 Cache policy

- Cache only data that is safe for the requested audience.
- Invalidate or version public content keys after publication, archive, media replacement, or profile changes.
- Avoid globally caching personalized dashboards, student data, PPDB applications, or contact messages.
- Measure hit ratio, origin load, regeneration cost, and stale-data behavior before adding more caching.
- Do not let cache availability become an authorization bypass.

---

## 12. QUEUES

Queues are introduced for work that should not block a web request or that is retryable and observable. They are not a replacement for authorization, validation, or the core database transaction.

### 12.1 Candidate jobs

| Job group | Examples | Requirements |
|---|---|---|
| `mail` | Password reset, verification, PPDB status, contact response | Retry with backoff, idempotency, no unnecessary PII, delivery monitoring. |
| `notifications` | In-app/email notification fan-out and read-state processing | Respect preferences and account status; retry safely. |
| `media` | Thumbnail generation, image validation, metadata stripping, virus scan, derivative cleanup | Private/public visibility preserved; failed jobs quarantine source and notify operations. |
| `ppdb` | Document processing, duplicate checks, status notification preparation | Idempotent by application/document ID; no status transition outside the owning service. |
| `exports` | Audit, student, applicant, and operational reports | Authorized, encrypted/private output, expiry, audit record. |
| `maintenance` | Retention cleanup, cache warming, stale media cleanup, backup checks | Scheduled, observable, safe to retry, and covered by restore/runbook tests. |

### 12.2 Queue design rules

Every job must define:

- An explicit queue name and connection.
- Retry count, backoff, timeout, and maximum attempts.
- Idempotency or deduplication behavior.
- Failure logging and a dead-letter/failed-job policy.
- Cleanup and retention behavior.
- Monitoring for queue depth, oldest job age, failures, and worker health.
- A payload containing identifiers and minimal context rather than full sensitive records.

Multi-step database workflows dispatch jobs after commit, or use a transaction-aware dispatch mechanism. A job must not observe a partially committed PPDB or enrollment state.

Queue workers must be supervised by a process manager such as systemd or Supervisor, restarted on failure, and monitored independently from the web tier. A dedicated queue dashboard is optional and should be added only when operational requirements justify it.

---

## 13. AUDIT LOGGING

### 13.1 Purpose

The audit log is a security and accountability record, not a replacement for domain history. It must answer who did what, to which record, when, through which request, and with what result, without becoming an unsafe store of secrets or raw personal documents.

### 13.2 Events that must be audited

#### Authentication and account lifecycle

- Successful and failed login where failure details are safe to retain.
- Logout and session revocation.
- Password reset request, completion, and invalidation of sessions.
- Email verification and resend.
- Invitation creation, acceptance, expiry, and revocation.
- Role, status, activation, suspension, deactivation, and account recovery changes.
- Administrator MFA changes and recovery events, if enabled.

#### Sensitive data access

- Viewing or exporting student or guardian PII.
- Searching or listing sensitive records outside ordinary dashboard navigation.
- Downloading a private student or PPDB document.
- Viewing PPDB applications, reviewer notes, and identity documents.
- Accessing contact messages containing personal information.
- Generating sensitive exports.

#### Domain changes

- Create, update, archive, restore, export, or delete of teacher/student/guardian records.
- PPDB submission, validation rejection, review, status transition, acceptance, rejection, and conversion.
- Berita, Prestasi, and Galeri draft, publish, unpublish, archive, and deletion.
- Media upload, visibility change, download, replacement, and deletion.
- Contact moderation and response actions.
- School profile and system-setting changes.
- Permission, policy, role, and security-control changes.

### 13.3 Required audit fields

Conceptually record:

- Actor user ID, with a safe representation for deleted/deactivated actors.
- Action name and result (`success`, `denied`, or `failed` where appropriate).
- Target type and target ID, with no unnecessary sensitive payload.
- Timestamp in UTC and an application correlation/request ID.
- IP address or a privacy-safe representation, user agent, and relevant route context.
- Redacted before/after values for approved fields.
- Reason or justification for sensitive export/review actions where policy requires it.
- Schema/version identifier for the event format.

### 13.4 Safety and retention

- Never record passwords, password hashes, reset/verification tokens, session IDs, raw identity documents, or full PPDB answers.
- Redact or hash sensitive values in snapshots and error payloads.
- Make security-sensitive changes and their critical audit record transactional where practical, so a successful privileged change cannot occur without an attributable record.
- Use append-only behavior for audit records; corrections are new events, not edits to history.
- Restrict audit reads to administrators or a separately approved security role.
- Define retention, legal hold, export, and deletion rules before production.
- Monitor audit-write failures, but do not expose internal details to ordinary users.

A full audit event for every public page view is not required and would add noise. Sensitive reads, writes, exports, and administrative actions are the priority.

---

## 14. PERFORMANCE TARGET

### 14.1 Current status and capacity claim

The existing requirement says the application should grow toward approximately 1,000 concurrent users. **No claim is made that the current system, a single VPS, or any proposed design already supports 1,000 users.** Capacity is unverified until representative load tests pass.

### 14.2 Measurable interpretation for planning

For the first capacity test, interpret the target as:

- **1,000 active sessions** in a rolling five-minute window, including a representative mix of anonymous visitors, Guru, Siswa, and Administrator sessions. This is not 1,000 simultaneous in-flight HTTP requests.
- **100 dynamic requests per second sustained for 30 minutes** as the initial steady-state benchmark.
- **Up to 100 concurrent in-flight dynamic requests** at the steady-state benchmark.
- A separate **250 requests/second burst for five minutes**, including login/session activity and a PPDB submission burst, to test headroom.
- Static and approved public media requests should be served by Nginx/CDN/object storage and measured separately from PHP-FPM capacity.
- A starting traffic mix of approximately 70% public read traffic, 20% authenticated read/dashboard traffic, and 10% authenticated writes, contact submissions, and PPDB activity. This is a test hypothesis, not an observation of production traffic, and must be revised with real usage data.

This definition prevents “1,000 concurrent users” from being used as an unsupported promise.

### 14.3 Initial service-level objectives

These are proposed launch targets measured at the server/application boundary and must be validated in staging:

| Request class | Target |
|---|---|
| Cache-hit public page | p95 ≤ 250 ms, p99 ≤ 750 ms |
| Dynamic public page | p95 ≤ 500 ms, p99 ≤ 1,000 ms |
| Authenticated dashboard/list | p95 ≤ 800 ms, p99 ≤ 1,500 ms |
| Write, review, or PPDB transition (excluding file transfer and email delivery) | p95 ≤ 1,500 ms, p99 ≤ 3,000 ms |
| Server error rate under the target load | Less than 0.1%, excluding deliberate client validation errors |
| Normal queue age | Oldest actionable job under 5 minutes |
| Peak queue age | Oldest actionable job under 15 minutes, or an explicitly approved exception |
| Monthly availability target | 99.9% for the public website and authenticated application, subject to the hosting and backup plan |

The final SLOs must include the chosen VPS sizing, database capacity, network assumptions, and RPO/RTO.

### 14.4 Important endpoint classes

Load tests must cover:

- `/` and school profile.
- Public Berita listing and detail.
- Public Prestasi listing and detail.
- Galeri album and media pages.
- Public Guru directory and detail.
- PPDB information, application form, document upload, and review transitions.
- Contact submission and moderation.
- Login, logout, reset, and verification.
- Role-specific dashboards.
- Teacher/student/guardian lists and detail pages.
- Administrator user, PPDB, content, and audit pages.
- Private media download/streaming.
- Notification center and queued email side effects.

An API is not assumed. If an API is later added, it needs its own versioning, authentication, rate-limit, resource, and load-test contract.

### 14.5 Database requirements

- Use pagination and bounded page sizes for all large tables.
- Eager-load relationships and prevent N+1 queries.
- Select only required columns, especially on dashboards and public pages.
- Add indexes based on actual query plans, status/date filters, and relationship lookups.
- Keep PPDB acceptance and enrollment conversion transactional.
- Monitor slow queries, lock waits, connection count, buffer-pool/cache behavior, and MariaDB CPU/IO.
- Align PHP-FPM worker count and database connection limits with the measured workload.
- Consider read replicas or read scaling only after a real read-heavy bottleneck is measured.
- Test MariaDB behavior separately from SQLite.

An initial query budget should be established for each list/dashboard during Phase 1, with a review trigger when a page exceeds its budget. No unmeasured optimization should be added solely to meet the headline number.

### 14.6 Cache requirements

Cache suitable public read paths:

- School profile and public navigation.
- Published Berita and Prestasi lists/details.
- Gallery metadata and approved public image URLs.
- Public PPDB information.

Use explicit TTLs, versioned keys, invalidation on publication/update, and deployment cache warming where useful. Do not globally cache personalized dashboards, student records, PPDB applications, contact messages, or private media responses. Measure hit/miss ratios and origin load before tuning.

### 14.7 Load-testing requirements

Before production, run a staged test using representative seeded data and the actual production-like stack:

1. Establish a baseline with one application node and a small number of PHP-FPM workers.
2. Ramp from low traffic to the defined 1,000-session/100-RPS profile.
3. Test anonymous public browsing, cached and uncached requests, authenticated dashboards, and role-specific mixed traffic.
4. Test login/session bursts, password-reset abuse protection, PPDB submissions/uploads, and contact spam controls.
5. Test concurrent public media requests and verify that they are served by Nginx/CDN rather than PHP-FPM.
6. Run at least a 30-minute steady test and a defined burst test with cold and warm cache conditions.
7. Inject controlled failures such as Redis interruption, queue-worker failure, database saturation, and object-storage latency; verify safe degradation and recovery.
8. Record requests per second, p50/p95/p99 latency, error rate, PHP-FPM saturation, MariaDB connections/slow queries, Redis latency/memory, queue depth/oldest job, worker memory, and media-origin traffic.
9. Tune and repeat the test. A capacity claim requires a successful, documented staging result on the proposed production topology.

A small single VPS must not be assumed to support the target without measurement.

---

## 15. SECURITY BASELINE

The following controls are minimum requirements before production. They apply even when a feature is not yet publicly available.

### 15.1 Application security

- **CSRF:** Protect every state-changing web request with Laravel CSRF tokens. Do not use GET for mutations. Keep an appropriate `SameSite` cookie policy.
- **XSS:** Rely on Blade's default escaping; sanitize any explicitly supported rich content; avoid raw output unless a reviewed sanitizer and safe rendering path exist.
- **SQL injection:** Use Eloquent/query-builder parameter binding. Do not interpolate user input into SQL. Review and constrain any necessary raw SQL.
- **Mass assignment:** Use explicit fillable/guarded allowlists, Form Requests, and DTOs/value objects for sensitive workflows. Do not bind request arrays directly to models.
- **Authorization:** Enforce server-side policies, Gates, middleware, ownership, and query scope for every protected operation. Default deny.
- **Authentication security:** Use password hashing, secure reset/verification flows, account status, session regeneration, rate limits, and administrator MFA before launch.
- **Rate limiting:** Protect login, reset, verification, contact, PPDB, search, upload, and future API endpoints.
- **Secure file uploads:** Enforce size, type, content, authorization, randomized names, safe storage, malware scanning where appropriate, and retention controls.
- **MIME/type validation:** Check both extension and server-detected content; reject executable or active content; validate image dimensions and sanitize public metadata.
- **Private file access:** Store private files outside the public root and use authorization plus short-lived signed URLs or controlled streaming. Never treat a URL alone as permission.
- **Password security:** No plaintext, no default credentials, no fixed production seeder credentials, secure hashing, reset invalidation, and compromised-password review before launch.
- **Session security:** HTTPS-only secure cookies, `HttpOnly`, `SameSite`, regeneration, inactivity/absolute limits, small payloads, and revocation on security events.
- **Security headers:** Configure at Nginx and, where appropriate, Laravel: HSTS, CSP, `X-Content-Type-Options: nosniff`, Referrer-Policy, frame protection, Permissions-Policy, and a reviewed CORS policy.
- **HTTPS:** TLS 1.2 or newer, automatic HTTPS redirect, secure cookies, HSTS after validation, no mixed content, and correct trusted-proxy configuration.
- **Secrets management:** Keep credentials and application keys outside source control, use environment-specific secret injection, rotate them, and never reproduce them in logs, docs, backups exposed to the web, or audit snapshots.
- **Debug and errors:** Production must use `APP_ENV=production`, `APP_DEBUG=false`, generic error pages, and production-appropriate log levels.
- **Backups:** Encrypted, offsite, regularly tested database and private-media backups, with documented retention, RPO/RTO, restoration, and access controls.
- **Logging and monitoring:** Structured, rotated or centralized logs; no secrets or unnecessary PII; monitoring for authentication abuse, authorization failures, rate limits, queue failures, storage errors, database health, and availability.
- **Dependencies and runtime:** Automated dependency advisory scanning, controlled updates, secure container/base-image practices, and a vulnerability response process.
- **Privacy governance:** Document consent, retention, deletion, export, incident response, and data-subject/request procedures before collecting real personal data.

### 15.2 Security verification gate

Before production, provide evidence for:

- Authentication and authorization tests for every role.
- Negative tests for cross-user and cross-role access.
- CSRF, XSS, SQL injection, mass-assignment, and upload security tests.
- Rate-limit behavior and generic error responses.
- Private URL expiry and access-denial tests.
- Dependency and secret scans.
- Nginx/PHP-FPM and security-header review.
- Backup restoration exercise and incident/rollback runbook.

---

## 16. ENVIRONMENT ARCHITECTURE

### 16.1 Development environment

Development is intended for:

- Android with Termux.
- Ubuntu PRoot on ARM64.
- PHP 8.5+ and Composer.
- Laravel application with Vite.
- MariaDB, using the `sekolah_kita` database when available.
- Redis as the intended shared development service for integration tests, but the current assessment reports that Redis is unavailable and the PHP Redis extension is missing.
- Local filesystem disks for development.
- Mail logged to the local log driver unless a safe test mail service is configured.
- Database-backed session/cache/queue drivers as an acceptable temporary low-traffic fallback, provided the production Redis path is tested separately.

Development rules:

- Do not use production data or production secrets.
- Do not treat SQLite as the primary application database.
- Use in-memory SQLite only for fast unit tests or isolated experiments where MariaDB-specific behavior is irrelevant.
- Add MariaDB/Redis integration tests for foreign keys, transactions, indexes, sessions, cache, and queues.
- Do not run destructive database commands against the shared development database without explicit approval.
- Do not modify Android system files or projects outside `/home/dev/workspace/sekolah-kita`.
- Keep package installation and runtime changes outside this Phase 0 document.

### 16.2 Production environment

The target production topology is:

```text
Browser
   |
 HTTPS / HTTP2 or HTTP3
   |
 Nginx (TLS, static assets, security headers, rate limits)
   |
 Stateless Laravel application / PHP-FPM (OPcache, production caches)
   |              |                 |
 MariaDB       Redis              S3-compatible object storage
 (source of     sessions, cache,   public media, private documents
 truth)        queues, limits
   |
 Supervised queue workers and scheduler
   |
 Mail provider, monitoring, logs, encrypted backups
```

Production responsibilities include:

- Ubuntu VPS with a supported, patched operating system.
- Nginx with explicit root, HTTPS, security headers, static asset caching, upload limits, and trusted-proxy settings.
- PHP-FPM with OPcache, production settings, worker sizing, and health/readiness endpoints.
- MariaDB/MySQL with restricted network access, credentials, backups, slow-query monitoring, and tested restore.
- Redis with authentication, private networking or TLS, appropriate persistence/eviction separation, and monitoring.
- Supervisor or systemd for queue workers and scheduled tasks.
- HTTPS certificate automation and renewal monitoring.
- Object storage/CDN for public media and private storage for documents.
- Structured log collection, error monitoring, uptime checks, disk/CPU/memory alerts, and queue-depth alerts.
- Encrypted offsite backups and a documented restore and rollback process.
- Non-root service accounts, least-privilege filesystem/database permissions, firewall rules, and secure deployment credentials.
- Reviewed release procedures. Generic `composer setup` scripts that run migrations or initialize SQLite must not be used as production deployment commands.

### 16.3 Environment separation

Development, staging, and production must use separate:

- Database credentials and databases.
- Redis instances or isolated credentials/prefixes.
- Mail providers and sender identities.
- Object-storage buckets/prefixes and credentials.
- Application keys and session/cache namespaces.
- Audit and log destinations where feasible.

A production deployment must never use a developer's local `.env`, fixed test account, or database reset command.

---

## 17. DEVELOPMENT PHASES

The roadmap is dependency-aware. Each phase has an exit gate; later phases must not compensate for missing security or data-boundary decisions in earlier phases.

### Phase 0 — Requirements and architecture decisions

Deliverables:

- This architecture decision document.
- Approval of single-school versus multi-school direction.
- Initial role and permission matrix.
- Privacy and data-classification rules.
- PPDB scope, statuses, required documents, and review ownership.
- Academic scope decision.
- Guardian-account decision.
- Media/retention and backup policy decisions.
- Notification channels and locale/timezone decisions.
- Performance workload definition and initial SLOs.
- API/mobile scope decision.

Exit gate: no domain migration is written until the decisions affecting its entities, relationships, and privacy are approved.

### Phase 1 — Secure application and infrastructure foundation

Depends on: Phase 0 approvals.

Planned work:

- Align the documented example environment and connection defaults with MariaDB, without changing the current environment in this phase.
- Define production cache/session/queue/mail/storage/logging settings as configuration decisions.
- Establish secure cookie, HTTPS, trusted-proxy, debug, locale, timezone, and error-handling requirements.
- Establish CI for tests, formatting, dependency audits, and frontend builds.
- Establish test conventions, including separate fast unit and MariaDB/Redis integration suites.
- Replace the starter route/page with a minimal Sekolah Kita shell when feature implementation is approved.
- Add reusable layouts/components, error pages, health/readiness behavior, and operational documentation.
- Define a reviewed deployment process that does not use destructive setup scripts.
- Decide how Redis will be made available in development/staging without pretending it is already configured.

Exit gate: a reproducible, non-production baseline with no known critical security or configuration drift.

### Phase 2 — Authentication and RBAC foundation

Depends on: Phase 1 secure foundation and approved role/privacy rules.

Planned work:

- Add approved role and account-status fields to the existing user model through new migrations only.
- Implement login/logout, password reset, email verification, password change, invitations, activation, suspension, and deactivation.
- Implement session regeneration, invalidation, secure cookies, rate limiting, and administrator provisioning.
- Add role middleware, Gates, Policies, Form Request authorization, and ownership/query scopes.
- Add audit events for identity and account changes.
- Add authentication, authorization, and negative-access tests.

Exit gate: all three roles and denied access paths are proven by tests before any dashboard business data is exposed.

### Phase 3 — School, people, and academic context

Depends on: Phase 2 identity/RBAC and approved privacy rules.

Planned work:

- Add singleton school profile.
- Add Guru, Siswa, guardian, and relationship models.
- Add the minimum academic context needed for class scope and enrollment.
- Implement role-scoped dashboards and safe read models.
- Add factories, environment-aware seeders, policy tests, and privacy tests.
- Establish consent/approval fields before publishing identifiable people content.

Exit gate: a Guru can access only assigned records, a Siswa only their own, and an administrator's sensitive access is auditable.

### Phase 4 — Public content, media, and contact

Depends on: Phase 3 school/people data, media decisions, and content permissions.

Planned work:

- Add media abstraction, public/private disk separation, upload validation, and queued derivatives.
- Add Berita and Prestasi workflows with draft/publish/archive states.
- Add Galeri albums/items and approved teacher directory.
- Add Contact submission, anti-spam controls, moderation, and notifications.
- Add public cache invalidation, SEO metadata, sitemap, and Indonesian presentation rules.

Exit gate: public pages expose only approved fields, uploads pass security tests, and private media never resolves from the public disk.

### Phase 5 — Platform services: Redis, cache, notifications, and audit

Depends on: Phases 1–4 event and data contracts; must be available at minimum level before PPDB production use.

Planned work:

- Provision and test Redis sessions, cache, rate limits, and queues in staging.
- Establish cache versioning/invalidation and public read projections.
- Add notification center, preferences, queued email, and failure monitoring.
- Implement append-only audit service, redaction, sensitive-read tracking, and retention controls.
- Add scheduled maintenance, cleanup, and cache-warming jobs.
- Configure supervised workers, queue monitoring, and runbooks.

Exit gate: Redis failure, queue retry, audit-write failure, and notification failure behavior are tested and documented.

### Phase 6 — PPDB

Depends on: Phases 2–5, especially identity, people, private media, audit, notifications, and rate limiting.

Planned work:

- Add admission periods and public information.
- Add applicant and guardian data model with consent/version tracking.
- Add private document upload, validation, scanning, and access auditing.
- Add application validation, anti-spam, duplicate detection, and submission throttling.
- Add review workflow, status history, reviewer authorization, and applicant notifications.
- Add transactional acceptance-to-student conversion and enrollment creation.
- Add retention/deletion rules for rejected applications and documents.

Exit gate: PPDB privacy, state-transition, concurrency, document-access, rate-limit, and conversion tests pass.

### Phase 7 — Security hardening, observability, and performance validation

Depends on: feature scope sufficient to represent production-like behavior.

Planned work:

- Run code, dependency, secret, and container/security scans.
- Review Nginx, PHP-FPM, headers, cookies, TLS, permissions, and trusted proxies.
- Profile MariaDB queries and indexes; remove N+1 and unbounded work.
- Test Redis, queues, object storage, backups, and restore procedures.
- Run realistic load tests using the Phase 0 workload definition.
- Tune PHP-FPM, cache, media delivery, and database settings from measurements.
- Complete incident response, rollback, and operational ownership runbooks.

Exit gate: agreed SLOs and security evidence pass on the proposed production topology, or limitations are explicitly accepted by the responsible owner.

### Phase 8 — Production deployment and stabilization

Depends on: Phase 7 exit gate and approved backup/retention policy.

Planned work:

- Provision isolated production database, Redis, mail, storage, and secrets.
- Deploy Nginx/PHP-FPM, workers, scheduler, monitoring, and HTTPS.
- Run reviewed migrations only, with backup and rollback readiness.
- Perform smoke tests for public, authentication, role, PPDB, private media, queue, and audit paths.
- Monitor errors, latency, queue age, storage, database, and security events during stabilization.
- Document release, incident, restore, credential-rotation, and data-retention procedures.

Exit gate: production go/no-go review confirms security, privacy, performance, backup restoration, monitoring, and ownership requirements.

### 17.1 Testing strategy across phases

Testing is a cross-cutting dependency, not a final phase:

- **Unit tests:** validation, policy decisions, state transitions, redaction, and pure domain rules.
- **Feature tests:** routes, middleware, Form Requests, database transactions, notifications, and queue dispatch.
- **Authorization tests:** every role, ownership boundary, assigned-class scope, administrator-only operation, and denied request.
- **MariaDB integration tests:** foreign keys, indexes, transactions, uniqueness, and migration behavior.
- **Redis integration tests:** sessions, cache invalidation, rate limits, queue retries, and connection failure behavior.
- **Browser/security tests:** CSRF, XSS, upload validation, private URL expiry, accessibility, and responsive behavior.
- **PPDB workflow tests:** duplicate submissions, status history, reviewer scope, acceptance transaction, and notification/audit side effects.
- **Load tests:** defined traffic mix, SLOs, failure injection, and repeatability.
- **Restore tests:** encrypted backup restoration and media/database consistency.

---

## 18. ARCHITECTURAL RISKS

The following decisions are expensive to change after real data or user workflows exist. They should be resolved or explicitly time-boxed during Phase 0.

| Risk | Why postponement is expensive | Mitigation / decision deadline |
|---|---|---|
| Unresolved single-school versus multi-school scope | Retrofitting `school_id`, tenant policies, composite constraints, cache keys, jobs, and media paths touches nearly every module. | Confirm before the first domain migration; create a new ADR if the product changes. |
| Identity and profile coupling | Storing roles, passwords, and personal records in one table makes privacy, account lifecycle, and future roles difficult to change. | Approve separate `users` and people profiles before schema work. |
| Role and assignment model | A role column without assignment scope can expose all students or make Guru access impossible to retrofit safely. | Approve fixed roles, class/teaching assignment rules, and policy matrix before dashboards. |
| Public/private data rules | Accidental public student/PPDB exposure is a privacy incident and may require notification, remediation, and trust repair. | Approve field classification and publication consent before any public people/media feature. |
| PPDB workflow and status model | Rewriting acceptance, review history, notifications, and conversion after applicants exist corrupts operational history. | Define states, transitions, reviewers, documents, quotas, and conversion before PPDB migrations. |
| Guardian identity/account scope | Adding accounts and relationships after intake is designed can change PII collection and authentication architecture. | Decide guardian accounts before PPDB implementation; default to records without login. |
| Academic structure | Class, enrollment, year, subject, and assignment relationships influence Guru scope and future grades/attendance. | Confirm minimum academic context and defer optional academic modules explicitly. |
| Media visibility and retention | Moving public/private files after real uploads is disruptive and may expose data through old paths or URLs. | Approve disks, MIME rules, signed access, deletion, and retention before uploads. |
| Notification channels and preferences | Email/on-site delivery and PII in payloads affect queues, templates, retention, and privacy. | Confirm channels and preference defaults before notification implementation. |
| Session/cache/queue topology | Changing drivers after production data affects availability, serialization, scaling, and failure behavior. | Define Redis purpose, isolation, fallback, and worker operations in Phases 1 and 5. |
| Undefined capacity target | “1,000 users” can lead to unjustified architecture or an unsafe capacity promise. | Adopt the measurable workload/SLO definition and test on staging. |
| API/mobile ambiguity | An accidental public API creates a new authentication, versioning, CORS, rate-limit, and support surface. | Confirm web-only scope for the initial release; require a new decision for API/mobile. |
| Test/production database divergence | SQLite tests can pass while MariaDB foreign keys, indexes, transactions, or Redis behavior fail. | Add MariaDB/Redis integration environments before feature release. |
| Unsafe setup/deployment scripts | Generic scripts that run migrations or create SQLite can damage or misconfigure a real database. | Replace with reviewed deployment/runbooks; never use destructive commands in production. |
| Secrets and default credentials | A fixed seeder or committed environment value can become a production compromise. | Use controlled provisioning, environment secret injection, rotation, and production scans. |
| Backup/restore and retention gaps | A backup that has never been restored is not a recovery plan; deleting data without a policy creates legal and operational risk. | Define RPO/RTO, retention, restore test, and incident ownership before real PII. |

---

## 19. APPROVAL CHECKLIST BEFORE IMPLEMENTATION

The following items are the minimum decisions to confirm before the related migrations or features begin:

- [ ] Single-school architecture is confirmed; no `school_id` tenancy is introduced accidentally.
- [ ] Modular monolith and web-first/Blade direction are confirmed.
- [ ] Role codes and role/resource matrix are approved.
- [ ] Guru assignment scope and Siswa self-access rules are approved.
- [ ] Guardian accounts are explicitly out of scope or have a separate requirements document.
- [ ] Academic scope is limited to directory/class context, or grades/attendance/schedules are separately specified.
- [ ] PPDB periods, quotas, required documents, statuses, transitions, reviewers, and acceptance conversion are defined.
- [ ] Public student-data and identifiable achievement/gallery publication rules are approved.
- [ ] PII, sensitive-document, media, export, log, backup, and retention classifications are approved.
- [ ] Notification channels, sender identity, preferences, and failure behavior are defined.
- [ ] Locale, timezone, date, and number formats are confirmed for Indonesian use.
- [ ] Redis cache/session/queue/rate-limit topology and failure behavior are defined.
- [ ] Performance workload, SLOs, load-test data, and pass criteria are approved.
- [ ] Backup RPO/RTO, restore testing, and production operations ownership are defined.
- [ ] API/mobile scope is explicitly web-only for the first release.
- [ ] Administrator MFA and production security-header policy are resolved before launch.

Until the relevant boxes are resolved, the feature should remain a planning item rather than an implicit assumption in code or migrations.
