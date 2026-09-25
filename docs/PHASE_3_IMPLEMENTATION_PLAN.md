# Sekolah Kita — Phase 3 Implementation Plan

**Date:** 2026-09-25
**Project:** Sekolah Kita
**Document status:** Planning only; this document does not authorize implementation, migrations, schema changes, or data changes.
**Scope:** Identity + Academic Core (people, academic context, scoped authorization, privacy, and tests)

> This plan translates the current repository and the Phase 0/2 decision documents into a gated implementation sequence. It is not an approval record. The proposed decisions in `docs/PHASE_2_OWNER_APPROVAL_DRAFT.md` remain proposed until the responsible owners explicitly confirm them. No application source, migration, configuration, dependency, environment file, or database was changed to produce this document.

## 1. Executive summary

The repository currently contains a secure Laravel authentication foundation and no Sekolah Kita domain schema. The next implementation should add only the minimum identity and academic context needed to establish trusted people records, class placement history, and assignment-scoped authorization.

The recommended design is:

- keep `users` as the authentication/account aggregate and preserve the Phase 1 role, status, session, and lifecycle behavior;
- create separate `teachers`, `students`, and `guardians` person records, with a nullable, unique, one-to-one account link only where approved;
- represent academic years, ordered terms, year-specific classes, effective-dated assignments, and historical enrollments as MariaDB source-of-truth records;
- derive a student's current placement from an applicable enrollment rather than storing a mutable `current_class_id` on the student;
- use native Laravel Policies, Form Requests, route authorization, and query-level scopes, with default-deny behavior;
- enforce Guru access through current/effective homeroom or teaching assignments only, never through the role alone;
- give Siswa access only to the profile and enrollment records linked to the authenticated user;
- keep guardian records person-only and out of authentication;
- exclude public student projections, identifiable public achievements, media, PPDB, deferred academic modules, multi-tenancy, and custom RBAC from this increment;
- verify every schema and concurrency decision against the actual MariaDB server before committing a migration.

The migration gate is not yet open. At minimum, owner decisions O2-01 (identifiers and minimum identity fields), O2-02 (academic calendar and grade levels), O2-04 (enrollment lifecycle), O2-05 (Guru scope and minimum fields), O2-13 (retention/audit lifecycle), and O2-17 (data-entry, date, and identifier conventions) must be resolved for the affected schema. The plan deliberately leaves school-specific values and unresolved alternatives explicit rather than inventing them.

## 2. Phase 3 scope and explicit non-scope

### 2.1 In scope

1. **Academic core**
   - academic years and their lifecycle;
   - ordered terms/semesters within an academic year;
   - owner-approved grade levels represented without a speculative catalog unless separately approved;
   - year-specific classes/rombels;
   - optional subject catalog only if subject teaching assignments are approved;
   - separate effective-dated homeroom and teaching-assignment relationships;
   - student enrollment/class-placement history, effective dates, lifecycle states, capacity checks, and rollover/transfer behavior;
   - MariaDB foreign keys, indexes, uniqueness constraints, and concurrency invariants.

2. **People**
   - Guru profile and employment lifecycle;
   - Siswa profile and student lifecycle;
   - guardian/Wali records and effective student-guardian relationships;
   - optional account links for Guru and Siswa only;
   - school employee/student identifiers after the owner approves their required/conditional status, format, uniqueness, correction, and masking rules;
   - minimum-necessary fields and explicit private projections.

3. **Authorization**
   - administrator access to approved academic/people resources;
   - current/effective assignment-scoped Guru access;
   - exact self-access for Siswa;
   - Policies, Gates where useful, query scopes, Form Request authorization, and coarse route middleware only where appropriate;
   - negative-access tests for direct IDs, lists, relationship access, exports, counts, and search paths.

4. **Security and privacy**
   - field-level classification and projections;
   - mass-assignment protection;
   - server-side authorization and no relationship-based implicit access;
   - redaction of identifiers from URLs, logs, validation responses, cache keys, and public output;
   - lifecycle/archive behavior that preserves history;
   - hooks for the audit requirements already established by the architecture, without creating a speculative audit table in this increment.

5. **Testing**
   - unit, feature, authorization, relationship, privacy, negative-access, migration/schema, and MariaDB integration tests;
   - concurrency tests for active enrollment, homeroom assignment, and class capacity;
   - preservation tests proving the existing users/authentication data is not rewritten.

### 2.2 Explicit non-scope

The following are not authorized by this plan and must not be added as placeholders:

- PPDB applicants, admission periods, application status, documents, quotas, review, conversion, or applicant access;
- guardian login, guardian role, guardian portal, or any guardian authentication field;
- public student directory, student search, public student profile, student face gallery, or identifiable public student achievement;
- Berita, Prestasi, Galeri, Contact, school public pages, or any public-content workflow;
- media tables, photo references, public/private storage workflow, document scanning, or publication consent fields that are not required for the core people scope;
- grades, gradebooks, assessment, report cards, attendance, schedules, timetables, extracurricular activities, finance, billing, payroll, or other deferred academic modules;
- subject, program, major, strand, gradebook, or class-successor tables unless an owner decision proves a real requirement;
- public API, mobile application, applicant-facing application API, or a second data surface;
- multi-tenancy, `school_id` columns, tenant filters, or tenant-aware cache keys;
- custom roles, normalized permission tables, permission packages, delegated administration, or field-level permission metadata;
- Redis as a source of academic, student, guardian, assignment, or enrollment truth;
- school profile implementation. Although the Phase 2 blueprint places `school_profiles` in a proposed people increment, it is outside the scope named for this task. It requires a separate scope confirmation rather than a silent migration.
- destructive database operations, editing existing migrations, fixed production seed accounts, real personal data, or new Composer/NPM dependencies.

A minimal authenticated internal interface may be added only if it is necessary to exercise approved domain behavior. No public student-facing route or public projection is part of this increment.

## 3. Current repository baseline

The following baseline was inspected read-only before planning.

| Area | Observed baseline | Planning consequence |
|---|---|---|
| Git | HEAD `0fb4a4b` (`docs: finalize phase 2 domain approval draft`); working tree was clean before this document was created | Preserve history; create only this plan; do not commit or stage it in this task. |
| Runtime | Laravel Framework `13.33.0`, PHP `8.5`, Composer `2.10.3`, PHPUnit `12.5.35` | Use Laravel 13 conventions and confirm APIs against the installed version. |
| Direct Composer packages | `laravel/framework`, `laravel/tinker`, Faker, Boost, Pint, PHPUnit, Collision, Pail, Pao, Mockery | Use native Laravel facilities; do not add a domain/RBAC/media package. |
| Database | Current default connection is named `mysql` but the server reports MariaDB `12.3.3`; database is `sekolah_kita` | MariaDB is the source of truth; do not change the connection or `.env` as part of this plan. Test the actual server explicitly. |
| Existing schema | Nine framework tables: `users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, and `migrations`; all are InnoDB/`utf8mb4_unicode_ci` | Preserve every existing table and migration. New domain tables must be additive. |
| Migration status | Three framework migrations are in batch 1; `2026_09_24_115047_add_role_and_status_to_users_table.php` is in batch 2 | The live status is authoritative even though the Phase 1 document records an earlier pending snapshot. |
| Existing users | Live read-only count is zero | The plan must still preserve future/existing users; no automatic profile backfill is implied. |
| Domain code | No Guru, Siswa, guardian, academic, subject, assignment, enrollment, PPDB, media, or content models/migrations/policies | The proposed model is a future implementation, not current behavior. |
| Identity | `User` has fixed role/status enums, restricted fillable/hidden attributes, hashed password cast, and `MustVerifyEmail`; `UserPolicy`, role/active middleware, account lifecycle/session invalidation, and authentication routes exist | Extend relationships and policies without weakening Phase 1 behavior. |
| Tests | PHPUnit 12 with in-memory SQLite, array cache/session/mail, and synchronous queue in `phpunit.xml`; existing feature tests cover authentication, account lifecycle, and authorization | Fast tests may remain isolated, but schema, foreign-key, index, transaction, and concurrency behavior must also be proven on MariaDB. |
| Configuration | Current local configuration is UTC/`en`, database-backed sessions/cache/queue, log mail, local debug, and a Redis configuration surface that is not currently used by domain code | Do not silently change environment, locale, session, cache, queue, or secret settings in this increment. |
| Documentation | Phase 0 architecture is approved at the architecture level; Phase 2 domain decisions/blueprint/owner draft are proposed; Phase 1 authentication foundation is complete | Treat unresolved O2/B items as gates, not defaults. |

The repository also contains a `composer.json` setup script that can run migrations and a post-create-project script that creates SQLite. Neither is an approved deployment procedure and neither may be used to initialize or repair the existing database.

## 4. Relevant existing Phase 1 architecture

The following Phase 1 behavior is a compatibility contract:

- `users.email` remains the normalized, globally unique login address.
- `users.password` remains a credential field handled by the `hashed` cast; it must never be copied to a person profile.
- `users.role` remains nullable until controlled provisioning assigns exactly one of `administrator`, `guru`, or `siswa`.
- `users.status` remains `active`, `inactive`, or `suspended`; no new account-status value is introduced here.
- `users.email_verified_at` remains the verification fact; invitation and MFA state are not added by this plan.
- `User::$fillable` is intentionally limited to `name`, `email`, and `password`; `password` and `remember_token` remain hidden.
- The `active` middleware blocks missing-role, inactive, and suspended accounts from protected areas.
- The `role` middleware is a coarse route-group guard, not a resource policy.
- `UserPolicy` permits administrators to manage other accounts, permits users to view/update their own account, and denies hard deletion and non-administrator lifecycle actions.
- `AccountLifecycleService` performs role/status transitions under a row lock, invalidates sessions/remember tokens, and records a redacted security event. Phase 3 must reuse or compose this boundary rather than create a second account-status workflow.
- The existing `UserSessionInvalidator` and `auth.session` middleware remain the session invalidation mechanism.
- Existing authentication controllers, Form Requests, rate limiters, verification routes, and password-reset behavior remain unchanged by this plan.
- Existing protected routes continue to use `auth`, `auth.session`, `active`, `verified`, and fixed-role middleware. Domain routes must add resource authorization on top.

The only expected identity-file extension is a narrow, explicit relationship to an optional Teacher or Student profile. Even that extension must not make `users` a person table, copy legal identity fields into it, allow a user to link to both profile types, or make a profile imply an account.

Administrator MFA, controlled invitation/provisioning, email-change verification, and a formal account-erasure process remain separate identity/security decisions. They are not silently added to the people migration.

## 5. Relevant Phase 2 decisions

### 5.1 Inherited constraints

The following Phase 0 decisions remain non-negotiable:

- one school only, with no `school_id` or tenant scope;
- modular Laravel monolith and web-first Blade application;
- fixed internal roles only: `administrator`, `guru`, and `siswa`;
- native Laravel authorization using middleware, Form Requests, Policies/Gates, ownership, and query scoping;
- MariaDB/InnoDB as business truth and Redis as disposable infrastructure;
- no public student directory or identifiable public student achievement;
- no guardian account in the first release;
- private personal data and documents are never placed on a public projection or public disk;
- existing migrations and data are preserved; future schema changes are additive;
- destructive commands and hard deletion are not normal workflows;
- public URLs, cache keys, logs, and ordinary validation responses do not contain sensitive identifiers.

### 5.2 Phase 2 proposals and Phase 3 impact

| Phase 2 item | Proposed direction | Status for this plan | Phase 3 consequence |
|---|---|---|---|
| O2-01 | Unique employee/student identifiers; collect NIK/NISN only when required | Proposed | Blocks field nullability, uniqueness, correction, encryption/digest, and privacy rules. |
| O2-02 | One year with one or more ordered terms; owner-approved numeric grade levels; no program entity without a real requirement | Proposed | Blocks calendar, term count, class key, rollover, and assignment scope. |
| O2-04 | One active enrollment per student per academic year; historical rows; explicit transfer/rollover states | Proposed | Blocks enrollment statuses, unique-current strategy, and effective-date rules. |
| O2-05 | Current homeroom and, if approved, current teaching assignments; minimum necessary student fields; no historical access by default | Proposed | Blocks the Guru scope query, optional subject table, and field allowlist. |
| O2-10/O2-11 | Public Guru/achievement publication remains a later, conditional decision | Proposed and out of this increment | No public people directory, photo fields, or achievement recipient schema. |
| O2-13 | Retain academic/audit history; lifecycle/archive/hold-aware deletion; redacted audit | Proposed | Blocks exact retention, audit event contract, and whether a separate enrollment transition history is necessary. |
| O2-14 | Existing workload hypothesis is not an approved SLO | Proposed | Indexes must be query-driven and verified; no capacity claim is made. |
| O2-15 | Redis is later infrastructure for safe workloads | Proposed | Do not make Redis a domain dependency or add domain cache keys. |
| O2-16 | Administrator MFA is a later production requirement | Proposed | Do not add MFA columns/tables or alter Phase 1 identity schema in this plan. |
| O2-17 | Indonesian conventions, UTC timestamps, `Asia/Jakarta` presentation, normalized identifiers | Proposed | Blocks exact lengths, date boundaries, normalization, and validation. |
| O2-18 | Encrypted/tested database and private-media restore | Proposed | Backup/restore is a release gate; no production recovery command is part of this plan. |

The owner draft is a proposal matrix, not approval. Approval of one item does not automatically resolve dependent items. If a decision is delayed, the corresponding table or invariant must be deferred rather than represented by nullable “maybe” columns.

## 6. Proposed implementation sequence

The implementation sequence is dependency-aware and gated:

1. **Decision gate and baseline capture**
   - obtain explicit owner decisions for identifiers, calendar, enrollment, assignment scope, field privacy, retention/audit, and data-entry conventions;
   - record the decision references for every planned table and migration;
   - recheck `git status`, migration status, live schema, and existing authentication tests;
   - do not change `.env`, Composer, or existing migrations.

2. **People foundation**
   - add the approved Teacher, Student, Guardian, and StudentGuardian tables and models;
   - add factories with synthetic data and explicit lifecycle states;
   - add account-link invariants and profile lifecycle services;
   - add privacy-safe model serialization/projections and People policies.

3. **Academic catalog**
   - add academic years and terms;
   - add year-specific classes and approved grade-level validation;
   - add factories and lifecycle services;
   - enforce calendar containment, year uniqueness, and rollover rules through services and tests.

4. **Assignment scope**
   - if approved, add subjects;
   - add homeroom assignments;
   - if approved, add subject teaching assignments;
   - add effective-date and one-active invariants;
   - add the Guru scope query service and policy tests before exposing any student list/detail.

5. **Enrollment history**
   - add enrollment records and the approved current-placement uniqueness mechanism;
   - implement transfer, rollover, leave, completion, withdrawal, and correction actions transactionally;
   - add capacity and concurrency tests before enabling writes.

6. **HTTP and read surfaces**
   - add only the internal endpoints necessary for approved people/academic operations;
   - keep controllers thin and use Form Requests, Policies, and scoped services;
   - do not add a public student route, public directory, or identifiable content route.

7. **Verification and review**
   - run fast tests, MariaDB integration tests, privacy/negative tests, route checks, schema checks, and static formatting;
   - review query plans and logs for identifier leakage;
   - obtain owner sign-off before applying migrations to any shared environment.

Later modules must call the owning people/academic services. They must not insert or update profile, assignment, or enrollment rows directly from a future PPDB controller.

## 7. Domain model overview

### 7.1 Aggregate boundaries

- **Identity aggregate:** `User` owns login, role, account status, verification, sessions, and credential data. It does not own legal person facts or academic placement.
- **People aggregates:** `Teacher`, `Student`, and `Guardian` own their domain lifecycle and approved profile fields. A person may exist before an account; a guardian never receives an account.
- **Academic aggregates:** `AcademicYear`, `Term`, and `Class` own calendar and class structure. `Subject` is conditional on the approved teaching-assignment scope.
- **Relationship/history aggregates:** `ClassTeacherAssignment`, `TeachingAssignment`, `StudentGuardian`, and `Enrollment` own effective relationships and preserve their history. They do not overwrite a previous relationship when scope changes.
- **Authorization context:** the authenticated `User` is resolved to exactly one profile appropriate to its fixed role. The profile is then used to evaluate current assignments or exact ownership.

### 7.2 Relationship map

```text
users 0..1 ---------------------- 1 teachers
users 0..1 ---------------------- 1 students
students * ---------------------- * guardians
              through student_guardians

academic_years 1 ---------------- * terms
academic_years 1 ---------------- * classes
teachers 1 --------------------- * class_teacher_assignments * ---- 1 classes
teachers 1 --------------------- * teaching_assignments * --------- 1 classes
classes * ----------------------- * subjects through teaching_assignments
students 1 --------------------- * enrollments * ------------------- 1 classes
academic_years 1 ---------------- * enrollments
```

`academic_year_id` on an assignment or enrollment is retained only if the implementation uses a composite integrity relationship with the class. It must never be allowed to disagree with the class's year. If the implementation chooses to derive the year through `class_id`, that simplification must be recorded in the schema decision before the migration; it must not be an accidental omission.

No generic `people`, `persons`, `profile_type/profile_id`, or unconstrained polymorphic ownership table is proposed. Separate profiles make privacy classification, account lifecycle, and authorization easier to audit.

### 7.3 Core invariants

- all records belong to the one school; no `school_id` exists;
- a Guru/Siswa account has at most one matching profile, and a user cannot link to both profile types;
- a profile can exist without a user;
- a class belongs to exactly one academic year and is not reused across years;
- a student has no mutable current-class pointer; current placement is derived;
- assignment and enrollment effective dates are bounded by their approved academic period;
- current authorization is time/effective-date sensitive;
- personal/history records are not removed by ordinary lifecycle changes;
- missing profile, missing assignment, missing approval, or ambiguous scope fails closed.

## 8. Detailed table-by-table schema plan

The following is a logical schema plan, not migration code. “Conditional” means the field/table must not be created until the referenced owner decision is recorded. Exact identifier lengths, grade-level representation, capacity, and relationship vocabulary are not silently fixed here.

### 8.1 `teachers` — Guru person/profile

| Attribute group | Planned columns and types | Rules/classification |
|---|---|---|
| Identity key | `id` BIGINT UNSIGNED primary key | Opaque internal key; never a public identifier. |
| Account link | `user_id` BIGINT UNSIGNED nullable, unique, explicit FK to `users.id` | At most one profile per user. A profile may have no account. Recommended delete behavior is restrict because Phase 1 denies hard user deletion. |
| Legal name | `legal_name` VARCHAR with owner-approved length, required | Confidential person data; not copied from or used to replace `users.name`. |
| School identifier | `employee_number` VARCHAR with owner-approved format/length, required only if O2-01 confirms it is mandatory; unique if issued | Sensitive business identifier; immutable after issue; correction is a controlled, audited action. No public route or cache key. |
| Employment lifecycle | `employment_status` VARCHAR/enum-backed, required; proposed values `active`, `leave`, `inactive`, `retired` | Independent from `users.status`; exact vocabulary and transition rules require owner approval. |
| Employment dates | `employment_start_date` DATE and optional `employment_end_date` DATE | Requiredness follows O2-01; end date must be consistent with retirement/inactive transitions. |
| Conditional profile fields | `preferred_name`, business contact, approved identity/contact fields, position/professional summary only when required and classified | Add in a separately reviewed additive migration if approved; do not collect fields merely because the Phase 2 blueprint lists them. |
| Metadata | `created_at`, `updated_at` TIMESTAMP | Mutable profile record; no routine hard delete. |
| Excluded | password, reset token, role, account status, session data, student identifiers, public-directory slug, photo/media reference | These do not belong in the Guru profile for this increment. |

The source record is confidential even if a future, separately approved public projection exists. A public projection must query an explicit allowlist and must not serialize the full Teacher model.

### 8.2 `students` — Siswa person/profile

| Attribute group | Planned columns and types | Rules/classification |
|---|---|---|
| Identity key | `id` BIGINT UNSIGNED primary key | Opaque internal key. |
| Account link | `user_id` BIGINT UNSIGNED nullable, unique, explicit FK to `users.id` | A profile may be created before account provisioning. An activated `siswa` account must link to one Student. A user cannot link to both Student and Teacher. |
| Legal name | `legal_name` VARCHAR with owner-approved length, required | Confidential; no public projection. |
| School identifier | `student_number` VARCHAR with owner-approved format/length, required if O2-01 confirms; unique | Immutable after allocation; not a URL, search key, login credential, public field, or recyclable ordinary identifier. |
| Student lifecycle | `status` VARCHAR/enum-backed, required; proposed values `active`, `on_leave`, `graduated`, `transferred`, `withdrawn`, `archived` | Independent from account status. Exact values and transitions require O2-04/owner confirmation. |
| Enrollment facts | `admission_date` DATE, optional `graduation_date`, `leaving_date`, and confidential `leaving_reason` | Do not put current class in this table. `leaving_reason` must not be returned in broad projections. |
| Conditional identity/contact | birth data, preferred name, contact fields, official identifier such as NISN/NIK only when legally/operationally required | Privacy-reviewed, minimized, corrected through a controlled workflow; no raw identifier in logs or public output. |
| Metadata | `created_at`, `updated_at` TIMESTAMP | Lifecycle/archive instead of routine hard deletion. |
| Excluded | password, role, account status, guardian contact, public directory fields, photo/media reference, current class pointer | Guardian and placement relationships are explicit foreign-key relations, not copied fields. |

No public Student projection, student search endpoint, public student number, or identifiable achievement is permitted. A future approved projection would be a separate design and privacy decision.

### 8.3 `guardians` — Wali person record

| Attribute group | Planned columns and types | Rules/classification |
|---|---|---|
| Identity key | `id` BIGINT UNSIGNED primary key | Person/domain key, never a login identity. |
| Name/contact | `legal_name` required; phone, email, address, and national identity data only when approved and necessary | Highly confidential. No global uniqueness is imposed on shared phone/email; duplicate resolution is an explicit workflow. |
| Lifecycle | `status` VARCHAR/enum-backed, required; proposed values `active`, `inactive`, `archived` | Independent from User status; end relationships before archiving. |
| Metadata | `created_at`, `updated_at` TIMESTAMP | Preserve relationship history; no routine hard delete. |
| Excluded | `user_id`, password, role, login/session fields, public profile fields, student number | Guardian is never an internal authenticated role in this release. |

### 8.4 `student_guardians` — relationship and authority history

| Attribute | Planned type | Rules |
|---|---|---|
| `id` | BIGINT UNSIGNED primary key | Internal relationship key. |
| `student_id` | BIGINT UNSIGNED, required FK to `students.id` | Restrict deletion; end relationship explicitly. |
| `guardian_id` | BIGINT UNSIGNED, required FK to `guardians.id` | Restrict deletion; archive guardian only after relationship history is preserved. |
| `relationship_type` | VARCHAR/enum-backed, required | Owner-approved vocabulary such as father/mother/grandparent/sibling/other; it is not an authorization role. |
| `is_primary_contact` | BOOLEAN, required or owner-approved default | Primary and emergency flags are distinct. |
| `is_emergency_contact` | BOOLEAN, required or owner-approved default | A lower-priority emergency contact is not automatically primary. |
| `priority_order` | bounded unsigned integer, conditional/nullable | Used only if the school needs deterministic priority; validate uniqueness/tie-breaks within the active set. |
| `starts_on` | DATE, required | Effective start of the relationship/authority. |
| `ends_on` | DATE, nullable | Must not precede `starts_on`; ending preserves the row. |
| `relationship_note` | bounded TEXT, nullable, confidential | Never exposed by default; not a free-form public field. |
| `created_at`, `updated_at` | TIMESTAMP | No routine hard delete. |

Do not add a permanent unique `(student_id, guardian_id)` constraint that would prevent historical repeats. Duplicate active links, overlap, and the exact primary-contact invariant need an owner-approved MariaDB-safe strategy and service-level transaction tests.

### 8.5 `academic_years`

| Attribute | Planned type | Rules |
|---|---|---|
| `id` | BIGINT UNSIGNED primary key | Opaque internal key. |
| `code` | VARCHAR with approved length, required, unique | Stable business code; correction/rollover rules are controlled. |
| `label`/display name | VARCHAR, required if the owner approves a separate display label | If code alone is the approved label, do not add a redundant field merely for symmetry. |
| `starts_on`, `ends_on` | DATE, required | `starts_on` must be before/equal `ends_on`; date ranges must follow the approved school calendar and timezone convention. |
| `status` | VARCHAR/enum-backed, required; proposed `draft`, `active`, `closed` | At most one operational active year and no overlapping ranges are service/database-test invariants. |
| `description` | bounded TEXT, nullable, internal | Optional only if the owner requires it; not public by default. |
| `created_at`, `updated_at` | TIMESTAMP | Closed years remain readable and are not deleted for rollover. |

Date-range non-overlap and one-active-year enforcement are not assumed to come from a normal index. They require a transaction, row/advisory locking strategy, and MariaDB concurrency tests approved for the implementation.

### 8.6 `terms`

| Attribute | Planned type | Rules |
|---|---|---|
| `id` | BIGINT UNSIGNED primary key | Internal key. |
| `academic_year_id` | BIGINT UNSIGNED, required FK to `academic_years.id` | Restrict deletion; terms cannot outlive their year. |
| `sequence` | bounded unsigned integer, required | Ordered within a year; exact type follows O2-02/O2-17. |
| `label` | VARCHAR, required | Display term/semester label. |
| `starts_on`, `ends_on` | DATE, required | Must be contained by the parent academic year and ordered. |
| `status` | VARCHAR/enum-backed, required; proposed `draft`, `active`, `closed` | Exact active-term rule follows the approved calendar. |
| `created_at`, `updated_at` | TIMESTAMP | Historical terms are retained. |

Unique `(academic_year_id, sequence)` is required. A unique label within a year is used only if the owner confirms that rule. The model does not hard-code exactly two semesters.

### 8.7 `classes` — class/group/rombel

| Attribute | Planned type | Rules |
|---|---|---|
| `id` | BIGINT UNSIGNED primary key | Internal key; never a public student identifier. |
| `academic_year_id` | BIGINT UNSIGNED, required FK to `academic_years.id` | A class is year-specific and is never reused across years. |
| `grade_level` | owner-approved numeric or coded value, required | The exact representation and allowed values are O2-02 decisions. No speculative `grade_levels` table is created for the baseline. |
| `name`/section label | VARCHAR, required | Class naming and uniqueness follow O2-02. |
| `capacity` | bounded unsigned integer, nullable | Add only if the school requires capacity; do not create a capacity workflow speculatively. |
| `status` | VARCHAR/enum-backed, required; proposed `planned`, `active`, `closed` | Closing, merging, splitting, or renaming preserves old relationships and history. |
| `closed_at` | DATE/TIMESTAMP, nullable | Only if closure timing is required. |
| `closure_reason` | bounded internal TEXT, nullable | Add only if business-significant closure reasons are required; never public. |
| `created_at`, `updated_at` | TIMESTAMP | No routine hard delete. |

The candidate business key is `(academic_year_id, grade_level, name)`, subject to the approved naming rule. A technical unique key involving `id` and `academic_year_id` may be needed if assignment/enrollment foreign keys use composite year integrity; it is not a public identifier.

### 8.8 `subjects` — conditional teaching catalog

This table is included only if O2-02/O2-05 approve subject-based teaching assignments. If the owner approves homeroom-only Guru scope, omit this table and all subject assignment fields.

| Attribute | Planned type | Rules |
|---|---|---|
| `id` | BIGINT UNSIGNED primary key | Internal key. |
| `code` | VARCHAR, required, unique | Stable school-wide code unless the owner explicitly approves program/year scope. |
| `name` | VARCHAR, required | Display name; not a public student field. |
| `description` | bounded TEXT, nullable/internal | Add only if operationally required. |
| `status` | VARCHAR/enum-backed, required; proposed `active`, `inactive` | Inactive subjects retain historical assignments. |
| `created_at`, `updated_at` | TIMESTAMP | No routine hard delete. |

### 8.9 `class_teacher_assignments` — homeroom/wali kelas

Included only if O2-05 approves homeroom-based access.

| Attribute | Planned type | Rules |
|---|---|---|
| `id` | BIGINT UNSIGNED primary key | Internal key. |
| `class_id` | BIGINT UNSIGNED, required FK to `classes.id` | Restrict deletion; class year is derived from the class. |
| `teacher_id` | BIGINT UNSIGNED, required FK to `teachers.id` | Restrict deletion; historical assignments remain. |
| `status` | VARCHAR/enum-backed, required; proposed `planned`, `active`, `ended`, `cancelled` | Status and dates must agree. |
| `starts_on` | DATE, required | Effective start; must fall within the class academic year unless the owner approves another rule. |
| `ends_on` | DATE, nullable | Must not precede `starts_on`; a replacement ends the old row and creates a new row. |
| `internal_reason` | bounded internal TEXT, nullable | Add only if replacement/closure reasons are required. |
| `created_at`, `updated_at` | TIMESTAMP | No hard delete. |

At most one active homeroom assignment per class is a required invariant. MariaDB does not provide a portable partial unique constraint for this shape; the implementation must select and test an explicit locking/technical-key strategy rather than relying on a race-prone read-then-write.

### 8.10 `teaching_assignments` — subject/class assignment

Included only if O2-05 approves subject teaching as an access source.

| Attribute | Planned type | Rules |
|---|---|---|
| `id` | BIGINT UNSIGNED primary key | Internal key. |
| `teacher_id` | BIGINT UNSIGNED, required FK to `teachers.id` | Restrict deletion. |
| `class_id` | BIGINT UNSIGNED, required FK to `classes.id` | Restrict deletion. |
| `subject_id` | BIGINT UNSIGNED, required FK to `subjects.id` | Restrict deletion; inactive subjects remain historically valid. |
| `academic_year_id` | BIGINT UNSIGNED, required only if retained as an explicit scope key | If present, must be constrained to the class's year through a composite relationship or an equivalent tested invariant; never unchecked duplication. |
| `term_id` | BIGINT UNSIGNED, nullable, FK to `terms.id` only if O2-05 approves term-specific scope | Do not add a nullable term field when the school uses year-wide scope. |
| `status` | VARCHAR/enum-backed, required; proposed `planned`, `active`, `ended`, `cancelled` | Current access requires active/effective status and dates. |
| `starts_on`, `ends_on` | DATE, `starts_on` required, `ends_on` nullable | Must fit the class year and, if applicable, the approved term. |
| `internal_reason` | bounded internal TEXT, nullable | Replacement/reason metadata only if approved. |
| `created_at`, `updated_at` | TIMESTAMP | Preserve historical assignments. |

The exact duplicate/scope uniqueness rule depends on whether co-teaching and term scope are allowed. A nullable-term unique key must not be added blindly. Co-teaching, replacement, and one-assignment-per-subject rules require owner confirmation and MariaDB concurrency tests.

### 8.11 `enrollments` — historical class placement

| Attribute | Planned type | Rules |
|---|---|---|
| `id` | BIGINT UNSIGNED primary key | Internal placement/history key. |
| `student_id` | BIGINT UNSIGNED, required FK to `students.id` | Restrict deletion; history cannot disappear with account/profile changes. |
| `class_id` | BIGINT UNSIGNED, required FK to `classes.id` | Restrict deletion; class must match the selected year. |
| `academic_year_id` | BIGINT UNSIGNED, required FK to `academic_years.id` | Used for current-placement queries and the approved one-active-per-year invariant; enforce consistency with the class. |
| `status` | VARCHAR/enum-backed, required; proposed `pending`, `active`, `transferred`, `completed`, `withdrawn`, `on_leave` | Exact vocabulary and transitions require O2-04. Status is not a public field. |
| `enrolled_on` | DATE, required or conditionally required for pre-placement records | Must follow the approved intake/placement rule. |
| `effective_from` | DATE, required if the owner requires explicit effective placement dates | Default relationship to `enrolled_on` must be explicit in the service, not guessed by a controller. |
| `effective_to` | DATE, nullable | Must not precede the start; ending a placement preserves the row. |
| `completed_on` | DATE, nullable | Required only for the approved completion/graduation transition. |
| `transition_reason` | bounded confidential TEXT, nullable | Internal reason; not returned in broad projections. |
| `created_at`, `updated_at` | TIMESTAMP | No routine hard delete. |

No `current_class_id` is added to `students`. Historical rows are allowed to repeat `(student_id, academic_year_id)`; the active uniqueness mechanism is selected only after O2-04 and MariaDB testing. No PPDB source foreign key is included because PPDB is out of scope.

If the owner requires an immutable event for every same-row status transition, a separate `enrollment_status_histories` table must be explicitly approved. The current Phase 2 blueprint does not authorize that table; this plan uses preserved placement rows plus the architecture-required audit hook and flags the question for owner review.

### 8.12 Tables deliberately not created in this increment

`school_profiles`, media tables, public-content tables, PPDB tables, notification/audit tables, grade/catalog tables, and deferred academic tables are not created by this plan. A future table must have its own decision reference, migration group, privacy classification, and test plan.

## 9. Foreign keys and delete behavior

All foreign keys below are explicit and indexed. The default for personal/history relationships is `RESTRICT`; no domain relationship uses an unconditional cascade to simplify a migration.

| Parent → child | Delete behavior | Reason |
|---|---|---|
| `users` → `teachers.user_id` | `RESTRICT` by default; a later `NULL ON DELETE` requires a separately approved account-erasure process | Phase 1 denies hard user deletion and preserves identity history. |
| `users` → `students.user_id` | `RESTRICT` by default | A student/account link is not erased by ordinary account lifecycle changes. |
| `academic_years` → `terms` | `RESTRICT` | A term cannot be detached from its historical year. |
| `academic_years` → `classes` | `RESTRICT` | Classes and their rosters are historical facts. |
| `academic_years` → scoped assignments/enrollments | `RESTRICT` | Rollover creates new records; it does not delete old-year relationships. |
| `terms` → `teaching_assignments` | `RESTRICT` when the optional term link is approved | Historical term scope must remain explainable. |
| `classes` → `class_teacher_assignments` | `RESTRICT` | Closing a class does not erase assignment history. |
| `classes` → `teaching_assignments` | `RESTRICT` | Same. |
| `classes` → `enrollments` | `RESTRICT` | Student placement history must not disappear. |
| `teachers` → assignments | `RESTRICT` | End-date assignments before archival; do not delete the teacher. |
| `subjects` → teaching assignments | `RESTRICT` | Inactive subjects retain historical references. |
| `students` → `enrollments` | `RESTRICT` | Deactivation/archive does not remove academic history. |
| `students` → `student_guardians` | `RESTRICT` | End-date the relationship before lifecycle changes. |
| `guardians` → `student_guardians` | `RESTRICT` | Preserve relationship and authority history. |

Existing framework foreign keys and indexes, including the current `sessions.user_id` index without a foreign-key constraint, are not altered in this increment. Any future change to delete behavior must be an additive, reviewed migration with a recovery plan.

## 10. Unique constraints

The following are the intended business/integrity rules. A proposed rule is not implemented until its owner decision is recorded.

| Table/relationship | Constraint or invariant | Notes |
|---|---|---|
| `teachers.user_id` | Unique non-null | One Guru profile per account. |
| `teachers.employee_number` | Unique when issued | Required/format/recycle/correction rules come from O2-01. Never expose it publicly. |
| `students.user_id` | Unique non-null | One Siswa profile per account. |
| `students.student_number` | Unique when issued | Permanent business identifier; no ordinary recycling. |
| Official student/teacher identifier | Only unique if O2-01 explicitly approves it | Sensitive exact-match design may require encryption/tokenization or a keyed digest; do not add a plaintext global index by default. |
| `guardians` contact fields | No global unique email/phone/name | Shared family contacts are legitimate; duplicate resolution is explicit and audited. |
| `student_guardians` | No blanket permanent pair uniqueness | Historical end-dated relationships may repeat; active overlap/primary rules need a tested strategy. |
| `academic_years.code` | Unique | Stable calendar key; exact label rule is separate. |
| Academic-year dates | Non-overlapping ranges and at most one active operational year | Enforced by service transaction/locks and MariaDB tests, not a naive status default. |
| `terms` | Unique `(academic_year_id, sequence)` | Optional unique label only if approved. |
| `classes` | Unique `(academic_year_id, grade_level, name)` after naming approval | Classes are year-specific. |
| `subjects.code` | Unique if subjects are approved | Global school scope only if O2-02 confirms it. |
| `class_teacher_assignments` | At most one active homeroom per class | MariaDB-safe service/locking or technical-key strategy required; historical rows may repeat. |
| `teaching_assignments` | No duplicate active logical assignment for the approved teacher/class/subject/period | Co-teaching and term scope must be decided before a unique key is chosen. |
| `enrollments` | At most one active enrollment per `(student_id, academic_year_id)` if O2-04 confirms it | Historical rows may repeat; MariaDB enforcement mechanism is an explicit implementation decision. |
| `enrollments` | Class/year consistency | Enforced through the class relationship or a tested composite foreign-key/invariant strategy. |

A unique constraint is not permission to hard-delete and reissue an identifier. Lifecycle and correction services must prevent recycling where historical meaning could be lost.

## 11. Index strategy

Indexes are selected from named query paths. Foreign-key columns are indexed by their constraints; redundant single-column indexes are not added automatically.

### 11.1 Candidate indexes for this increment

| Query path | Candidate index | Reason/caveat |
|---|---|---|
| Guru account lookup | Unique `teachers(user_id)` | One-to-one profile link. |
| Siswa account lookup | Unique `students(user_id)` | One-to-one profile link. |
| Operational student list | Candidate `students(status, student_number)` | Add only if status-filtered stable lists are confirmed; no public name index. |
| Student history/current placement | Candidate `enrollments(student_id, academic_year_id, status)` | Supports exact self/admin history and current selection. |
| Class roster | Candidate `enrollments(class_id, status, student_id)` | Supports paginated rosters and assignment-scoped queries. |
| Year reports/current year | Candidate `academic_years(status, starts_on)` | Verify the current-year query and date logic. |
| Term selector | Candidate `terms(academic_year_id, status, starts_on)` | Add only if the term selector needs it. |
| Year/grade/class list | Candidate `classes(academic_year_id, status, grade_level, name)` | Compare with the business unique key and actual `EXPLAIN`; avoid duplicate indexes. |
| Current homeroom | Candidate `class_teacher_assignments(class_id, status, ends_on)` | Supports class detail and current homeroom lookup. |
| Guru homeroom scope | Candidate `class_teacher_assignments(teacher_id, status, ends_on)` | Supports current scope. |
| Guru subject scope | Candidate `teaching_assignments(teacher_id, status, ends_on)` | Only if subject assignments are approved. |
| Class subject roster | Candidate `teaching_assignments(class_id, subject_id, status)` | Only if subject assignments are approved. |
| Guardian relationship lookup | Candidate `student_guardians(student_id, ends_on, is_primary_contact, priority_order)` | Exact order follows the active primary/contact query. |
| Reverse guardian lookup | Candidate `student_guardians(guardian_id, ends_on)` | Only for an authorized workflow. |
| Student status lists | Candidate `students(status, student_number)` | No public index or search index. |

### 11.2 Index rules

- verify every composite order with representative MariaDB data and `EXPLAIN`;
- do not add an index for every status/date/name column by rote;
- do not add a full-text index until language, search behavior, and query plans are approved;
- do not index raw NIK, email, phone, student number, or registration number merely for convenience; exact sensitive lookup requires a privacy-reviewed design;
- do not add partitioning, sharding, replica keys, or tenant keys;
- do not treat Redis keys as a substitute for a durable MariaDB index;
- review index write cost and selectivity before adding a birth-date, name, or contact index.

## 12. Model relationships

The following relationships are the intended model surface. Relationship methods describe storage; they do not grant authorization.

| Model | Relationships |
|---|---|
| `User` | `hasOne` nullable `Teacher`; `hasOne` nullable `Student`; no generic profile morph and no guardian relationship. Existing credential relationships remain framework-owned. |
| `Teacher` | `belongsTo` nullable `User`; `hasMany` `ClassTeacherAssignment`; `hasMany` `TeachingAssignment` conditionally. |
| `Student` | `belongsTo` nullable `User`; `hasMany` `Enrollment`; `belongsToMany` `Guardian` through `StudentGuardian`. No `currentClass` mutable field. |
| `Guardian` | `hasMany` `StudentGuardian`; belongs to Students through the explicit pivot. No User relationship. |
| `StudentGuardian` | `belongsTo` Student; `belongsTo` Guardian; relationship flags/effective dates are casts/attributes, not authorization. |
| `AcademicYear` | `hasMany` `Term`; `hasMany` `Class`; scoped `hasMany` `Enrollment` and assignments where explicit year links are retained. |
| `Term` | `belongsTo` AcademicYear; has conditional teaching assignments. |
| `Class` | `belongsTo` AcademicYear; `hasMany` Enrollment; `hasMany` class-teacher assignments; conditional `hasMany` teaching assignments. |
| `Subject` | `hasMany` TeachingAssignment only if the subject scope is approved. |
| `ClassTeacherAssignment` | `belongsTo` Class; `belongsTo` Teacher. |
| `TeachingAssignment` | `belongsTo` Teacher, Class, Subject; optional Term. |
| `Enrollment` | `belongsTo` Student, Class, AcademicYear. Current placement is a query over status/effective dates. |

Models should use explicit table names, casts, and relationships consistent with the current Laravel 13 attribute style. They must not expose a relationship that a public or cross-user query can traverse without a scoped service/policy. No unconstrained polymorphic relationship is acceptable for privacy-sensitive ownership.

## 13. Enum/value-object strategy

### 13.1 Backed enums

Use backed string enums and model casts, following the existing `UserRole` and `AccountStatus` pattern. Do not add roles or alter account statuses. Proposed domain enums are:

- `TeacherEmploymentStatus` — `active`, `leave`, `inactive`, `retired` (owner confirmation required);
- `StudentLifecycleStatus` — `active`, `on_leave`, `graduated`, `transferred`, `withdrawn`, `archived` (owner confirmation required);
- `GuardianLifecycleStatus` — `active`, `inactive`, `archived` (owner confirmation required);
- `AcademicYearStatus` — `draft`, `active`, `closed` (owner confirmation required);
- `TermStatus` — `draft`, `active`, `closed` (owner confirmation required);
- `ClassStatus` — `planned`, `active`, `closed` (owner confirmation required);
- `AssignmentStatus` — `planned`, `active`, `ended`, `cancelled` (owner confirmation required);
- `EnrollmentStatus` — `pending`, `active`, `transferred`, `completed`, `withdrawn`, `on_leave` (O2-04 confirmation required);
- `GuardianRelationshipType` — only the school-approved vocabulary, not a role or permission set.

The migration columns should use ordinary string columns with application enum casts, matching the current Phase 1 convention, unless the implementation review approves database check constraints. A database constraint must be additive and must not prevent an explicitly approved future state without a reviewed migration.

### 13.2 Value objects and normalization boundaries

- Use a small academic-period/effective-date value boundary to enforce start/end ordering and containment within an academic year; do not scatter date arithmetic across controllers.
- Use a grade-level validator backed by the owner-approved list. Do not create a `grade_levels` table until a real catalog-management requirement exists.
- Use dedicated identifier validation/normalization services only after O2-01/O2-17 define format, case, length, masking, and correction behavior. Do not put a raw official identifier in a URL, log, exception, cache key, or generic validation message.
- Keep `legal_name` separate from `users.name`; normalization must not silently rewrite legal history.
- Store timestamps in UTC per the proposed O2-17 convention and present school dates in the approved `Asia/Jakarta` calendar. Date-only effective boundaries must be handled as school-calendar dates, not accidentally shifted by server timezone.

## 14. Form Request validation strategy

Form Requests will authorize first and validate second. A domain request must never bind a raw request array directly to a sensitive model.

### 14.1 Request responsibilities

- One create/update request per approved resource or workflow command.
- `authorize()` calls the relevant Policy/Gate with the route-bound resource or an explicit resource identifier; it does not return `true` for domain mutations merely because middleware checked a role.
- `rules()` validates syntax, lengths, dates, enum values, and conditional fields using the owner-approved vocabulary.
- `prepareForValidation()` performs only approved normalization, such as trimming and lowercase account email; it does not log or expose the normalized sensitive value.
- `safe()->only()` or typed command objects select fields explicitly. Service methods accept typed inputs rather than arbitrary arrays.
- Generic create/update requests do not accept `user_id`, `role`, account `status`, relationship IDs chosen by the client, current-class pointers, or audit actor fields.
- Status changes, account links, transfers, primary-contact changes, and corrections use dedicated commands/services so they can enforce state, locks, effective dates, and audit.

### 14.2 Validation groups

| Resource/action | Validation focus |
|---|---|
| Teacher/profile | Approved name/identifier fields, employee-number format/uniqueness, employment dates, lifecycle transition; no role/password/photo fields. |
| Student/profile | Approved legal name, student number, lifecycle dates/reason, conditional identity/contact fields; no current class or public projection fields. |
| Guardian/relationship | Approved relationship vocabulary, contact minimum, primary/emergency flags, effective dates, priority and one-primary invariant. |
| Academic year/term | Code/label, date ordering, parent-year containment, sequence uniqueness, status transition. |
| Class | Academic year, approved grade level, class name, capacity if approved, status and closure fields; capacity cannot be changed to bypass enrollment checks. |
| Assignment | Teacher/class/subject/term relationship, effective dates contained by the academic period, status transition, duplicate/active-scope service check. |
| Enrollment | Student/class/year consistency, lifecycle transition, effective date ordering, capacity, one-active-per-year and transfer/rollover action rules. |

Identifier uniqueness/existence errors must be generic enough not to reveal whether a student, employee, or guardian record exists. The service may return a controlled administrative correction flow for authorized actors, but ordinary responses must not echo sensitive values.

## 15. Policy and authorization matrix

The matrix below is a proposed contract for owner review. `—` means denied unless a separately approved decision changes it. Middleware can protect a route group, but every resource operation still requires the policy and query scope.

| Resource/ability | Public/guest | Administrator | Guru | Siswa |
|---|---:|---:|---:|---:|
| View approved public people projection | — in this increment | Manage all | Future approved public fields only | Future approved public fields only |
| View/manage teacher profiles | — | View/manage/correct; sensitive reads audited | Own approved fields only | — |
| View/manage student profiles | — | View/manage/correct; sensitive reads audited | Assigned students, minimum fields only | Own profile/approved fields only |
| View/manage guardian records | — | Manage; sensitive reads audited | Only a specifically approved assignment/field | Only explicitly approved own-linked information |
| View academic years/terms | — | Manage | Current/assigned context only | Own applicable context only |
| Manage academic years/terms | — | Manage through academic service | — | — |
| View class administrative detail | — | Manage | Current assigned classes only | Own class context only |
| Create/update/close classes | — | Manage through class service | — | — |
| Manage homeroom assignments | — | Manage | View own assignments | — |
| Manage teaching assignments | — | Manage if approved | View own assignments | — |
| Create/change/end enrollments | — | Manage through enrollment service | — | — |
| View enrollment history | — | View/manage | Assigned current/minimum context only | Own history |
| Export sensitive people/academic data | — | Approved, reasoned, audited action | Only if separately approved | — |
| Hard delete any domain/person/history row | — | Denied by default | — | — |
| User account lifecycle | — | Existing Phase 1 policy/service | Own account fields only | Own account fields only |

Administrator access is broad only within the approved domain boundary. It does not make raw credentials, unnecessary PII, public student projections, or private files readable. A policy denial should not reveal resource existence through an inconsistent response.

## 16. Guru authorization and scoping rules

1. Require an active, verified authenticated `User` with the fixed `guru` role.
2. Resolve the linked `Teacher` profile from `users.id`; a missing, mismatched, inactive, or suspended profile/assignment fails closed.
3. A Guru may see a student only when the student has an applicable current/effective enrollment in a class for which the Guru has:
   - an active/effective homeroom assignment, if homeroom scope is approved; and/or
   - an active/effective subject teaching assignment, if subject scope is approved.
4. Historical assignment rows do not grant current access. A future historical-access ability must be a separately approved policy, not a consequence of retaining history.
5. A Guru with an assignment in one class cannot enumerate, search, count, export, or traverse another class, a historical class, another teacher's records, a guardian, or a PPDB record.
6. The list query must filter in the database through the assignment scope before pagination. It must not fetch all students and filter in PHP.
7. The initial student field allowlist is limited to the owner-approved minimum. The Phase 2 recommendation is name, school student number, current class context, and only a contact field with a documented operational need. NIK, birth data, address, guardian data, private photos, and documents are excluded unless separately approved.
8. Field projection is enforced at query/serialization time. Loading a full Student and hiding fields in Blade is not sufficient.
9. Assignment end dates/status changes revoke access immediately according to the approved effective-date rule. Tests must cover the boundary date, ended status, suspended account, and teacher employment leave/inactive/retired states.
10. Role middleware may guard `/guru`-style route groups, but it cannot replace the assignment policy or scoped query.

## 17. Siswa self-access rules

1. Require an active, verified authenticated `User` with the fixed `siswa` role.
2. Resolve the Student by `students.user_id = authenticated_user.id`; never accept a route Student ID as the ownership decision.
3. A Siswa may view their own approved profile, current enrollment/class context, applicable term context, and bounded enrollment history.
4. A Siswa may not view, search, count, export, or traverse another Student, class roster, guardian record, teacher private profile, PPDB application, audit record, or account record.
5. A route-bound Student/Enrollment/Guardian must be checked against the linked User even if the ID is syntactically valid.
6. Missing profile linkage, inactive/suspended account, unverified email, or ambiguous ownership denies access. Account deactivation blocks access but does not delete the Student or enrollment history.
7. There is no public Student projection, public directory, or public student achievement in this phase.
8. Responses and validation errors must not disclose whether another student's identifier exists. Direct-ID negative tests must verify both authorization and non-disclosure.

## 18. Guardian privacy boundaries

- A Guardian is a person record, never a User, role, session, or login credential.
- A student may have zero or more Guardian links, subject to the approved operational completeness rule. A Guardian may link to multiple students; duplicate resolution is explicit rather than based only on shared email/phone/name.
- `student_guardians` is the authority/relationship history. Ending or changing a relationship end-dates the old row and creates a new relationship when appropriate; it does not overwrite history.
- Primary and emergency contact flags are distinct. Exactly one active primary is a proposed operational rule, not yet approved; if the school supports shared custody or incomplete intake, the exception must be explicit.
- Administrators manage Guardian data. A Guru receives no Guardian data by role alone and only receives a specifically approved field if an assignment requires it. A Siswa receives only explicitly school-approved information about their own linked Guardian, if any.
- Guardians receive no access to dashboards, student records, documents, or exports in this release.
- Guardian phone, email, address, and identity data are not placed in public projections, ordinary logs, cache keys, or generic validation responses. Contact indexes are conditional on an approved authorized lookup workflow.

## 19. User/profile lifecycle

The lifecycle is split into two independent facts:

- **Account status:** existing `active`, `inactive`, `suspended` values control login and protected access.
- **Person status:** proposed teacher/student/guardian employment or lifecycle values control domain selection and history; they do not automatically log a person in or out.

Required behavior:

1. Create a Teacher/Student profile independently of account provisioning. A profile can exist before login invitation or account activation.
2. Provision a Guru/Siswa account only through the controlled Phase 1/identity workflow; do not infer an account from a profile row.
3. Before activating a `guru` or `siswa` account, verify the matching profile link and reject a user linked to both profile types.
4. Changing role/status uses `AccountLifecycleService` or a service that delegates to it, with authorization, row locking, session/remember-token invalidation, and the existing redacted security event. No generic `fill()` or request-array update may change role/status.
5. Suspending or deactivating an account preserves the profile, academic assignments, enrollments, and audit references. It does not change a person to `archived` or delete history.
6. Replacing or correcting a profile/account link is a controlled, audited action with an owner-approved procedure. There is no direct `user_id` mass assignment and no polymorphic profile link.
7. Hard deletion of a User remains denied. If a future erasure policy permits it, it requires a separate additive migration, privacy review, and a plan for preserving non-identifying audit references.
8. Email changes, invitation tokens, MFA, and password-change self-service are not added by this plan; they require their own identity/security decisions.

## 20. Enrollment lifecycle and historical preservation

The proposed lifecycle vocabulary is `pending`, `active`, `transferred`, `completed`, `withdrawn`, and `on_leave`; exact meanings and allowed transitions must be approved under O2-04.

### 20.1 Core rules

- At most one active class placement per student per academic year is the recommended baseline, subject to owner approval.
- A transfer locks the student/year context, ends the old enrollment with an effective end date/reason, checks target capacity, and creates a new active enrollment in one transaction.
- A rollover creates a new year/class/enrollment set; it never mutates the prior year to point at the new year.
- Leave, completion, withdrawal, graduation, and transfer close or suspend the relationship using explicit status/effective dates. They do not delete the row.
- A Student account deactivation does not change enrollment status.
- Class capacity is checked under a transaction/lock before activation or transfer. A capacity race must not produce over-capacity active enrollment.
- A current-placement query applies the approved operational date, enrollment status, class status, academic year, and assignment scope together.
- A correction to a historical fact is a controlled, audited action. It cannot be achieved by deleting and recreating a row without an approved retention decision.

### 20.2 Historical preservation

The current Phase 2 design preserves prior placement rows and assignment rows. It does not yet require a separate `enrollment_status_histories` table. The owner must explicitly decide whether every same-row lifecycle transition needs a durable event independent of the central audit trail. Until that decision, the implementation must not silently add such a table or claim that a mutable status alone reconstructs all history.

The durable source of truth is MariaDB. Redis may later coordinate a retry, but it cannot determine the current enrollment or retain the only lifecycle history.

## 21. Service-layer responsibilities

Services/actions own multi-step behavior and keep controllers thin. The exact class names should follow existing `App\Services` conventions, but responsibilities are fixed:

- **Profile service:** create/update approved Teacher/Student/Guardian facts, lifecycle transitions, corrections, and account-link invariants; never accepts a request array wholesale.
- **Academic calendar service:** create/validate/open/close years and terms, enforce date containment, and perform reviewed rollover preparation.
- **Class service:** create/activate/close year-specific classes, validate grade/name/capacity, and protect historical class references.
- **Assignment service:** create/end homeroom and subject assignments, enforce period containment, current homeroom, duplicate scope, and effective dates.
- **Enrollment service:** activate, transfer, leave, complete, withdraw, and roll over placements; lock rows; check capacity; preserve history; emit an audit hook.
- **Guru scope service/query:** resolve the linked teacher and expose only the current/effective assigned-student query and approved field projection.
- **Siswa ownership service/query:** resolve the authenticated student's own profile and enrollment context.
- **Account/profile link service:** reuses Phase 1 account lifecycle and session invalidation; checks role/profile consistency before activation.
- **Audit hook/port:** accepts a redacted domain event descriptor, not a full model/request payload. The durable audit store remains a later platform decision.

Cross-module writes must call the owning service. A future PPDB conversion must not insert a Student or Enrollment directly from a controller. No custom permission service is introduced.

## 22. Audit/security considerations

### 22.1 Audit hooks

The architecture requires audit accountability for sensitive reads and domain changes, but the repository currently has application logs rather than a durable append-only audit store. The implementation must therefore:

- reuse the existing redacted logging style for identity/account events;
- emit structured, minimal domain events for teacher/student/guardian reads, profile corrections, academic status changes, assignment changes, enrollment transitions, capacity decisions, and denied privileged actions when the audit contract is available;
- record actor, action, target type/opaque target ID, result, timestamp, and correlation/request ID only as approved by the eventual audit schema;
- never record passwords, hashes, tokens, session IDs, full request arrays, full names/contact details, NIK, student numbers, employee numbers, guardian data, or raw documents;
- keep sensitive-read auditing separate from ordinary page-view logging and rate-limit/aggregate noisy denials appropriately;
- treat audit-write failure and retention/hold behavior as an operations gate, not a reason to silently broaden access.

A new `audit_logs` table is not part of this implementation plan because O2-13/B-18 are not approved. The service boundary must nevertheless be designed so the later append-only store can be attached without changing domain truth.

### 22.2 Privacy and data handling

- Use query-time field allowlists for internal projections; do not rely on Blade to hide sensitive columns.
- Do not expose raw student, employee, national, guardian, or official identifiers in public URLs, filenames, cache keys, log messages, validation errors, or error/debug pages.
- Use generic administrative responses for identifier collisions or existence checks; provide a controlled correction flow to authorized administrators.
- Use explicit model fillable/hidden attributes and service-level `forceFill`/typed input only for approved transitions. Do not mass-assign `user_id`, `role`, `status`, relationship IDs, or audit actor fields.
- Keep sensitive responses non-cacheable/private and avoid shared personalized caching. No student/guardian data belongs in Redis.
- Require a fresh policy check for any later export or private-file access. Possession of an ID or URL is not permission.
- Preserve CSRF, session invalidation, rate limiting, and generic authentication errors from Phase 1.
- Never add a public student relationship, a public Student model serialization, or an implicit Eloquent relationship endpoint that bypasses policy.

## 23. MariaDB integration testing strategy

SQLite remains useful for fast unit/feature tests, but MariaDB is mandatory for schema and concurrency claims.

### 23.1 Test environment

- Use a separately provisioned disposable MariaDB schema with a distinct name and credentials; never use the shared `sekolah_kita` schema for destructive or reset-based tests.
- Run the current migrations and future additive migrations against that isolated schema only after the owner decision gates are recorded.
- Do not use `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, or equivalent reset/lifecycle commands. Create a new isolated schema through the approved environment process when a clean schema is required.
- Do not use `RefreshDatabase` or a test helper that drops/recreates the shared MariaDB schema. Use a dedicated schema and transaction-based cleanup or a controlled migration lifecycle.
- Use synthetic data only; factories must not create fixed production credentials or real personal data.

### 23.2 Required integration checks

1. `users` and existing migration/framework tables remain present and unchanged.
2. Every planned foreign key is present, indexed, and has the intended delete action.
3. Nullable unique account links permit unlinked profiles but reject duplicate links.
4. Employee/student number uniqueness and non-reuse rules behave as approved.
5. Term sequence and class business keys reject duplicates.
6. Active homeroom and active enrollment invariants survive concurrent requests without double-current rows or over-capacity classes.
7. Transfer transactions roll back both the old-row end-date change and the new-row insert if either operation fails.
8. Date containment, non-overlap, and effective-date boundary rules behave correctly on the actual MariaDB engine.
9. Query plans for Guru scope, class rosters, student history, and current-year selection use the intended indexes without full scans at representative volume.
10. MariaDB foreign-key enforcement rejects invalid parent/child references; SQLite success is not sufficient evidence.
11. Migration application from the current baseline preserves existing users and migration ledger rows.
12. Any database-level current-row or assignment uniqueness mechanism is tested under simultaneous transactions, not only sequential calls.

The implementation must record MariaDB version, connection name, schema name, migration status, and test command in the implementation evidence without recording credentials or secrets.

## 24. Feature/authorization/privacy test matrix

| Test layer | Representative cases | Required negative cases |
|---|---|---|
| Unit | Enum casts, lifecycle transition rules, effective-date containment, identifier normalization, capacity calculation, current-placement selection | Invalid transitions, reversed dates, duplicate current placement, identifier leakage in error objects |
| Relationship | User–Teacher/Student optional links, StudentGuardian pivot, year/term/class/assignment/enrollment relationships, no cross-profile link | User linked to both profiles, guardian with user, orphan/deleted history |
| Migration/schema | Tables, columns, nullability, FKs, unique keys, indexes, existing user preservation | Invalid FK, duplicate key, missing index, wrong delete behavior |
| MariaDB integration | Transaction rollback, lock behavior, active uniqueness, capacity, `EXPLAIN`, date range checks | Concurrent double activation, capacity breach, stale scope assignment |
| Feature/account lifecycle | Profile creation without account, controlled account link, deactivation/suspension blocks access, history remains | Direct user/profile ID change, role/status mass assignment, session reuse after deactivation |
| Feature/academic | Year/term creation, class activation/closure, assignment effective dates, enrollment placement/transfer/rollover | Client-supplied year/class mismatch, out-of-period dates, unauthorized lifecycle mutation |
| Authorization administrator | Active verified administrator can manage approved people/academic resources; sensitive actions are auditable | Inactive/unverified/non-admin denial, hard-delete denial, excessive fields |
| Authorization Guru | Current homeroom/teaching assignment can view approved student fields and assigned roster | Unassigned Guru, historical-only assignment, other class, direct Student/Enrollment/Guardian ID, search/count/export |
| Authorization Siswa | Own profile/enrollment/history and own class context | Other Student, other enrollment, other guardian, class roster, arbitrary user/profile ID, unlinked account |
| Privacy | Public-safe internal projection contains only allowlisted fields; private response has no sensitive identifiers | Student/guardian/employee number, NIK, email/phone, raw IDs, storage paths, or full model in HTML/JSON/errors |
| Logs/cache/URLs | Structured redacted events and no domain cache writes | Raw sensitive identifier in log, exception, rate-limit key, cache key/value, URL, filename, or validation message |
| Negative boundary | Inactive, suspended, unverified, missing role, missing profile, expired assignment | Every denial path returns a non-disclosing response and creates no side effect |

The default test suite should add feature tests before adding pure unit tests for framework behavior. No Dusk/browser dependency is required for this planning increment; response and authorization tests should cover the web contract.

## 25. Migration sequencing

No migration file is created in this planning task. After approval, the following additive groups are the proposed order:

1. **People profile group:** create `teachers`, `students`, `guardians`, then `student_guardians`, with explicit user/profile foreign keys and approved indexes. Do not alter `users`.
2. **Academic calendar group:** create `academic_years`, then `terms`, then `classes`; add grade-level validation and year/term lifecycle services.
3. **Assignment group:** create `subjects` only if approved, then `class_teacher_assignments`, then `teaching_assignments` if approved. Add the chosen active-scope constraints/indexes only after MariaDB proof.
4. **Enrollment group:** create `enrollments` after all referenced people/academic tables exist; add the approved active-placement uniqueness mechanism and composite year/class integrity.
5. **Optional technical hardening group:** add an explicitly approved current-row marker/index, composite foreign key, or additional query index only if the first MariaDB tests prove it necessary. This is additive, not a reason to add speculative fields earlier.
6. **Future data backfill group:** if an owner later authorizes a backfill, create a separate reviewed, idempotent data migration. Do not infer profiles or roles from existing accounts automatically.

Each migration group must include its decision references, synthetic factories, schema tests, policy/scope tests, and rollback/recovery notes before deployment. New migrations must be appended after the existing four migrations; existing files are immutable.

## 26. Rollback/recovery considerations without destructive database commands

- Before any shared-environment migration, take a reviewed encrypted backup, verify its restore path, record table-lock/maintenance impact, and confirm the migration/application compatibility order.
- Prefer a forward corrective migration for a schema or logic defect. Do not edit a deployed migration or run an ad hoc destructive `DROP`, `TRUNCATE`, or mass `DELETE`.
- If a new domain table is empty and a reviewed emergency recovery is approved, the recovery owner may use a separately documented restore or controlled forward fix; this plan does not authorize a rollback command against the existing database.
- Domain lifecycle recovery deactivates/archives people, closes classes/assignments, or end-dates enrollments through authorized services, preserving history and audit events.
- A failed transaction must leave both the old and new placement unchanged. Retried transfer/conversion actions must be idempotent.
- Migration `down()` methods, if generated for local/test convenience, are not a production recovery procedure and must not be invoked against the existing database.
- A restore of MariaDB and any future private media must be coordinated and tested; Redis data is disposable and must not be treated as the recovery source for domain records.
- If a migration partially applies, stop the release, preserve logs/backup, inspect the migration ledger and schema read-only, and follow an owner-approved forward recovery. Do not improvise resets.

## 27. Performance considerations

- MariaDB remains the source of truth. Measure the named Guru scope, class roster, student history, and current-year queries with representative data and `EXPLAIN` before finalizing composite indexes.
- Resolve the authenticated profile and assignment scope in the database. Avoid loading all students/classes and filtering in PHP.
- Use bounded pagination for all people, roster, assignment, and history lists. Use keyset pagination only when offset cost is measured for a large view.
- Select only required columns, especially for Guru and Siswa projections. Eager-load only the class/assignment context needed by a screen; use counts/aggregates rather than hydrating full rosters.
- Index common status/date/effective filters in the order supported by actual query plans. Do not add indexes blindly for every status or date.
- Use short transactions and row locks for enrollment/assignment/capacity changes. Do not perform mail, queue work, external storage operations, or unbounded loops inside the transaction.
- Measure query count per list/detail action and establish a budget before adding dashboard features. Prevent N+1 queries in future controllers and Blade views.
- Do not cache personalized Guru/Siswa dashboards, student records, guardian data, or academic authorization results globally. A future cache must be audience-specific, privacy-reviewed, and invalidated correctly.
- The existing approximately 1,000-user workload is a planning hypothesis, not a capacity promise. No load-test or SLO claim is made by this plan.
- Do not add partitioning, sharding, read replicas, or speculative denormalization until measurements justify them.

## 28. Redis boundaries

The current repository uses database drivers for sessions, cache, and queue. Redis is a future production infrastructure target, not a Phase 3 domain dependency.

- Do not store academic years, terms, classes, assignments, enrollments, Student/Teacher/Guardian profiles, identifiers, lifecycle truth, or authorization decisions as the only copy in Redis.
- Do not put names, emails, phones, NIK, NISN, employee numbers, student numbers, registration numbers, or other sensitive identifiers in Redis keys or values.
- If a future cache is approved, use versioned, non-identifying keys for public-safe aggregates only; invalidate them on relevant publication/profile changes. Personalized student and Guru results remain uncached or strictly audience-scoped.
- Redis may later support sessions, rate limits, queues, and short-lived distributed coordination. MariaDB row locks/transactions remain the authority for uniqueness and lifecycle transitions.
- Queue payloads, when introduced later, should contain opaque IDs and minimal context, dispatch after commit, and be idempotent/retryable. No queue is required for the basic Phase 3 CRUD/lifecycle actions unless a later approved workflow needs one.
- Redis failure behavior, prefixes, persistence, isolation, and production fallback remain O2-15 decisions. The implementation must not silently fall back to an unsafe authorization or data-integrity path.

## 29. Risks and unresolved decisions

### 29.1 Blocking owner decisions

| Decision | Why it blocks Phase 3 | Safe interim plan |
|---|---|---|
| O2-01 identifiers/minimum fields | Required/nullable columns, unique/correction rules, masking, possible encryption/digest | Do not create people tables with placeholder identifiers. |
| O2-02 calendar/grade levels/programs | Year/term cardinality, class key, date rules, subject scope | Do not hard-code two semesters, a grade catalog, or a program table. |
| O2-04 enrollment lifecycle | Status names, one-active rule, transfer/rollover/leave/completion semantics, uniqueness | Plan rows/services but do not create an enrollment migration. |
| O2-05 Guru scope/minimum fields | Whether subject assignments exist and exactly which student fields are visible | Do not create a broad student query or subject table. |
| O2-13 retention/audit/hold | Historical deletion, audit event contract, whether an enrollment event table is needed | Preserve rows and use redacted hooks; do not add audit/retention schema. |
| O2-17 locale/date/identifier conventions | Lengths, normalization, date boundaries, validation/masking | Do not freeze sensitive field formats or effective-date interpretation. |
| B-05/B-06 profile and guardian relationship rules | Link timing, account replacement, primary/emergency rules, required fields | Keep account and person separate; no guardian account. |

### 29.2 Technical decisions requiring explicit review

- whether the owner wants `school_profiles` in this phase despite the narrower requested scope;
- whether the school requires class capacity and closure reasons;
- whether one or multiple homeroom teachers are allowed and how replacements are represented;
- whether subject teaching assignments are year-wide or term-specific, and whether co-teaching is allowed;
- whether a term can have a separate active student placement;
- whether every same-row enrollment status transition needs a durable event table beyond the later audit store;
- how MariaDB should enforce one active homeroom and one active enrollment without unsafe partial-index assumptions;
- whether class merge/split/rename needs a successor relationship or can be represented only by closure and new records;
- whether Guru contact access is operationally necessary beyond the minimum allowlist;
- whether any identifier requires encrypted storage or keyed-digest lookup;
- whether sensitive domain reads can be logged now through the current application log while the durable audit store is deferred.

### 29.3 Baseline risks

- The live default connection is named `mysql` while the server is MariaDB; implementation tests must use an explicit approved connection without changing `.env` in this pass.
- The Phase 1 document's migration status is older than the live schema; use the live migration ledger and never re-run or edit the identity migration.
- The current fast test environment is SQLite, so a passing suite alone cannot validate foreign keys, uniqueness, locks, or MariaDB query plans.
- The current local configuration has debug enabled, database-backed infrastructure, and no domain cache; do not infer production readiness from local tests.
- The current user count is zero, but the design must still support future existing users without automatic profile creation or role changes.

## 30. Exact implementation order for the next coding pass

The next coding pass must stop at the first unresolved gate. Assuming the required decisions are recorded, use this exact order:

1. Capture the owner decision references and confirm the requested scope excludes school profile, media, PPDB, and deferred academic modules.
2. Re-run read-only baseline checks: `git status`, `migrate:status`, live schema inspection, route list, and the current test suite.
3. Use Laravel generators, with `--no-interaction`, to create only the approved enums, migrations, models, factories, Form Requests, Policies, services, and tests. Do not add dependencies.
4. Add the people migrations in dependency order, then run their narrow SQLite tests and isolated MariaDB schema tests.
5. Add Teacher/Student/Guardian models, explicit account relationships, lifecycle services, policies, and privacy projections. Verify Phase 1 tests still pass.
6. Add academic year/term/class migrations and models; validate dates, grade values, and year-specific uniqueness with MariaDB tests.
7. Add subject/homeroom/teaching assignment tables only if approved. Prove the active-scope query and direct-ID negative paths before adding any student read endpoint.
8. Add enrollment migrations and services. Prove transfer/rollover/capacity/concurrency behavior before enabling any write route.
9. Add only approved internal controllers/requests/routes, retaining the existing middleware chain and named-route conventions.
10. Add audit/security hooks using the existing redacted logging boundary; do not create the deferred audit store.
11. Run the complete fast suite, MariaDB integration suite, privacy/negative tests, Pint for changed PHP files, `git diff --check`, route/migration checks, and a read-only review of logs/cache/output for identifier leakage.
12. Stop for owner review of schema, query plans, security evidence, and recovery readiness before applying anything to a shared database.

If any owner decision is missing, stop before creating the corresponding migration; do not “finish” a partial schema with nullable speculative columns.

## 31. Definition of Done

Phase 3 domain implementation is done only when all applicable items are true:

- [ ] Required owner decisions are recorded and linked to every table/field/invariant.
- [ ] Existing four migrations, users, authentication data, and Phase 1 tests are preserved.
- [ ] Only additive MariaDB migrations were used; no existing migration was edited, removed, or reset.
- [ ] Teacher, Student, Guardian, StudentGuardian, academic, approved assignment, and enrollment models have explicit relationships and factories.
- [ ] No `school_id`, guardian/applicant role, generic polymorphic profile link, public student projection, media, PPDB, or deferred academic table slipped into the change.
- [ ] Required identifiers have approved uniqueness, immutability/correction, normalization, privacy, and masking rules.
- [ ] Foreign keys, indexes, unique constraints, and delete behavior are verified on MariaDB, not only SQLite.
- [ ] Active enrollment, homeroom, capacity, effective-date, and rollover invariants pass concurrency/transaction tests.
- [ ] Policies, query scopes, Form Request authorization, and middleware enforce administrator, Guru, and Siswa boundaries server-side.
- [ ] Guru historical/unassigned/cross-class access and Siswa cross-user access are denied in list, detail, direct-ID, relationship, count, search, and export paths.
- [ ] Guardian records cannot authenticate and have no role/login fields.
- [ ] Sensitive fields are absent from public/internal projections, URLs, logs, validation responses, cache keys, filenames, and error output.
- [ ] User/profile lifecycle deactivation preserves history and reuses Phase 1 session invalidation.
- [ ] Historical enrollment and assignment records survive class changes, rollover, deactivation, and closure according to approved retention.
- [ ] Audit hooks are redacted and do not pretend that application logs are the future append-only audit store.
- [ ] MariaDB and fast test suites pass; query plans, route authorization, privacy, and recovery evidence are reviewed.
- [ ] No new dependency, environment change, production seed, destructive command, commit, or out-of-scope feature was introduced.

## 32. Files expected to change during implementation

These are expected future files, not files created by this planning task. Exact names/timestamps follow the approved scope and Laravel generator output.

- `app/Enums/` — approved lifecycle, assignment, enrollment, and relationship enums only.
- `app/Models/Teacher.php`, `Student.php`, `Guardian.php`, `StudentGuardian.php`, `AcademicYear.php`, `Term.php`, `Class.php`/approved class model name, `Subject.php` if approved, `ClassTeacherAssignment.php` if approved, `TeachingAssignment.php` if approved, and `Enrollment.php`.
- `app/Models/User.php` — only explicit optional Teacher/Student profile relationships and narrowly required helpers; preserve Phase 1 fields and authentication behavior.
- `app/Policies/` — approved Teacher, Student, Guardian, AcademicYear, Term, Class, assignment, and Enrollment policies; existing `UserPolicy` behavior remains intact.
- `app/Services/` — profile/account-link, academic calendar, class, assignment, enrollment, Guru-scope, and Siswa-scope services; reuse existing account/session services.
- `app/Http/Requests/` — approved people, academic, assignment, enrollment, and relationship requests; no generic request-array binding.
- `app/Http/Controllers/` — only thin internal controllers needed for approved domain actions.
- `app/Providers/AppServiceProvider.php` — only policy/authorization/scoped-service registration that cannot be auto-discovered, while preserving existing gates and rate limiters.
- `routes/web.php` — only approved internal domain routes, with existing authentication/active/verified/role middleware preserved.
- `database/migrations/<new-timestamped-additive-migrations>` — the approved people, academic, assignment, and enrollment groups.
- `database/factories/` — synthetic factories and lifecycle states for all new models.
- `tests/Unit/`, `tests/Feature/`, and an approved MariaDB integration test location/configuration — tests for the contracts above.
- A separately reviewed decision/operations record if the owner explicitly requests one; this planning task does not create it.

No UI/public projection, media, PPDB, notification, audit-store, Redis, Composer, or environment file belongs in the expected change set for this scope.

## 33. Files that MUST NOT be modified

For this planning task, no file other than `docs/PHASE_3_IMPLEMENTATION_PLAN.md` may be modified. During the later implementation pass, the following remain protected unless a separate decision explicitly changes them:

- `database/migrations/0001_01_01_000000_create_users_table.php`;
- `database/migrations/0001_01_01_000001_create_cache_table.php`;
- `database/migrations/0001_01_01_000002_create_jobs_table.php`;
- `database/migrations/2026_09_24_115047_add_role_and_status_to_users_table.php`;
- `.env`, `.env.example`, and any secrets/credentials;
- `composer.json`, `composer.lock`, `package.json`, and `package-lock.json`;
- existing Phase 1 authentication controllers, requests, middleware, listeners, and password/session behavior;
- `app/Enums/UserRole.php` and `app/Enums/AccountStatus.php` vocabulary;
- existing authentication configuration and session/credential configuration;
- `database/seeders/DatabaseSeeder.php` fixed production/test provisioning behavior;
- public content/media/PPDB/attendance/grade/schedule/extra-curricular files and migrations that are outside scope;
- any existing migration used in the live database;
- Git history, branches, tags, or force-push state.

`app/Models/User.php`, `app/Providers/AppServiceProvider.php`, and `routes/web.php` may receive narrowly additive relationships/registrations/routes during implementation, but their existing authentication, mass-assignment, middleware, rate-limit, and role behavior must remain intact. No new base directory or dependency is assumed.

## 34. Commands that implementation is allowed to run

Subject to the owner decision gate and the safety rules, the implementation may run:

### Read-only inspection and verification

- `git status --short --branch`, `git diff --check`, `git diff --stat`, and `git log`;
- `php artisan --version`, `php artisan route:list --except-vendor`, `php artisan migrate:status`, `php artisan config:show ...`, and `php artisan about`;
- Laravel Boost read-only database connection, schema, and `EXPLAIN` queries;
- `composer show --direct`, `composer validate --strict`, `composer audit`, and read-only dependency inspection;
- `npm run build` when a frontend change actually requires it;
- `vendor/bin/pint --dirty --format agent` after PHP changes, and `vendor/bin/pint --format agent` if the formatter requires a broader pass;
- `php artisan test`, `php artisan test --compact`, and `vendor/bin/phpunit` for the narrowest relevant suite first, followed by the full suite;
- `php artisan migrate --pretend --no-interaction` to inspect generated SQL without applying it.

### Isolated MariaDB verification

- `php artisan migrate --no-interaction` only against a separately provisioned, disposable MariaDB test schema after the migration is approved and backed up;
- read-only `SHOW CREATE TABLE`, `information_schema`, `EXPLAIN`, and migration-ledger queries;
- synthetic factory/test execution and concurrency tests against that isolated schema;
- a reviewed backup/restore exercise performed by the responsible operator, never against the shared schema without explicit approval.

Commands that write to the shared `sekolah_kita` database are not allowed merely because they are in a general Laravel list. A connection name or environment must be explicit and reviewed before any migration command.

## 35. Commands that implementation is forbidden to run

The following are prohibited for this scope, including against development or production-like data:

- `php artisan migrate:fresh`;
- `php artisan migrate:refresh`;
- `php artisan migrate:reset`;
- `php artisan db:wipe`;
- `php artisan db:drop`, `db:show` commands used to destroy data, or equivalent schema/database deletion;
- `migrate:rollback` against the existing/shared database, or any migration `down()` operation that would remove live domain/history data;
- raw `DROP DATABASE`, `DROP TABLE`, `TRUNCATE`, destructive `DELETE`, or unreviewed mass `UPDATE` statements;
- `php artisan db:seed` against the shared or production-like database, or creation of fixed production credentials;
- `composer setup`, `composer create-project`, or post-create-project scripts for this existing repository because they can create SQLite or run migrations;
- `composer require`, `composer update`, `npm install`, dependency upgrades, or changes to lockfiles without a separate approval;
- Redis flush commands or eviction/configuration changes used to manipulate business state;
- writing to `.env` or running `key:generate`/environment changes as part of this domain increment;
- commands that seed real people, commit Git changes, reset Git history, or push/commit as part of this planning task;
- tinker or ad hoc application writes used to bypass migrations, policies, services, or audit hooks.

The safe recovery posture is forward correction plus a reviewed backup/restore process, not database reset. Any exception requires a separately documented owner/technical approval and cannot be inferred from this plan.

---

## Planning handoff

This document is ready for owner review as a gated implementation plan. It is not permission to create migrations or modify the current Phase 1 foundation. The next safe action is explicit owner confirmation of the blocking decisions, followed by a review of the decision-to-schema traceability before any coding pass begins.

The highest implementation risks are unresolved school-specific identity/calendar/enrollment/Guru-field rules, the lack of an approved durable audit/retention contract, and MariaDB-specific concurrency behavior for active assignment/enrollment invariants. The next coding pass must stop rather than invent answers to those questions.
