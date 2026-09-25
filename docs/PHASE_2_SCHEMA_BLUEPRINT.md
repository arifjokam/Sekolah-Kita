# Sekolah Kita — Phase 2 Schema Blueprint

**Date:** 2026-09-24
**Project:** Sekolah Kita
**Document status:** Logical data contract for review — **no migration is authorized or created**
**Companion decision document:** [`PHASE_2_DOMAIN_DECISIONS.md`](./PHASE_2_DOMAIN_DECISIONS.md)
**Architecture constraints:** [`PHASE_0_APPROVAL.md`](./PHASE_0_APPROVAL.md), [`ARCHITECTURE_DECISIONS.md`](./ARCHITECTURE_DECISIONS.md)

> This is an implementation-oriented logical blueprint, not PHP migration code and not a statement of final business approval. Fields marked recommended/conditional require the referenced Phase 2 owner decision before a migration is written. All records are single-school; no domain entity receives a `school_id` tenancy column.

---

## 1. Purpose and boundaries

This document defines the proposed first-release entity model for:

- identity/profile separation and person lifecycle;
- Guru, Siswa, and guardian records;
- minimum academic context and authorization scope;
- public/private media and content;
- PPDB review and controlled student conversion;
- required platform boundaries for audit, notifications, and contact.

It deliberately does **not** define SQL files, Laravel models, controllers, routes, factories, seeders, enums, or runtime configuration.

### 1.1 Planning rules

- MariaDB/InnoDB is the source of truth. UTF-8 (`utf8mb4`) and strict SQL mode remain the target.
- Existing migrations are immutable. All future changes are additive and require a reviewed backup/rollback/recovery plan.
- Every foreign key has an intentional delete action. Personal/history records prefer `restrict`, explicit lifecycle, or controlled relationship closure over destructive cascades.
- Primary keys are opaque internal identifiers. Public routes use approved opaque slugs, never personal identifiers or raw storage paths.
- Foreign-key columns are indexed by their constraints. Additional indexes are justified by a named query path, not added by rote.
- If an approved national identifier requires exact lookup, the implementation must use a reviewed encryption/tokenization or keyed-digest design. It must not be placed in ordinary logs, URLs, or an unneeded plaintext search index.
- All tables that store mutable domain data have `created_at` and `updated_at`; history/audit/log tables are append-oriented and use event time rather than ordinary mutable timestamps.
- `school_id` is not present. The single school is represented, where needed, by one singleton profile.
- The three existing roles remain `administrator`, `guru`, and `siswa`. No role/permission pivot table is introduced.
- A database default is not a business authorization decision. Status transitions and workflow invariants are enforced by services, policies, transactions, and tests.

### 1.2 Naming conventions

Logical names use lowercase plural English table names and `snake_case` columns. The final implementation may use a different Laravel class name, but the data contract should not drift silently.

- `*_id` is an internal foreign key unless explicitly called a public identifier.
- `*_at` is a timestamp; a date-only business fact ends in `_date`.
- `is_*` is a boolean invariant/flag; named workflow states use `status`.
- `*_media_id` references `media_assets`; no document/image blob is stored in a domain row.
- Public slug fields are nullable until the associated public page is approved.

---

## 2. Current schema boundary

The inspected repository currently has these migration files:

| Existing migration | Current role | Phase 2 treatment |
|---|---|---|
| `0001_01_01_000000_create_users_table.php` | `users`, `password_reset_tokens`, `sessions` | Preserve. No Phase 2 alteration. |
| `0001_01_01_000001_create_cache_table.php` | Framework cache tables | Preserve. May remain development fallback; not business storage. |
| `0001_01_01_000002_create_jobs_table.php` | Framework queue tables | Preserve. Redis is a later operational target. |
| `2026_09_24_115047_add_role_and_status_to_users_table.php` | Existing `users.role` and `users.status` | Preserve. Read-only status check for this phase reports it as run in batch 2. |

There are no current Guru, Siswa, guardian, academic, media, public-content, or PPDB tables. The logical entities below are therefore a future design, not a description of deployed tables.

### 2.1 Existing `users` contract used by this blueprint

| Field/relationship | Existing contract | Domain dependency |
|---|---|---|
| `id` | Internal bigint user key | Referenced by explicit profile/media/review links; never a public person ID. |
| `name` | Login/display label | Not the canonical legal name of a Guru/Siswa. |
| `email` | Globally unique login/reset/verification contact, normalized by current auth flow | Not copied automatically into a person profile. |
| `password`, `remember_token` | Credential/security fields | Never copied to domain profiles, media metadata, or audit snapshots. |
| `email_verified_at` | Account email verification time | Account lifecycle only. |
| `role` | Nullable enum-cast `administrator`, `guru`, or `siswa` | Must be assigned before normal protected access. |
| `status` | `active`, `inactive`, `suspended` | Independent of person employment/student status. |

The Phase 2 data contract does not add User columns. A future identity-security decision may add MFA metadata, but it is outside this phase.

---

## 3. Entity relationship map

```text
users (existing)
  0..1 --------------------------- 1 teachers
  0..1 --------------------------- 1 students
  1 ----------------------------- 0..* media_assets (uploader)
  1 ----------------------------- 0..* admission_reviews (assigned reviewer)
  1 ----------------------------- 0..* application_status_histories (staff actor, optional)
  1 ----------------------------- 0..* news_articles (author)
  role=administrator ------------> no separate administrator/person table

school_profiles (exactly one application record)

students * ------------------------ * guardians
                via student_guardians

academic_years 1 ------------------ * terms
academic_years 1 ------------------ * classes
classes * ------------------------ * subjects
                via teaching_assignments
teachers 1 ----------------------- * class_teacher_assignments * ---- 1 classes
teachers 1 ----------------------- * teaching_assignments * ------- 1 classes
students 1 ----------------------- * enrollments * ---------------- 1 classes
academic_years 1 ------------------ * enrollments

media_assets 1 -------------------- * media_variants

news_articles 0..1 ---------------- 1 media_assets (cover)
achievements 0..1 ---------------- 0..1 media_assets (cover)
achievements 1 ------------------- * achievement_recipients
  achievement_recipients 0..1 ---- 1 students (internal attribution only)
  achievement_recipients 0..1 ---- 1 teachers (internal attribution only)
gallery_albums 1 ----------------- * gallery_items * ---------------- 1 media_assets
gallery_albums 0..1 -------------- 0..1 media_assets (cover)

admission_periods 1 --------------- * admission_applications
admission_periods 1 --------------- * admission_period_quotas [only if scoped quota approved]
admission_applications 1 ---------- * application_guardians
admission_applications 1 ---------- * application_documents * ----- 1 media_assets (private)
admission_applications 1 ---------- * application_status_histories
admission_applications 1 ---------- * admission_reviews
admission_applications 0..1 ------ 0..1 students (accepted conversion)
admission_applications 0..1 ------ 0..1 enrollments (accepted conversion)

users 1 -------------------------- * notifications (Laravel database notification)
users 0..1 ----------------------- * audit_logs (actor)
contact_messages
```

### 3.1 Cardinality rules

- One `users` row can link to at most one `teachers` row and at most one `students` row. An internal Guru/Siswa role requires the corresponding link.
- One `teachers` or `students` row can exist with no `users` link.
- One `students` row can have many historical `enrollments`; at most one active enrollment per academic year is the recommended rule, subject to O2-04.
- One `classes` row belongs to exactly one `academic_years` row. A class is never reused across years.
- One `class_teacher_assignments` row has one class and one teacher; a class has at most one active homeroom assignment.
- One `teaching_assignments` row has one teacher, one class, and one subject, scoped to an approved academic period.
- One `admission_applications` row can have many application guardians, document versions, reviews, and status-history events.
- An accepted application can point to at most one converted student and one conversion enrollment. Conversion retries reuse those links.
- Public content never receives a student foreign key in a public projection. Internal achievement recipient links are confidential and are not joined by public queries.

---

## 4. Identity and people entities

### 4.1 `school_profiles` — singleton school identity

| Attribute | Logical contract |
|---|---|
| Purpose | One public/internal school identity and branding configuration. It is not a tenant key. |
| Primary key | `id` (opaque bigint; singleton enforced by application invariant) |
| Foreign keys | Optional `logo_media_id`, `hero_media_id` to `media_assets`; optional `updated_by_user_id` to `users` for attribution only |
| Important fields | `legal_name`, `public_name`, `short_name`, optional `tax/registration_identifier` (only if required), `address_line`, `city`, `postal_code`, `public_phone`, `public_email`, `vision`, `mission`, `about`, optional public social links, publication/version timestamps |
| Unique constraints | Application invariant: at most one profile row. No `school_id`. |
| Indexes | None beyond primary key initially; the row is a singleton. Add only for an actual query that is not a singleton lookup. |
| Status | No tenant status. Use a publication/version marker only if the owner approves an unpublished profile workflow; do not make an inactive profile break the application. |
| Timestamps/retention | `created_at`, `updated_at`; retain historical changes through audit, not a public-content copy. |
| Soft delete | No routine soft delete. A one-school profile is not hard-deleted as an administrative convenience. |
| Classification | Public projection plus confidential internal fields. Public fields require an explicit allowlist. |
| Cardinality | Exactly one logical row to the school; many media references are possible but should be bounded and purposeful. |

### 4.2 `teachers` — Guru person/profile

| Attribute | Logical contract |
|---|---|
| Purpose | Guru identity, employment status, professional profile, private/public presentation metadata, and optional account link. |
| Primary key | `id` |
| Foreign keys | Nullable unique `user_id` → `users`; optional `photo_media_id` → `media_assets` |
| Important fields | `legal_name`, optional `preferred_name`, optional birth place/date, optional gender, optional national identity data, optional address, optional contact phone/email, `employee_number`, `employment_status`, `employment_start_date`, optional `employment_end_date`, optional `position_title`, optional `qualification_summary`, optional `biography`, optional public `directory_slug`, optional publication consent/approval references after O2-10 |
| Unique constraints | Unique `employee_number` after O2-01; unique non-null `user_id`; unique non-null `directory_slug` only if a public directory is approved. Do not add a unique public index for personal name. |
| Candidate indexes | `user_id` unique; `employment_status` plus a stable operational sort key only if directory/admin filters require it; a public projection index must follow the exact published/approved query, not be created blindly. Name search needs a measured MariaDB strategy, not an assumed prefix index. |
| Status | `employment_status`: recommended `active`, `leave`, `inactive`, `retired`. Independent of `users.status`. |
| Timestamps/retention | `created_at`, `updated_at`; retain historical academic/content references. Public photo may be withdrawn/removed under media retention. |
| Soft delete | No routine soft delete. Use employment status/archive policy; do not delete a teacher with assignments or content history. |
| Classification | Entire record confidential; legal/contact/employment data sensitive; only a separately approved public projection may be public. |
| Cardinality | 0..1 linked User; many assignments, classes, and internal achievement attributions over time. |

**Integrity rule:** an account with `role=guru` that is activated must link to this record. A User cannot link to both a teacher and a student. This is a service/policy invariant in the first release, not a nullable polymorphic profile reference.

### 4.3 `students` — Siswa person/profile

| Attribute | Logical contract |
|---|---|
| Purpose | Private student identity, official school number, lifecycle facts, and optional account link. It is not a public directory record. |
| Primary key | `id` |
| Foreign keys | Nullable unique `user_id` → `users`; optional private `photo_media_id` → `media_assets` |
| Important fields | `legal_name`, optional `preferred_name`, optional birth place/date, optional gender, optional national identity data, optional address/contact, `student_number`, optional approved official student identifier, `admission_date`, optional `graduation_date`, optional `leaving_date`, optional confidential `leaving_reason`, `status` |
| Unique constraints | Unique `student_number`; unique non-null `user_id`; any official identifier uniqueness only after O2-01. |
| Candidate indexes | `user_id` unique; `status` plus `student_number` for bounded operational lists if the query is common; a private birth-date index only if a real private lookup requires it and its privacy/write cost is accepted. No public name/photo index. |
| Status | Recommended `active`, `on_leave`, `graduated`, `transferred`, `withdrawn`, `archived`. Independent of `users.status`. |
| Timestamps/retention | `created_at`, `updated_at`; retain enrollments and historical facts. Archive/anonymize only under approved retention. |
| Soft delete | No routine soft delete. Lifecycle status and controlled retention replace destructive deletion. |
| Classification | Confidential/sensitive. No public projection in the first release. |
| Cardinality | 0..1 linked User; many enrollments and guardian links; many internal achievement attributions. |

**Integrity rule:** an account with `role=siswa` that is activated must link to one student. A student can exist without an account, including before controlled PPDB conversion or during an invitation wait.

### 4.4 `guardians` — Wali person record

| Attribute | Logical contract |
|---|---|
| Purpose | Shared person/contact record for one or more students and/or application intake records. Never a User. |
| Primary key | `id` |
| Foreign keys | None required for the person itself. Relationship tables reference it. |
| Important fields | `legal_name`/display name, optional address, phone, email, optional approved national identity data, `status` |
| Unique constraints | No global name/email/phone/national-ID unique constraint. Deduplication is an explicit, audited correction/merge decision. |
| Candidate indexes | Only indexes required for exact, authorized contact/duplicate workflows. A phone/email search index is conditional on O2-07 and must be privacy-reviewed. |
| Status | Recommended `active`, `inactive`, `archived`. |
| Timestamps/retention | `created_at`, `updated_at`; no account fields. |
| Soft delete | No routine soft delete. End relationships and archive/anonymize only under approved retention. |
| Classification | Confidential/sensitive. No public access. |
| Cardinality | Many students through `student_guardians`; may be referenced by many application guardian records. |

### 4.5 `student_guardians` — student/guardian relationship history

| Attribute | Logical contract |
|---|---|
| Purpose | Explicit many-to-many relationship with primary/emergency priority and effective dates. |
| Primary key | `id` |
| Foreign keys | `student_id` → `students`; `guardian_id` → `guardians` |
| Important fields | `relationship_type`, `is_primary_contact`, `is_emergency_contact`, `priority_order`, `starts_on`, optional `ends_on`, optional confidential `relationship_note`, timestamps |
| Unique constraints | No broad unique pair for all time, because history is retained. Application-level/invariant checks prevent overlapping duplicate active links unless the owner approves them. A precisely designed active-relationship constraint is an O2 decision. |
| Candidate indexes | `student_id` plus active/effective-date fields for roster/contact lookup; `guardian_id` plus active/effective-date fields for reverse lookup; `priority_order` only as part of a measured query, not as a standalone blanket index. |
| Status | Derived from `starts_on`/`ends_on`; no duplicate “active” boolean is required unless an owner requires one. |
| Timestamps/retention | `created_at`, `updated_at`; preserve relationship history. |
| Soft delete | No. End-date a relationship; do not delete a relationship that explains historical communication/authority. |
| Classification | Confidential/sensitive link. Sensitive reads are audited. |
| Cardinality | Many-to-many between students and guardians. |

---

## 5. Academic entities

### 5.1 `academic_years`

| Attribute | Logical contract |
|---|---|
| Purpose | Historical school operating year and rollover boundary. |
| Primary key | `id` |
| Foreign keys | None in the single-school model. |
| Important fields | `code`/label, `starts_on`, `ends_on`, `status`, optional description, timestamps |
| Unique constraints | Unique code/label; non-overlapping date rules are an application/integration invariant and must be verified on MariaDB. |
| Candidate indexes | `status` plus `starts_on` only for current-year selection; date-range checks must be measured before adding more indexes. |
| Status | Recommended `draft`, `active`, `closed`; at most one operational active year. |
| Timestamps/retention | `created_at`, `updated_at`; retain closed years indefinitely subject to approved academic retention. |
| Soft delete | No. Close/archive; never delete to roll over. |
| Classification | Internal. |
| Cardinality | One to many terms, classes, enrollments, assignments, and admission periods. |

### 5.2 `terms`

| Attribute | Logical contract |
|---|---|
| Purpose | Ordered semester/term within one academic year. |
| Primary key | `id` |
| Foreign keys | `academic_year_id` → `academic_years` |
| Important fields | `sequence`, `label`, `starts_on`, `ends_on`, `status`, timestamps |
| Unique constraints | Unique `(academic_year_id, sequence)`; unique label within a year if the school requires it. |
| Candidate indexes | FK index; `(academic_year_id, status, starts_on)` only if the current-term query needs it. Do not duplicate a standalone FK index without a query reason. |
| Status | Recommended `draft`, `active`, `closed`. Number of terms is not hard-coded. |
| Timestamps/retention | `created_at`, `updated_at`; historical. |
| Soft delete | No; close terms. |
| Classification | Internal. |
| Cardinality | Many terms per year; one term may scope many teaching assignments. |

### 5.3 `classes` — class/group/rombel

| Attribute | Logical contract |
|---|---|
| Purpose | A year-specific class/section that holds enrollments and assignments. |
| Primary key | `id` |
| Foreign keys | `academic_year_id` → `academic_years`; optional `program_id` only if O2-02 explicitly adds a program entity |
| Important fields | `grade_level` or approved level code, `name`, optional `capacity`, `status`, optional `closed_at`, optional confidential/internal `closure_reason`, timestamps |
| Unique constraints | Unique `(academic_year_id, grade_level, name)` after the naming rule is approved. No class reuse across years. |
| Candidate indexes | `(academic_year_id, status, grade_level, name)` for year/grade rosters; unique business key already supports exact lookup. Avoid a separate index on every component without a query plan. |
| Status | Recommended `planned`, `active`, `closed`. Merge/split/rename is a controlled lifecycle event, not a destructive update. |
| Timestamps/retention | `created_at`, `updated_at`; retain historical class records. |
| Soft delete | No routine soft delete; close class and preserve relationships. |
| Classification | Internal; membership is sensitive. |
| Cardinality | Many classes per year; many enrollments/assignments per class. |

### 5.4 `subjects`

| Attribute | Logical contract |
|---|---|
| Purpose | School subject catalog used by teaching assignments. |
| Primary key | `id` |
| Foreign keys | None in the base single-school model; a program relation is only added if O2-02 requires it. |
| Important fields | stable `code`, `name`, optional `description`, `status`, timestamps |
| Unique constraints | Unique code; name uniqueness only if the school requires it. |
| Candidate indexes | `status` plus code/name only for actual subject selectors; no blanket text index. |
| Status | Recommended `active`, `inactive`. |
| Timestamps/retention | `created_at`, `updated_at`; retain historical subject references. |
| Soft delete | No routine hard delete; inactive preserves assignments. |
| Classification | Internal. |
| Cardinality | Many subjects per class through teaching assignments. |

### 5.5 `class_teacher_assignments` — homeroom/wali kelas

| Attribute | Logical contract |
|---|---|
| Purpose | Time-bounded homeroom relationship, separate from subject teaching. |
| Primary key | `id` |
| Foreign keys | `class_id` → `classes`; `teacher_id` → `teachers` |
| Important fields | `starts_on` or `starts_at`, optional `ends_on`/`ended_at`, `status`, optional internal reason, timestamps |
| Unique constraints | At most one active homeroom assignment per class. A partial unique constraint may not be portable across MariaDB versions; use an explicit service/transaction invariant and a testable database strategy approved during implementation. |
| Candidate indexes | `(teacher_id, status, ends_on)` for a Guru's homeroom scope; `(class_id, status)` for current homeroom lookup. Confirm with the scope query before finalizing. |
| Status | Recommended `planned`, `active`, `ended`, `cancelled`. Effective dates preserve replacement history. |
| Timestamps/retention | `created_at`, `updated_at`; historical. |
| Soft delete | No; end assignment. |
| Classification | Internal authorization/scope data; sensitive when linked to student rosters. |
| Cardinality | Many historical assignments per class/teacher; one active per class. |

### 5.6 `teaching_assignments`

| Attribute | Logical contract |
|---|---|
| Purpose | Time-bounded subject teaching relationship that contributes to Guru scope. |
| Primary key | `id` |
| Foreign keys | `teacher_id` → `teachers`; `class_id` → `classes`; `subject_id` → `subjects`; `academic_year_id` → `academic_years`; optional `term_id` → `terms` only when term-specific scope is approved |
| Important fields | `starts_on`/`starts_at`, optional `ends_on`/`ended_at`, `status`, optional internal replacement reason, timestamps |
| Unique constraints | Exact duplicate/scope uniqueness depends on O2-02's year-versus-term rule. Do not add a nullable-term unique constraint blindly. One logical teacher/class/subject/period assignment must not be duplicated, while co-teaching/replacement must be representable. |
| Candidate indexes | `(teacher_id, status, ends_on)` for current Guru scope; `(class_id, subject_id, status)` for class teaching roster; period columns added only when the query needs them. Verify `EXPLAIN` before final composite order. |
| Status | Recommended `planned`, `active`, `ended`, `cancelled`. |
| Timestamps/retention | `created_at`, `updated_at`; historical. |
| Soft delete | No; end-date assignment. |
| Classification | Internal authorization/scope data. |
| Cardinality | Many assignments per teacher/class/subject over time; many classes per teacher. |

### 5.7 `enrollments`

| Attribute | Logical contract |
|---|---|
| Purpose | Historical student class placement for an academic year; current placement is derived from active status. |
| Primary key | `id` |
| Foreign keys | `student_id` → `students`; `class_id` → `classes`; `academic_year_id` → `academic_years`; optional `source_admission_application_id` → `admission_applications` only after the PPDB link decision |
| Important fields | `status`, `enrolled_on`, optional `effective_from`, optional `effective_to`, optional confidential `transition_reason`, optional `completed_on`, timestamps |
| Unique constraints | Recommended at most one active enrollment per `(student_id, academic_year_id)`; historical rows may repeat the pair. The implementation must choose a MariaDB-safe enforcement strategy after O2-04. |
| Candidate indexes | `(student_id, academic_year_id, status)` for self/admin history; `(class_id, status, student_id)` for class roster and Guru scope; `(academic_year_id, status)` for year reports. |
| Status | Recommended `pending`, `active`, `transferred`, `completed`, `withdrawn`, `on_leave`; exact vocabulary awaits O2-04. |
| Timestamps/retention | `created_at`, `updated_at`; history is retained under academic policy. |
| Soft delete | No routine soft delete; end-date/status transition. |
| Classification | Confidential/sensitive. Membership and history are never public. |
| Cardinality | Many historical enrollments per student; many students per class; one active per student/year recommended. |

---

## 6. Media entities

### 6.1 `media_assets`

| Attribute | Logical contract |
|---|---|
| Purpose | Metadata/reference for an external public or private object. Does not store binary data. |
| Primary key | `id` |
| Foreign keys | `uploader_user_id` nullable → `users`; no generic unconstrained business-owner foreign key |
| Important fields | configured `disk`, random `object_key`, restricted `original_filename`, server-detected `mime_type`, `size_bytes`, `checksum_sha256`, optional image dimensions, `visibility` (`private`, `public`), `processing_status` (`quarantined`, `processing`, `ready`, `failed`, `deleted`), `moderation_status` conditional for publishable media, optional `metadata_sanitized_at`, optional `published_at`, `deleted_at`, timestamps |
| Unique constraints | Unique `(disk, object_key)`; checksum is not automatically unique because identical public images may legitimately be reused. |
| Candidate indexes | `(visibility, processing_status)` for private/public processing; `(uploader_user_id, created_at)` only for authorized uploader history; no public path index. |
| Status | Processing and visibility are separate dimensions. Public visibility is invalid unless business publication and moderation requirements are satisfied. |
| Timestamps/retention | `created_at`, `updated_at`, processing/deletion timestamps; retain tombstone metadata as needed for audit/cleanup. |
| Soft delete | Logical `deleted` state/tombstone, not a blind database row delete while references or holds exist. |
| Classification | Metadata can be confidential/sensitive; public object metadata is public only after approval. |
| Cardinality | One asset has many variants and many explicit domain references. |

**Storage rule:** `object_key` is random and contains no name, NIK, student number, registration number, or other PII. The database never stores a private file blob/base64 body.

### 6.2 `media_variants`

| Attribute | Logical contract |
|---|---|
| Purpose | Derived thumbnail/responsive rendition metadata. |
| Primary key | `id` |
| Foreign keys | `media_asset_id` → `media_assets` |
| Important fields | `disk`, `object_key`, transformation/format identifier, width/height, checksum, `processing_status`, timestamps |
| Unique constraints | Unique source/transformation/output key as appropriate; avoid duplicate processing variants. |
| Candidate indexes | FK index; `(media_asset_id, processing_status)` for worker recovery if measured. |
| Status | `pending`, `processing`, `ready`, `failed`; inherits source privacy. |
| Timestamps/retention | `created_at`, `updated_at`; delete with source/retention workflow. |
| Soft delete | No routine row delete while source/reference/hold exists. |
| Classification | Same or stricter than source asset. |
| Cardinality | Many variants per source. |

### 6.3 Media reference rule

There is no generic `media_references` table in the base blueprint. News, achievements, gallery items, profiles, and application documents use explicit foreign keys. This preserves module ownership and avoids a polymorphic reference that could accidentally make a private document publicly reachable.

Before a media record is removed, the owning module must verify all explicit references, variants, caches, legal holds, and retention requirements. Object-storage deletion and database metadata deletion are separate, retryable steps; they are not assumed to be one atomic database operation.

---

## 7. Public content entities

### 7.1 `news_articles`

| Attribute | Logical contract |
|---|---|
| Purpose | Server-rendered public news/Berita with a controlled publication lifecycle. |
| Primary key | `id` |
| Foreign keys | `author_user_id` → `users`; optional `cover_media_id` → `media_assets` |
| Important fields | `title`, unique `slug`, `excerpt`, sanitized `body`, `status`, optional `published_at`, optional scheduled time, optional SEO title/description, `archived_at`, timestamps |
| Unique constraints | Unique slug; no duplicate public title requirement. |
| Candidate indexes | `(status, published_at, id)` for published list; `author_user_id` plus status only for contributor/admin lists. |
| Status | `draft`, `scheduled`, `published`, `archived`. |
| Timestamps/retention | `created_at`, `updated_at`; archive rather than hard delete. |
| Soft delete | No routine hard delete; archived status preserves URL/history decisions. |
| Classification | Draft confidential; published selected fields public. |
| Cardinality | One author; optional cover; categories/tags are deferred unless separately approved. |

### 7.2 `achievements` — Prestasi

| Attribute | Logical contract |
|---|---|
| Purpose | Public school-level/anonymized achievement presentation with optional internal recipient attribution. |
| Primary key | `id` |
| Foreign keys | Optional `cover_media_id` → `media_assets`; internal author/owner user reference only if the content policy requires it |
| Important fields | `title`, sanitized `description`, `category`, `level`, `achievement_date`, `organizer_name`, `status`, optional `published_at`, `archived_at`, timestamps |
| Unique constraints | No public title uniqueness requirement. Add a slug only if the public detail route is approved. |
| Candidate indexes | `(status, achievement_date, id)` for public list; category/level indexes only for measured public filters. |
| Status | `draft`, `published`, `archived`; public recipient identity is never enabled by this status. |
| Timestamps/retention | `created_at`, `updated_at`; public copy may be anonymized only under approved retention. |
| Soft delete | No routine hard delete; withdraw/archive and retain decision history. |
| Classification | Public only in anonymized/school-level projection; internal recipient data confidential. |
| Cardinality | One achievement may have zero or many internal recipient links if O2-11 selects that option. |

**Public projection rule:** public queries select the achievement's public fields only. They must not eager-load or serialize `achievement_recipients` student/teacher identity.

### 7.3 `achievement_recipients` — conditional internal attribution

This entity is **conditional on O2-11**. If the school needs only school-level achievements, omit the table and do not create a placeholder.

| Attribute | Logical contract |
|---|---|
| Purpose | Internal relationship to a student or teacher recipient without making identity public. |
| Primary key | `id` |
| Foreign keys | Exactly one of nullable `student_id` → `students` or `teacher_id` → `teachers`; explicit FKs, not an unconstrained polymorphic owner |
| Important fields | Internal attribution/approval note, optional consent/publication reference only if separately approved, timestamps |
| Unique constraints | Prevent duplicate same-recipient links for one achievement through an exact unique key; enforce exactly one recipient FK with an application/database strategy reviewed for MariaDB. |
| Candidate indexes | FK indexes for internal recipient/withdrawal lookups; no public query index that includes student identity. |
| Status | Usually no public status; visibility is governed by the parent achievement and privacy policy. |
| Timestamps/retention | `created_at`, `updated_at`; retain/retract according to achievement lifecycle. |
| Soft delete | No destructive deletion of a historical attribution; use withdrawal/retention action. |
| Classification | Confidential/sensitive. |
| Cardinality | Many recipients per achievement; many internal attributions per student/teacher over time. |

### 7.4 `gallery_albums`

| Attribute | Logical contract |
|---|---|
| Purpose | Public/private grouping and publication state for gallery items. |
| Primary key | `id` |
| Foreign keys | Optional `cover_media_id` → `media_assets`; optional created/updated user references if needed for audit attribution |
| Important fields | `title`, optional `description`, `status`, optional `published_at`, `sort_order`, `archived_at`, timestamps |
| Unique constraints | No global title uniqueness; optional unique slug only for approved public routes. |
| Candidate indexes | `(status, sort_order, id)` for public/admin album lists. |
| Status | `draft`, `published`, `archived`; public album never implies every draft/private item is public. |
| Timestamps/retention | `created_at`, `updated_at`; archive/withdraw rather than hard delete. |
| Soft delete | No routine hard delete. |
| Classification | Album metadata public only when published and approved; captions/media may be more restrictive. |
| Cardinality | One album to many items; optional cover asset. |

### 7.5 `gallery_items`

| Attribute | Logical contract |
|---|---|
| Purpose | Ordered, publishable item in a gallery album. |
| Primary key | `id` |
| Foreign keys | `gallery_album_id` → `gallery_albums`; `media_asset_id` → `media_assets` |
| Important fields | `caption`, required accessible `alt_text`/description, optional `event_date`, `sort_order`, `status`, optional `published_at`, timestamps |
| Unique constraints | No duplicate `(album, media_asset)` if the school does not intentionally allow it; decide after O2-10. |
| Candidate indexes | `(gallery_album_id, status, sort_order, id)` for public item pagination; `media_asset_id` for reference/deletion checks. |
| Status | `pending`, `published`, `hidden`, `archived`. A published item still requires public-ready media. |
| Timestamps/retention | `created_at`, `updated_at`; remove/unpublish under media retention. |
| Soft delete | No routine hard delete. |
| Classification | Public only when album/item/media are all public-safe. No student image under A-11. |
| Cardinality | Many items per album; one media asset per item in the base model. |

---

## 8. PPDB entities

### 8.1 `admission_periods`

| Attribute | Logical contract |
|---|---|
| Purpose | Public admission window/information tied to an academic year; operational quota/review rules remain internal. |
| Primary key | `id` |
| Foreign keys | `academic_year_id` → `academic_years` |
| Important fields | `name`/title, sanitized public description/instructions, `opens_at`, `closes_at`, `status`, optional public announcement/reference, timestamps |
| Unique constraints | Non-overlapping/unique period rules depend on the approved calendar; no duplicate public slug unless a public route requires it. |
| Candidate indexes | `(status, opens_at, closes_at)` for public period selection; `academic_year_id` for year linkage. |
| Status | Recommended `draft`, `open`, `closed`, `archived`; public view exposes only approved open/closed information. |
| Timestamps/retention | `created_at`, `updated_at`; archive after cycle according to O2-13. |
| Soft delete | No routine hard delete. |
| Classification | Public period projection plus confidential internal quota/operational fields. |
| Cardinality | One year may have one or more approved periods; many applications per period. |

### 8.2 `admission_period_quotas` — conditional

This entity is included only if O2-02/O2-06 chooses quotas by grade/program/round. If quota is a simple period total, keep the approved value on the period or a dedicated single policy record; do not create an unused table.

| Attribute | Logical contract |
|---|---|
| Purpose | Scoped, effective quota/selection capacity. |
| Primary key | `id` |
| Foreign keys | `admission_period_id` → `admission_periods`; optional approved scope reference such as grade/program only after O2-02 |
| Important fields | scoped capacity, reserved/waitlist capacity if approved, effective dates, status/version, timestamps |
| Unique constraints | One quota per approved period/scope/effective version. |
| Candidate indexes | Period/scope/status for capacity checks; no blind index on every scope column. |
| Status | `draft`, `active`, `closed`, `superseded`. |
| Timestamps/retention | `created_at`, `updated_at`; historical quota changes must be auditable. |
| Soft delete | No; supersede/close. |
| Classification | Internal/ confidential; public only if explicitly published. |
| Cardinality | Many quota rows per period only if scoped rules require them. |

### 8.3 `admission_applications`

| Attribute | Logical contract |
|---|---|
| Purpose | Applicant/application aggregate. It is not a User and not a public directory. |
| Primary key | `id` |
| Foreign keys | `admission_period_id` → `admission_periods`; optional `assigned_reviewer_user_id` → `users`; optional converted `student_id` → `students`; optional converted `enrollment_id` → `enrollments` |
| Important fields | opaque unique `registration_number`, applicant legal/preferred name, birth data as required, normalized contact email/phone or controlled contact reference, consent/notice version and time, source/channel, `status`, optional `revision_requested_at`/deadline, `submitted_at`, optional `decided_at`/`accepted_at`, conversion status/stage/timestamps, timestamps |
| Unique constraints | Unique `registration_number`; no broad natural identity unique key unless O2-07 explicitly approves it. |
| Candidate indexes | `(admission_period_id, status, submitted_at, id)` for queues/reports; `(assigned_reviewer_user_id, status, submitted_at, id)` for reviewer work; duplicate-review indexes only if approved. |
| Status | `draft`, `submitted`, `under_review`, `revision_requested`, `accepted`, `rejected`, `waitlisted`, `withdrawn`, `expired`. Conversion status is separate. |
| Timestamps/retention | `created_at`, `updated_at` plus event timestamps; retain status history and audit. Raw PII/document retention awaits O2-08/O2-13. |
| Soft delete | No routine hard delete; withdraw/archive/expire and retain workflow. |
| Classification | Highly confidential/sensitive. Never public, never cached globally, never logged wholesale. |
| Cardinality | Many guardians/documents/reviews/history rows; at most one converted student and one conversion enrollment. |

**Important:** an `assigned_reviewer_user_id` is an administrator account reference, not a fourth internal role. Applicant/system status actors cannot be falsely represented as a `users` actor.

### 8.4 `application_guardians`

| Attribute | Logical contract |
|---|---|
| Purpose | Guardian/contact data captured for a specific application before canonical student linkage/conversion. |
| Primary key | `id` |
| Foreign keys | `admission_application_id` → `admission_applications`; optional `canonical_guardian_id` → `guardians` after conversion/duplicate resolution |
| Important fields | name, relationship, phone/email, optional address/national identity data, `is_primary_contact`, `is_emergency_contact`, optional priority, timestamps |
| Unique constraints | No global contact uniqueness. At most one active primary per application is a business invariant after intake validation. |
| Candidate indexes | Application lookup; optional exact duplicate-review index only if O2-07 requires and privacy approves. |
| Status | Application data follows application state; no User status. |
| Timestamps/retention | `created_at`, `updated_at`; retained with application policy. |
| Soft delete | No routine hard delete; correct/retract with audit. |
| Classification | Highly confidential/sensitive. |
| Cardinality | Many application guardians per application; many applications may resolve to one canonical guardian. |

### 8.5 `application_documents`

| Attribute | Logical contract |
|---|---|
| Purpose | Private document type/version reference and review state. Binary remains in private media storage. |
| Primary key | `id` |
| Foreign keys | `admission_application_id` → `admission_applications`; `media_asset_id` → `media_assets`; optional `uploaded_by_user_id` or applicant actor reference after applicant access decision |
| Important fields | `document_type`, `version`, `valid_from`/`valid_until` if required, `verification_status`, optional reviewer note, reviewed/reviewer references, timestamps |
| Unique constraints | Version/active-document uniqueness by application/type after O2-08. Do not overwrite an old version when a new version is required. |
| Candidate indexes | Application/type/status; `media_asset_id` for reference/deletion checks. |
| Status | Document verification states must be distinct from media processing states; exact vocabulary awaits O2-08. |
| Timestamps/retention | `created_at`, `updated_at`; retention/hold/deletion events retained. |
| Soft delete | No routine hard delete; supersede/reject/retention-delete according to approved process. |
| Classification | Sensitive private document. |
| Cardinality | Many document versions per application; one private media asset per document version. |

### 8.6 `admission_reviews`

| Attribute | Logical contract |
|---|---|
| Purpose | Reviewer decision history/notes and assignment outcome. The application may also carry the current assigned reviewer. |
| Primary key | `id` |
| Foreign keys | `admission_application_id` → `admission_applications`; `reviewer_user_id` → `users` (administrator) |
| Important fields | decision/recommendation, internal notes, `reviewed_at`, optional assignment/reassignment reference |
| Unique constraints | No blanket unique `(application, reviewer)` if history is required. Current reviewer ownership is enforced on `admission_applications`/service lock. |
| Candidate indexes | `(reviewer_user_id, reviewed_at, id)` for reviewer history; application/status for queue. |
| Status | Internal review outcome; applicant never sees internal notes. |
| Timestamps/retention | `created_at`, `reviewed_at`; finalized review is append-oriented. A mutable draft is a separate future workflow, not an implicit edit to a finalized decision. |
| Soft delete | No; corrections are new events. |
| Classification | Confidential/sensitive. |
| Cardinality | Many reviews/history events per application; one active owner at a time. |

### 8.7 `application_status_histories`

| Attribute | Logical contract |
|---|---|
| Purpose | Append-oriented workflow history. It is required in addition to current application status. |
| Primary key | `id` |
| Foreign keys | `admission_application_id` → `admission_applications`; optional `actor_user_id` → `users` for staff actions; applicant/system actors use safe actor type/reference, not a fake User FK |
| Important fields | `from_status`, `to_status`, actor type/reference, safe reason/code, request/correlation ID, `occurred_at`, optional public-safe message reference |
| Unique constraints | No mutable current-state uniqueness; a transition/version/idempotency key is required by the service after O2-08/O2-09. |
| Candidate indexes | `(admission_application_id, occurred_at, id)` for timeline; actor/time only for approved audit/report queries. |
| Status | Immutable history event. |
| Timestamps/retention | Event time plus `created_at`; no ordinary editing. |
| Soft delete | No. Legal hold/retention policy controls archival/deletion. |
| Classification | Confidential/sensitive workflow history. |
| Cardinality | Many history events per application. |

### 8.8 `admission_announcements` — conditional

This is optional. If public PPDB announcements are ordinary Berita with an approved category, do not create a second content store. If the school requires a PPDB-specific public announcement lifecycle, use a separate entity with explicit publication fields; it must not store applicant PII.

| Attribute | Logical contract |
|---|---|
| Purpose | PPDB-specific public announcement/information distinct from the period's core data. |
| Primary key | `id` |
| Foreign keys | Optional `admission_period_id` → `admission_periods`; optional author `users` reference |
| Important fields | title, slug, sanitized body, status, published/archived timestamps, timestamps |
| Unique constraints | Unique slug if used publicly. |
| Candidate indexes | `(status, published_at, id)`. |
| Status | `draft`, `published`, `archived`. |
| Timestamps/retention | `created_at`, `updated_at`; archive. |
| Soft delete | No routine hard delete. |
| Classification | Public only after publication; no internal notes. |
| Cardinality | Many announcements, optionally per period. |

---

## 9. Platform and communication entities

These entities are not permission tables. They support the project specification and the security/lifecycle boundaries. Their migration timing still depends on the relevant platform decisions.

### 9.1 `contact_messages`

| Attribute | Logical contract |
|---|---|
| Purpose | Public contact submissions and administrator moderation. Not a User registration. |
| Primary key | `id` |
| Foreign keys | Optional assigned administrator `users` reference; no applicant/applicant role |
| Important fields | name, contact email/phone, subject, message, source, moderation status, received/replied timestamps, timestamps |
| Unique constraints | No contact identity uniqueness. Anti-spam/fingerprint retention is conditional on O2-15. |
| Candidate indexes | `(moderation_status, received_at, id)` for queue; assigned reviewer/status if assignment is implemented. |
| Status | `new`, `in_review`, `responded`, `spam`, `archived` (exact list can be refined). |
| Timestamps/retention | `created_at`, `updated_at`; retention awaits O2-13. |
| Soft delete | No routine hard delete; moderation/archive/retention. |
| Classification | Confidential PII; no public read. |
| Cardinality | Many messages; no direct user relationship required. |

### 9.2 `notifications` — Laravel database notification infrastructure

| Attribute | Logical contract |
|---|---|
| Purpose | User-restricted in-app notification/read state. PPDB applicant messages do not imply a User. |
| Primary key | Laravel framework UUID/string key as appropriate |
| Foreign keys | `notifiable_type`/`notifiable_id` only for approved User notifiables; do not attach a Guardian/Applicant as a fake User. |
| Important fields | framework type/data/read timestamp as supported by Laravel; minimize PII in data payload |
| Unique constraints | Framework-managed. |
| Candidate indexes | Recipient/type/read-time indexes as needed for notification center. |
| Status | Read/unread framework state. |
| Timestamps/retention | Framework timestamps plus approved notification retention. |
| Soft delete | No personal-data assumption; retention policy applies. |
| Classification | User-restricted; payload minimized. |
| Cardinality | Many notifications per User. |

### 9.3 `notification_preferences` — conditional

Include only if O2-15 approves user-configurable channels/categories. Do not create a placeholder preference system for deferred channels.

| Attribute | Logical contract |
|---|---|
| Purpose | User preference for approved notification channel/category. |
| Primary key | `id` |
| Foreign keys | `user_id` → `users`; approved channel/category values as controlled fields |
| Important fields | channel, category, enabled, timestamps |
| Unique constraints | Unique `(user_id, channel, category)`. |
| Candidate indexes | User/channel/category lookup; FK index. |
| Status | Enabled/disabled; security notices may be mandatory and cannot be disabled by preference. |
| Timestamps/retention | `created_at`, `updated_at`. |
| Soft delete | No; disable preference. |
| Classification | User-restricted; no secrets. |
| Cardinality | Many preferences per User. |

### 9.4 `audit_logs`

| Attribute | Logical contract |
|---|---|
| Purpose | Append-only security/business accountability, including sensitive reads and private downloads. Not a second copy of domain data. |
| Primary key | `id` or UUID as approved for safe indexing |
| Foreign keys | Optional `actor_user_id` → `users`; target is a controlled type/id reference because one audit table spans modules, with a strict allowlist and redaction. Deactivated actors are not deleted. |
| Important fields | action, result, target type/id, redacted changed-field metadata, occurred_at UTC, correlation/request ID, privacy-safe network/client representation, reason where required, schema/event version |
| Unique constraints | Idempotency/correlation constraints only if approved; no natural uniqueness on target/action. |
| Candidate indexes | `(actor_user_id, occurred_at)`; `(target_type, target_id, occurred_at)`; action/time for approved reports. Avoid indexing full payload. |
| Status | Append-only success/denied/failed event; no ordinary update. |
| Timestamps/retention | `occurred_at`, `created_at`; retention/legal hold per O2-13. |
| Soft delete | No; archive/export/delete only through approved hold-aware process. |
| Classification | Confidential/security data; administrator-only. |
| Cardinality | Many events per actor/target; no business-row cascade. |

### 9.5 `site_settings` — conditional and limited

Use only for settings that are not school profile fields and are not secrets. This entity is not required to implement the domain blueprint and is deferred unless a real requirement exists.

| Attribute | Logical contract |
|---|---|
| Purpose | Small, typed, administrator-managed non-secret settings that do not belong in `school_profiles`. |
| Primary key | `id` |
| Foreign keys | Optional `updated_by_user_id` → `users` for attribution; no secret reference. |
| Important fields | allowlisted `key`, typed `value`, optional `description`, `updated_by_user_id`, timestamps. Prefer a small fixed vocabulary; avoid arbitrary JSON blobs. |
| Unique constraints | Unique `key` only if the setting is intentionally singleton. |
| Candidate indexes | Unique key lookup; no blanket index on values. |
| Status | No default status; enabled/disabled only if the approved setting requires it. |
| Timestamps/retention | `created_at`, `updated_at`; audit changes. |
| Soft delete | No routine hard delete; remove only through a reviewed setting lifecycle. |
| Classification | Administrator-only/internal. Never store passwords, API keys, tokens, or credentials. |
| Cardinality | Small singleton settings; no user-specific secrets. |

### 9.6 Existing framework infrastructure

`cache`, `cache_locks`, `jobs`, `job_batches`, and `failed_jobs` remain framework/operational entities. Redis may replace database drivers in production, but MariaDB remains the business source of truth. No domain data belongs in cache/queue rows.

---

## 10. Index and query-path plan

The table below is a **query-driven candidate list**, not a final migration instruction. Every candidate must be checked against existing indexes, representative MariaDB data, `EXPLAIN`, write cost, and the approved workload. Foreign keys are indexed by their constraints; do not duplicate them with a single-column index unless a distinct query needs it.

| Query path | Candidate index | Rationale/caveat |
|---|---|---|
| Login by normalized email | Existing unique `users.email` | Already present; do not duplicate. |
| Active users by role/status | Existing `users_role_status_index` and `users_status_index` | Already present; add no speculative index. |
| Teacher by linked User | Unique `teachers.user_id` | One-to-one profile lookup. |
| Student by linked User | Unique `students.user_id` | One-to-one profile lookup. |
| Student operational list | Candidate `(students.status, student_number)` | Useful only if status-filtered stable lists are common; verify. |
| Guardian roster for a student | Candidate `student_guardians(student_id, ends_on, is_primary_contact, priority_order)` | Supports active relationship/primary contact lookups; exact order depends on query. |
| Reverse guardian lookup | Candidate `student_guardians(guardian_id, ends_on)` | Supports a guardian's linked students without exposing them publicly. |
| Current academic year | Candidate `academic_years(status, starts_on)` | Supports current-year selection; validate date logic and overlap. |
| Year/class roster | Candidate `classes(academic_year_id, status, grade_level, name)` | Supports year/grade/class selectors; unique business key may overlap, so inspect before adding. |
| Subject selector | Candidate `subjects(status, code, name)` | Supports the approved subject selector; add only after confirming the actual form/query and selectivity. |
| Current homeroom teacher | Candidate `class_teacher_assignments(class_id, status, ends_on)` | Supports class detail and authorization; service enforces one active homeroom. |
| Guru homeroom scope | Candidate `class_teacher_assignments(teacher_id, status, ends_on)` | Supports current class scope. |
| Guru subject scope | Candidate `teaching_assignments(teacher_id, status, ends_on)` | Supports assignment scope; period-specific columns may need a composite variant. |
| Class subject roster | Candidate `teaching_assignments(class_id, subject_id, status)` | Supports class/subject teacher list; verify period scope. |
| Student enrollment history | Candidate `enrollments(student_id, academic_year_id, status)` | Supports self/admin history and current placement. |
| Class roster | Candidate `enrollments(class_id, status, student_id)` | Supports paginated roster and Guru scope; must be joined to valid assignment scope. |
| Public Berita | Candidate `news_articles(status, published_at, id)` | Filters published state and stable newest-first order. |
| Public Prestasi | Candidate `achievements(status, achievement_date, id)` | Filters public state and stable date order. |
| Gallery items | Candidate `gallery_items(gallery_album_id, status, sort_order, id)` | Supports album pagination and stable ordering. |
| Media processing recovery | Candidate `media_assets(visibility, processing_status, created_at)` | Supports queued/retry work; exact index depends on worker query. |
| Media object lookup | Unique `(disk, object_key)` | Prevents duplicate object references; never expose key publicly. |
| PPDB period queue | Candidate `admission_applications(admission_period_id, status, submitted_at, id)` | Supports period/status lists and stable ordering. |
| PPDB reviewer queue | Candidate `admission_applications(assigned_reviewer_user_id, status, submitted_at, id)` | Supports scoped reviewer work; must be paired with Policy checks. |
| PPDB document lookup | Candidate `application_documents(admission_application_id, document_type, verification_status)` | Supports required-document review; exact order follows document workflow. |
| Status timeline | Candidate `application_status_histories(admission_application_id, occurred_at, id)` | Supports immutable timeline. |
| Notification center | Candidate on recipient/read/time as framework requires | Avoid indexing notification payload. |
| Audit actor/target reports | Candidate `(actor_user_id, occurred_at)` and `(target_type, target_id, occurred_at)` | Supports administrator/security reports; retention and access controlled. |

### 10.1 Indexes intentionally not prescribed

- No MariaDB full-text index until search requirements, language behavior, relevance, and query plans are approved.
- No standalone index on every date, status, or name column.
- No index containing raw NIK, email, phone, student number, or registration number merely because it is sensitive; exact authorized lookup design must be reviewed.
- No cache table/Redis key design as a substitute for a durable index.
- No partitioning, sharding, or read-replica key.

---

## 11. Query and eager-loading contract

Future read services/controllers should prepare data outside Blade and apply explicit projections.

### 11.1 Public queries

- Select only public columns from `school_profiles`, published `news_articles`, anonymized `achievements`, published `gallery_items`, and approved public media.
- Public queries must filter publication/processing/visibility state in the database, not only in Blade.
- Do not eager-load `students.user_id`, guardian data, reviewer notes, private media, or full recipient records for public pages.
- Use stable order (`published_at DESC, id DESC`, or approved `sort_order, id`) for deterministic pagination.
- Use bounded page sizes; use keyset/cursor pagination for large public feeds only after measuring offset cost.

### 11.2 Authenticated scoped queries

- Resolve the authenticated User's linked teacher/student profile before applying role scope; missing link denies the operation.
- For a Guru, scope students through current/effective homeroom or teaching assignments and then current enrollment. Do not query all students and filter in PHP.
- For a Siswa, resolve `students.user_id = authenticated user id`; do not accept a student ID from the request as the ownership decision.
- For a PPDB reviewer, scope the application list to the current assigned reviewer and verify the application state before detail/download actions.
- Eager-load only the relationships needed for the page. Use `withCount()`/aggregate queries for counts rather than hydrating full rosters.

### 11.3 Write and workflow queries

- Use Form Requests and explicit DTO/action inputs; do not mass update a model from a request array.
- Lock the application, enrollment, active class, or assignment row inside a transaction before checking state/uniqueness and writing history.
- After commit, dispatch media, email, notification, cache invalidation, and maintenance jobs with opaque IDs/minimal context.
- Retryable jobs must be idempotent; conversion cannot create a second student/enrollment on retry.

---

## 12. Foreign-key delete and retention matrix

The following is the target policy, subject to the retention decision.

| Parent → child relationship | Default delete behavior | Reason |
|---|---|---|
| `users` → nullable `teachers.user_id` / `students.user_id` | `nullOnDelete` only if an approved account-erasure process permits it; otherwise `restrict` and deactivate | Preserve person/history and avoid orphaned identity. |
| `users` → media uploader | `nullOnDelete` after deactivation/approved erasure; otherwise `restrict` | Preserve media provenance where possible, but no dangling security data. |
| `students` → `enrollments` | `restrict` | Historical placement must not disappear. |
| `students` → `student_guardians` | `restrict` | End relationship explicitly; preserve history. |
| `guardians` → `student_guardians` | `restrict` | End/archive guardian explicitly. |
| `classes` → `enrollments` / assignments | `restrict` | Historical class records cannot be deleted casually. |
| `teachers` → assignments | `restrict` | Assignment history remains. |
| `media_assets` → variants | `restrict`/controlled cleanup | Variants must be accounted for before object deletion. |
| `media_assets` → content/document references | `restrict` | Referenced/private media cannot be deleted by a generic cascade. |
| `admission_applications` → guardians/documents/history/reviews | `restrict` | Workflow evidence and private data require retention decision. |
| accepted application → student/enrollment | `restrict` | Prevent conversion links from disappearing silently. |
| `audit_logs` → actor user | nullable/restrict with safe actor snapshot | Audit must survive deactivation. |
| Notifications → User | framework/retention policy | Notification data is minimized; no domain history depends on it. |

No parent/child personal-data relationship should use an unconditional `cascadeOnDelete()` merely to simplify a migration.

---

## 13. Data classification by entity group

| Entity group | Default classification | Public projection allowed? | Shared Redis cache? | Audit expectation |
|---|---|---|---|---|
| `school_profiles` | Public + confidential fields | Yes, allowlisted | Public projection only | Profile/publication changes |
| `teachers` | Confidential/sensitive | Only after O2-10 | Public projection only | Private reads, corrections, employment changes, publication |
| `students` | Confidential/sensitive | No | No | Sensitive reads, corrections, exports, lifecycle/conversion |
| `guardians`, `student_guardians` | Confidential/sensitive | No | No | Sensitive reads, corrections, relationship/priority changes |
| Academic context | Internal; roster/enrollment sensitive | Aggregate only if explicitly safe | Aggregate only | Assignment/enrollment/lifecycle changes; sensitive roster reads |
| Media/document metadata | Public only if approved; otherwise confidential/sensitive | Media-specific | Public safe derivatives/metadata only | Visibility, upload, replacement, download, deletion |
| Berita/Prestasi/Galeri | Draft confidential; public projection allowed | Published safe fields | Public projection | Publish/unpublish/archive/visibility/correction |
| PPDB | Highly confidential/sensitive | Period information only | No | Submission, status, review, document access, conversion, duplicate decision |
| Contact | Confidential PII | No | No | Moderation, sensitive read, export/response |
| Notifications | User-restricted | No | Not in shared public cache | Delivery/security events as policy requires |
| Audit | Confidential/security | No | No | Administrator access and security operations |

---

## 14. Additive migration sequence and dependency gates

This is a design sequence only. The table names below do **not** authorize migration files.

| Order | Future migration group | Tables/operations after approval | Required decisions/tests before file creation |
|---|---|---|---|
| 1 | Identity/person foundation | `school_profiles`, `teachers`, `students`, `guardians`, `student_guardians` | B-05/B-06/B-13/B-14/B-19; O2-01, O2-05, O2-10, O2-11, O2-13, O2-17; factories, role/profile invariants, negative Policies, MariaDB FK/unique tests |
| 2 | Academic foundation | `academic_years`, `terms`, `classes`, `subjects`, `class_teacher_assignments`, `teaching_assignments`, `enrollments` | B-01–B-04; O2-02, O2-04, O2-05; year/term/class/assignment/enrollment lifecycle and scope tests, query-plan review |
| 3 | Media/public content | `media_assets`, `media_variants`, `news_articles`, `achievements`, optional `achievement_recipients`, `gallery_albums`, `gallery_items` | B-13–B-16; O2-08/O-10/O-11/O-12/O-13/O-15; public projection, privacy, upload, disk/visibility, cache invalidation, deletion tests |
| 4 | PPDB | `admission_periods`, optional scoped `admission_period_quotas`, `admission_applications`, `application_guardians`, `application_documents`, `admission_reviews`, `application_status_histories`, optional `admission_announcements` | B-06–B-12; O2-02–O-04, O2-06–O-09, O2-13; state, duplicate, reviewer, document, consent, concurrency, conversion tests |
| 5 | Platform/operations | `contact_messages`, audit, approved notification preferences/delivery, retention/hold metadata as required | B-17–B-20/B-23; O2-13/O2-15/O2-16/O2-18; redaction, notification privacy, queue failure, backup/restore tests |
| 6 | Query/performance hardening | Only evidence-driven indexes or operational metadata | O2-14/O2-18; MariaDB/Redis integration, load test, restore test, documented query plans |

### 14.1 Why PPDB comes after media and academic

PPDB depends on:

- canonical people/guardian records and their duplicate/correction rules;
- academic year/term/class/enrollment capacity and status rules;
- private media/document storage and processing;
- reviewer/audit/notification boundaries.

Creating PPDB first would force placeholder people, academic, quota, or media columns and would make later correction difficult. Media may be implemented in a dedicated increment before public content, but it must be available before real PPDB documents are collected.

### 14.2 Additive deployment requirements for any future group

Before a future migration is written:

1. confirm the decision references and owner sign-off;
2. inspect the actual MariaDB schema and migration status;
3. take a reviewed backup and document lock/rollback/recovery impact;
4. use MariaDB-compatible foreign keys, deliberate delete behavior, and query-backed indexes;
5. provide factories/tests without fixed production credentials or real personal documents;
6. verify no `school_id`, guardian role, applicant role, public student projection, or deferred academic table is introduced accidentally;
7. run MariaDB-backed migration/integration tests, not only SQLite;
8. deploy code and schema in a reviewed order compatible with existing users;
9. do not run destructive reset commands against the existing database.

---

## 15. Required implementation review checklist

### Identity and privacy

- [ ] Existing `users` role/status remain fixed and no new role is added.
- [ ] Guru/Siswa User links are nullable one-to-one and profile existence without an account is supported.
- [ ] Guardian and applicant records cannot authenticate as Users.
- [ ] Employee/student/registration identifiers have owner-approved uniqueness, immutability, masking, and correction rules.
- [ ] No public student projection or identifiable public achievement exists.
- [ ] Public teacher fields are explicitly allowlisted before any directory page.

### Academic and authorization

- [ ] Academic year/term/grade/program rules are approved.
- [ ] Classes are year-specific and historical.
- [ ] Homeroom and teaching assignment are separate, time-bounded relationships.
- [ ] Guru student access is enforced in queries and Policies, not only role middleware.
- [ ] Siswa access is exact User/student ownership.
- [ ] Enrollment/assignment state transitions are transactional and history-preserving.
- [ ] Negative tests cover direct IDs, list scope, historical scope, and exports.

### PPDB

- [ ] Applicant is not a User role or internal dashboard identity.
- [ ] Status state machine, history, reviewer ownership, revision, and concurrency are approved.
- [ ] Registration/status access is opaque, expiring, rate-limited, and separately authorized.
- [ ] Documents are private, versioned, validated/scanned, and audited.
- [ ] Duplicate handling does not expose PII or reject legitimate shared contacts by accident.
- [ ] Conversion is idempotent, transactional, and does not automatically create a User at submission.
- [ ] Account invitation/email failure is retryable and cannot duplicate a student.

### Media/content

- [ ] Public/private disks and provider abstraction are defined.
- [ ] Objects are random-key external files, not database blobs.
- [ ] Visibility, publication, moderation, consent, variants, and deletion are separate controls.
- [ ] Public images have metadata sanitized and no student identity.
- [ ] Public cache invalidation is explicit and private responses are not shared-cached.
- [ ] Berita, Prestasi, and Galeri statuses prevent draft/private content from public queries.

### Platform/operations

- [ ] Audit event catalog and redaction are approved before sensitive data is collected.
- [ ] Retention/legal hold/deletion authority is documented.
- [ ] Redis/cache/queue failure behavior and notification channels are approved.
- [ ] MariaDB and Redis integration tests exist for the relevant release.
- [ ] Workload/SLO and backup RPO/RTO/restore ownership are recorded.

---

## 16. Phase 2 schema handoff

This blueprint is ready to be reviewed alongside the Phase 2 domain decisions. It is **not fully approved** and is not permission to create a migration.

The next safe action is an owner review of the open decisions, followed by an implementation plan for one approved migration group. That plan must preserve the existing identity migration, introduce no `school_id`, keep guardian/applicant authentication out of scope, and prove authorization, privacy, transaction, and MariaDB behavior before the corresponding domain feature is exposed.
