# Sekolah Kita — Phase 2 Domain Decisions

**Date:** 2026-09-24
**Project:** Sekolah Kita
**Document status:** Planning and design only — **not approved and not an authorization to implement**
**Primary sources:** `AGENTS.md`, `PROJECT_SPEC.md`, `docs/AI_PROJECT_ASSESSMENT.md`, `docs/ARCHITECTURE_DECISIONS.md`, `docs/PHASE_0_APPROVAL.md`, and `docs/PHASE_1_IMPLEMENTATION.md`
**Companion blueprint:** [`PHASE_2_SCHEMA_BLUEPRINT.md`](./PHASE_2_SCHEMA_BLUEPRINT.md)
**Owner approval draft:** [`PHASE_2_OWNER_APPROVAL_DRAFT.md`](./PHASE_2_OWNER_APPROVAL_DRAFT.md)

> This document translates the approved architecture and the remaining Phase 0 questions into a proposed first-release domain contract for owner review. It does not claim product, technical, or privacy approval. No migration, model, route, controller, authentication change, seed, or environment change is authorized by this document.

---

## 1. How to read this document

A recommendation in this document is not a silent product decision. The review authority remains the school/product owner, technical owner, and privacy/security reviewer identified in `docs/PHASE_0_APPROVAL.md`.

The following terms are used deliberately:

- **Inherited constraint (A-xx):** a binding Phase 0 decision that remains unchanged.
- **Phase 2 contract (P2-D-xx):** a concrete, implementation-oriented recommendation made here. It is ready for review but becomes a migration gate only after owner approval.
- **Open (O2-xx):** a genuine business, legal, operational, or product choice. The document presents options and a recommended default for discussion; it does not make the final decision.

The schema names, fields, relationships, and indexes below are logical. The companion schema blueprint gives the detailed entity contract. Neither document is a migration specification.

## 2. Current implementation baseline

The implementation inspected before this design confirms the following starting point:

| Area | Current state | Phase 2 consequence |
|---|---|---|
| Application | Laravel 13, PHP 8.5, Blade/Vite/Tailwind, web-only, MariaDB, modular monolith | Keep the approved web-first and single-school boundaries. |
| Identity | `users` has `name`, normalized login `email`, password, verification, `role`, and account `status` | Reuse the existing account model; do not replace it with a shared person table or a generic polymorphic profile. |
| Roles | `administrator`, `guru`, `siswa` | Keep one stable role on the account. Do not add applicant or guardian roles. |
| Account status | `active`, `inactive`, `suspended` | Keep this exact account vocabulary. A person profile has an independent lifecycle status. |
| Authentication | Session login/logout, password reset, verification, active/verified middleware, rate limits, lifecycle service | Do not duplicate authentication data in a person profile. Domain authorization is a later, additional boundary. |
| Domain | No Guru, Siswa, guardian, academic, PPDB, content, or media domain model exists | The following design has no current application implementation to preserve or change. |
| Database | Four existing migrations; the three framework migrations and the additive identity migration are all reported as run | No migration status may change during this planning phase. |
| Test data | One `UserFactory`, no fixed production credentials, and an empty `DatabaseSeeder` | No production/domain seed data is needed for this phase. |
| Authorization | A `UserPolicy` and role/active middleware exist; no domain policy exists | Future Policies must extend, not bypass, the current active/verified identity boundary. |
| Package surface | No domain-specific package is needed for the proposed model | Use native Laravel/Eloquent/filesystem/queue/authorization facilities. Do not change dependencies for this contract. |

The current identity migration's history in `docs/PHASE_1_IMPLEMENTATION.md` says it was pending when that document was written; the live read-only check for this phase reports it as **Ran in batch 2**. This document does not change that state.

---

## 3. First-release scope boundary

### 3.1 Included in the blueprint

The first-release blueprint covers the data needed for:

- one singleton school profile;
- separate Guru, Siswa, and guardian person/profile records;
- a controlled link between an account and a Guru or Siswa profile;
- academic years and terms;
- academic-year-specific classes;
- subjects;
- time-bounded homeroom and teaching assignments;
- historical and active student enrollments;
- public media/private media metadata;
- published Berita, Prestasi, and Galeri;
- PPDB periods, applications, documents, review, and controlled conversion;
- the policy contract for the three existing internal roles and a guest;
- platform audit, notification, queue, and cache boundaries that PPDB depends on.

### 3.2 Deferred

The following are **not** required by the current high-level project specification and are therefore deferred without placeholder schema:

- attendance;
- report cards, grades, gradebooks, and assessment;
- class schedules and timetable generation;
- extracurricular activities;
- public student directories or student face galleries;
- identifiable public student achievements;
- guardian login or a guardian role;
- an applicant role or a general applicant dashboard;
- public unrestricted account registration;
- public API, mobile application, or a third-party integration;
- custom roles, permission tables, delegated administration, or a permission package;
- advanced automated PPDB scoring, ranking, payment, or external testing;
- multi-tenancy, `school_id` domain columns, read replicas, sharding, and multiple business databases.

The first release may add an academic structure because a Guru must have a safe scope and a Siswa needs a historical placement. It must not add attendance or assessment tables merely because those features are foreseeable.

### 3.3 Single-school invariant (P2-D-01)

**Contract:** Every record belongs to the one school. Do not add `school_id` to any domain table, cache key, policy, or queue payload.

The singleton `school_profiles` record is a content/configuration boundary, not a tenant discriminator. If a second independent school becomes a committed requirement, create a new architecture decision and revisit the schema, foreign keys, media paths, policies, caches, and audit model before adding another school.

---

## 4. Canonical identity and person model

### 4.1 The word “person” is conceptual, not a new first-release table (P2-D-02)

A person in this blueprint means a human represented by one of these records:

- `teachers` for a Guru;
- `students` for a Siswa;
- `guardians` for a Wali person.

A generic `people` or `persons` table is not proposed. It would force all three domains to share nullable identity/contact fields and would make privacy classification, deletion, and public projections harder to reason about. The shared **account** is `users`; the domain-specific **person** remains a separate profile.

### 4.2 Canonical relationship graph

```text
users 0..1 ---------------------- 1 teachers
users 0..1 ---------------------- 1 students
teachers 1 ---------------------- 0..1 users
students 1 ---------------------- 0..1 users

students * ---------------------- * guardians
             through student_guardians

users.role = administrator     (no separate administrator/person table)
```

The administrator is an **account role**, not a fourth person profile. An administrator may also be a person in ordinary life, but this application does not need to represent that fact. An account with `role=administrator` does not receive a `teachers` or `students` link merely because the person works or studies at the school.

There is no one-to-many “user has many teacher profiles” or “user has many student profiles.” A user can be linked to at most one Guru profile and at most one Siswa profile, and an internal Guru/Siswa user must be linked to the matching profile. A person can exist without an account; an account cannot be treated as a person record.

### 4.3 Account requirements and provisioning

| Record | Does it require `users`? | Account/provisioning rule |
|---|---:|---|
| Administrator | Yes | Provision through the approved controlled administrator flow. Assign `administrator`; do not create a person profile as a condition of access. |
| Guru | No | Create the Guru profile independently. A Guru account is optional and must be invited/provisioned by an administrator or a later approved staff-provisioning action. |
| Siswa | No | Create the Siswa profile through admission or administrator action. A Siswa account is optional and must be provisioned later through an administrator-controlled flow. |
| Guardian/Wali | No | Store a person record only. Never provision a User, add a guardian role, or create a guardian login. |
| PPDB applicant | No internal User account | Store applicant identity/contact and application data in the admission domain. Do not create `administrator`, `guru`, or `siswa` at submission. |
| Public visitor | No | No account is created merely by viewing public content. |

**P2-D-03 — account creation is a separate controlled operation.** Profile creation and account creation may be performed in one administrator workflow, but they remain separate domain facts. The account service must not infer a new account from a person row. PPDB acceptance may request a later account invitation, but a failed email delivery must not roll back a successfully committed student conversion.

**P2-D-04 — role/profile consistency.** Before activating a `guru` account, it must link to one Guru profile; before activating a `siswa` account, it must link to one Siswa profile. A user cannot be linked to both profile types. The future implementation should enforce this in the provisioning service, policies, and tests; the database should not rely on an unconstrained `profile_type/profile_id` pair to express it.

### 4.4 Identifier contract

| Identifier | Immutable? | Editable? | Uniqueness and privacy rule |
|---|---|---|---|
| `users.id` | Yes, as an internal technical key | Never through normal UI | Permanent historical reference; do not display publicly as a person identifier. |
| `users.email` | No, but changing it is a controlled identity operation | Only through a pending-change and verification flow | Existing global unique email remains the account uniqueness boundary. Normalize to lowercase; do not silently create an email alias. A new address must be verified before replacing the old login address. |
| `users.role` | No, but changing it is privileged and audited | Administrator-only lifecycle action | One of the three existing enum values. It is not a person field and is not mass assignable. |
| Guru `employee_number` | Recommended immutable once issued | No ordinary edit | Unique business identifier for the school. A correction requires an audited correction action and a collision check; do not reuse a retired number casually. Whether the school calls it NIP or another code is an owner decision. |
| Siswa `student_number` | Yes, once allocated | No ordinary edit | Unique business identifier. It is not a public URL, search key, or login credential. A correction requires an explicit audited action; never recycle a number that can be confused with historical data. |
| Official student identifier such as NISN | Conditional | Only through an approved correction process | Collect/store only if the school needs it. If treated as an official unique identifier, its uniqueness and masking rules must be explicitly approved; it is sensitive. |
| PPDB `registration_number` | Yes | No | Random, opaque, unique application reference. It is not a student number, User ID, email, or sequential database ID. |
| Person name | No | Correction is audited and permissioned | Legal name is a private domain fact. The `users.name` display label is not automatically the legal name. |
| Birth date/place, national ID, and similar identity facts | No, but corrections are high-risk | Administrator-only controlled correction | Sensitive; never public; never a default search key; correct with a reason and audit. |
| Guardian relationship | Not a global identifier | Controlled update | Relationship changes end the old link and create a new effective link; do not rewrite history. |
| Class/assignment IDs | Yes | Never | Internal authorization and history keys; do not expose raw IDs in public URLs. |

**P2-D-05 — stable public routes use opaque slugs only after a public page is approved.** A public teacher or article route may use a non-identifying, collision-checked slug. It must not use NIP, student number, registration number, NIK, raw database ID, email, or a storage path.

### 4.5 Account status is not person status (P2-D-06)

Keep the existing `users.status` vocabulary unchanged: `active`, `inactive`, `suspended`.

Do not add `pending_verification` to `users`. Email verification is represented by the existing `email_verified_at`; invitation state belongs to a future invitation workflow, if approved.

Person records need an independent domain status because an account can be disabled while the person remains a historical teacher/student, or a person can exist before an account is provisioned.

Recommended person status vocabularies are defined in Sections 5–7. The core rule is:

- **Deactivate an account** to remove login access. In the current identity contract, the `deactivate` lifecycle action results in `users.status=inactive`; it does not create a new person status. Sessions and remember tokens are revoked according to the existing lifecycle service.
- **Deactivate or archive a person** to stop new operational use. Academic, content, PPDB, notification, and audit references remain.
- **Hard delete** is never a substitute for either operation and requires an explicit retention/erasure decision.

### 4.6 Privacy boundaries for identity

- `users` contains authentication and login-delivery data, not legal identity facts or academic placement.
- A Guru/Siswa profile may contain legal identity and business contact data; it must not contain a password, reset token, role grant, or session payload. If an approved national identifier requires exact lookup, the implementation must use a reviewed encryption/tokenization or keyed-digest design; it must not be placed in ordinary logs, URLs, or an unneeded plaintext search index.
- Guardian identity and contact data belongs only to the person/admission domain.
- A public projection must select public-safe columns at query time. Hiding sensitive columns in Blade after loading a full model is not a sufficient boundary.
- Internal UUIDs or numeric IDs may be used for joins and audit, but public URLs, filenames, cache keys, and logs should not contain raw identity identifiers.
- A user email is not automatically copied into a person profile, and a person contact email is not automatically usable for login.

---

## 5. Guru domain contract

### 5.1 Minimum Guru record

The `teachers` record is a person/profile record. It is not a User account and does not own role or password data.

| Field group | Proposed fields | Rule and privacy |
|---|---|---|
| Relationship | `user_id` nullable, unique | At most one linked User. A Guru profile may exist with no account. If linked, the account must be `guru`. |
| Identity | `legal_name`, optional `preferred_name`, optional birth place/date, optional gender, optional national identity data, optional address, business contact phone/email | Minimum necessary collection. Legal identity and contact data are confidential/sensitive, not public by default. |
| School identifier | `employee_number` | Unique; immutable after issue once the identifier format is approved. Do not assume every teacher has a civil-service NIP. |
| Employment | `employment_status` (`active`, `leave`, `inactive`, `retired`), `employment_start_date`, optional `employment_end_date` | Independent of User status. A suspended account is not automatically a retired Guru. |
| Professional | `position_title`, optional `qualification_summary`, optional `biography` | Only the approved public allowlist may be projected publicly. A subject is not a single free-text field; it is represented by teaching assignments when applicable. |
| Presentation | `photo_media_id` optional, publication consent/approval metadata pending B-13 | Photo is private by default. A future public directory requires a separate publication decision. |
| Classification | Domain classification plus field-level privacy flags in the application contract | Mixed record: a public projection may be possible while the source record remains confidential. |

Not in the minimum Guru record: grades, attendance permissions, a duplicate User password, a global “access all students” flag, or a guardian relationship. Those would either belong to another module or violate least privilege.

### 5.2 Employment and account lifecycle

A Guru may be created before employment starts or before a login is issued. A Guru employment status of `leave` or `inactive` does not erase assignments or historical enrollments. A Guru whose account is `suspended` may still be a person referenced by historical content or assignments; the account cannot access them through login.

If a Guru leaves, the workflow must close or end-dated current assignments before or with employment closure. It must not delete the Guru, historical classes, content authorship, or audit references.

### 5.3 Academic relationships

```text
teachers 1 ---- 0..* class_teacher_assignments * ---- 1 classes
teachers 1 ---- 0..* teaching_assignments       * ---- 1 classes
classes  * ---- * subjects (through teaching_assignments)
```

- A homeroom relationship is represented separately from subject teaching. A Guru is not automatically a homeroom teacher because they teach a subject.
- A homeroom assignment is year-scoped, with effective start/end dates and an explicit status. There should be at most one active homeroom assignment per class; a service and concurrency tests must enforce that invariant.
- A teaching assignment identifies `teacher_id`, `class_id`, `subject_id`, and the approved academic period (year, or term when the school requires term-specific scope). Effective dates preserve replacement and historical access.
- A Guru may have many assignments over time, co-teach a class, or be replaced temporarily only if the academic owner approves that rule. The base model does not assume one subject per Guru.
- Historical assignments are not overwritten when a Guru, class, subject teacher, or school year changes.

### 5.4 Explicit Guru authorization scope

**P2-D-07 — a Guru never inherits unrestricted student access from `role=guru`.**

For a student `S`, an active `guru` user `G` may access only the fields necessary for a task when there is a valid, time-bounded relationship:

1. `G` has an active/effective homeroom assignment for the class containing `S`; or
2. `G` has an active/effective teaching assignment for the class containing `S`.

The future policy and query layer must evaluate the same scope for list, detail, export, and download actions. A valid relationship to one class does not grant access to another class, a historical class, a guardian record, a PPDB application, or another teacher's private fields. Historical access, if ever approved, must be a separate, explicit ability rather than an accidental consequence of an old assignment row.

The minimum student fields visible to an assigned Guru must be approved (for example, name, student number, class relationship, and contact only where operationally necessary). Student photo, birth data, NIK, address, guardian details, and private documents are not automatically included. The field allowlist is **O2-05**.

### 5.5 Public Guru projection

Until B-13 is approved, no public Guru directory or public teacher photo is enabled. The proposed safe projection is a separate query/read model containing only an approved subset such as display name, approved position, approved subject/assignment label, approved biography, and an explicitly approved public photo.

NIP/employee number, employment status, personal phone/email, address, birth data, account ID/email, assignment history, and private qualifications are excluded by default. Public indexing/searchability and the choice of a public slug are owner decisions.

---

## 6. Siswa domain contract

### 6.1 Minimum Siswa record

The `students` record is a private person/profile record. It is not a public directory row and is not a User account.

| Field group | Proposed fields | Rule and privacy |
|---|---|---|
| Relationship | `user_id` nullable, unique | At most one linked User. A Siswa profile can exist without an account. A linked account must have `role=siswa`. |
| Identity | `legal_name`, optional `preferred_name`, optional birth place/date, optional gender, optional national identity data, optional address, contact phone/email | Sensitive. Collect only for approved academic/admission purposes. Never public. |
| Student identifiers | `student_number`; optional official identifier such as NISN if required | Student number is unique and immutable once allocated. Official identifier handling is conditional on B-24/O2-01. |
| Enrollment facts | `admission_date`, optional `graduation_date`, optional `leaving_date`, optional `leaving_reason` | These are domain facts, not a public profile. A reason may be confidential. |
| Lifecycle | `status`: `active`, `on_leave`, `graduated`, `transferred`, `withdrawn`, `archived` | Independent of User status. `archived` is a retention state, not a normal active state. |
| Presentation | optional private `photo_media_id` | Never public in the first release. Store privately with access controls. |
| Classification | Highest default field classification is sensitive; entire record is confidential/sensitive | No public projection is planned. |

The current placement is not a mutable `class_id` on `students`. It is derived from the applicable active enrollment. A Siswa can have many historical enrollment rows and one current operational placement under the recommended enrollment rules.

### 6.2 Student lifecycle

- `active`: an enrolled/operational student with an active academic enrollment.
- `on_leave`: temporarily away; the student and history remain, and the current placement is not treated as ordinary active access.
- `graduated`: completed the approved academic program; preserve the final enrollment and completion date.
- `transferred`: left the school; preserve the last enrollment and effective leaving date.
- `withdrawn`: enrollment/application was withdrawn before completion; preserve the reason/history.
- `archived`: retained for an approved historical/legal period but removed from normal operational selection; not a routine status for a currently active student.

Account deactivation does not automatically change a student to `inactive` or `archived`. A student with a suspended account can remain an active person for school records, but cannot log in. Conversely, a person can exist with no account.

### 6.3 Guardians and academic placement

A Siswa can have zero or more guardian links. Once a student is operationally enrolled, the approved intake policy determines whether at least one guardian link is required. Guardian information is never exposed to another student.

A Siswa can have many enrollment records over time. The current class is derived from the active enrollment for the applicable academic year, not copied into the profile. Class changes end-date the old enrollment and create a new one; they do not overwrite history.

### 6.4 Public student boundary

There is **no public student directory, student search, student photo gallery, or identifiable public student achievement** in the first release. Even a public achievement or gallery description must not reveal a student's name, photo, class, student number, quotation, national identifier, or a combination of details that makes the student identifiable.

A future change that would identify a student publicly is a new privacy decision and would change inherited decision A-11. It cannot be introduced as a small UI toggle.

---

## 7. Guardian/Wali domain contract

### 7.1 Person-only model

A guardian is a `guardians` record, not a User and not an internal role. The same guardian record may be related to more than one student, but relationship rows are explicit and historical.

Minimum proposed fields:

- legal/display name;
- relationship to the student (`father`, `mother`, `grandfather`, `grandmother`, `sibling`, `uncle`, `aunt`, `other`, or an approved school vocabulary);
- phone and email as needed for school communication;
- address only when operationally required;
- optional national identity data only when an approved PPDB or legal process requires it;
- lifecycle `status`: `active`, `inactive`, `archived`;
- timestamps and an audit-safe correction history.

The relationship vocabulary is data, not authorization. Calling a person a guardian never grants that person access to a dashboard, student record, or document.

### 7.2 Student relationship and primary/emergency rules

`student_guardians` is the relationship/history table. Proposed rules:

- A guardian can be linked to many students; a student can have many guardians.
- A relationship has `relationship_type`, `is_primary_contact` (or a clearly named primary-contact equivalent), `is_emergency_contact`, `priority_order`, and effective start/end dates.
- For an operationally complete student, exactly one active primary contact is recommended. If the school must support shared custody, exactly one primary can still be selected for routine school communication, while multiple emergency contacts remain allowed.
- Primary and emergency are not interchangeable. A primary contact may be an emergency contact; a lower-priority emergency contact does not automatically become the primary contact.
- Priority is meaningful only within a student's active relationship set and must have a deterministic tie-breaker.
- A student may temporarily have no primary contact during an incomplete admission workflow, but the system must surface that as an operational exception rather than silently choosing an arbitrary person.
- Ending a relationship preserves the row and dates. Reassigning a primary contact is a controlled change, not an overwrite.

For PPDB, the applicant's guardian data is initially owned by the application. Conversion creates or links a canonical guardian record only after duplicate resolution; it must not create a second guardian merely because an email or name matches.

### 7.3 Privacy and future login boundary

Guardian data is confidential/sensitive. Administrators manage it; a Guru receives it only if a separately approved assignment requires a specific field; a Siswa receives only explicitly school-approved information about their own linked guardian, if any; guests receive none.

A future guardian login is a separate identity/privacy decision. It would require a new person-to-account policy, invitation/verification design, scoped access, MFA or recovery decisions, and privacy review. **No guardian role, `guardian_user_id`, or guardian authentication placeholder belongs in this first-release schema.**

---

## 8. Academic domain contract

### 8.1 Academic year

`academic_years` represents the school's operating year, with a stable label/code, start date, end date, status, and timestamps. Recommended lifecycle: `draft`, `active`, `closed`.

- The label is a business identifier, not a free-form replacement for dates.
- Start/end dates must be valid and non-overlapping according to the school's rollover rule.
- At most one current operational year is recommended. The implementation must enforce that invariant with an explicit service/transaction rather than relying on two conflicting “current” flags.
- A closed year remains readable and is never deleted merely because a new year opens.
- Rollover creates a new year and new classes/enrollment records; it does not mutate last year's records to point at the new year.

The number of terms and the supported grade levels remain owner decisions in B-01/O2-02, but the data contract is calendar-neutral: one year contains one or more ordered terms.

### 8.2 Term/semester

`terms` belongs to one academic year and has a stable sequence/label, start/end dates, status, and timestamps. The base contract does not hard-code exactly two semesters; it permits the number of terms the school actually uses.

- Terms are year-scoped and ordered by a numeric sequence plus a stable ID tie-breaker.
- A term may be `draft`, `active`, or `closed`; dates and status must agree with the school's rollover policy.
- A term is historical context, not a replacement for the academic year.
- Term-specific teaching access is supported only when the owner approves that scope; otherwise an assignment is valid for the whole academic year.

### 8.3 Grade/level and class/group/rombel

The minimum class model is an **academic-year-specific class** with:

- `academic_year_id`;
- `grade_level` (or an approved school level code);
- a class name/section label;
- optional capacity;
- lifecycle status `planned`, `active`, `closed`;
- optional close date/reason;
- timestamps.

A class is not reusable across academic years. A class in a new year is a new record, even if it has the same name. This makes historical enrollment and authorization unambiguous.

The exact grade vocabulary, whether grade levels are numeric or coded, and whether school programs/strands are supported are **O2-02**. The base blueprint does not create a speculative program/grade catalog. If programs are required, they must be explicitly modeled and linked to the relevant academic/admission scope before a migration is created.

Class name/grade is not a public student identifier. Class membership is authorization-sensitive.

Closing, merging, splitting, or renaming a class must not destroy history:

- close the old class and end-date its current relationships;
- create new class records for a replacement year or split;
- keep old enrollments and assignments attached to the old class;
- use an audited reason for a business-significant change;
- never move a historical student or teacher by changing only a display name.

Whether a dedicated class-successor table is necessary is an owner decision; it is not added speculatively.

### 8.4 Subject

`subjects` is a school subject catalog with a stable code, name, description, active status, and timestamps. It is not a grade or schedule table. A Guru's teaching subjects are derived from time-bounded teaching assignments, not copied into a comma-separated profile field.

Whether subject codes are global to the school or scoped by academic year/program is an owner decision. The base recommendation is school-wide stable codes with no destructive rename; any change is audited.

### 8.5 Homeroom teacher and teaching assignment

These are deliberately different relationships:

| Relationship | Purpose | Scope/history |
|---|---|---|
| `class_teacher_assignments` | Identifies the homeroom/wali kelas for a class | Year-scoped, effective start/end, one active homeroom per class. |
| `teaching_assignments` | Identifies a Guru teaching a subject in a class | Year- or approved term-scoped, effective start/end, subject required. |

A class can have no homeroom teacher while it is planned. Once the owner requires one, the operational rule should prevent activation without a valid assignment, but the schema must still represent the planning gap. A Guru may have multiple assignments and a class may have multiple subject teachers. A replacement ends the old assignment and creates a new one.

### 8.6 Student enrollment

`enrollments` is a historical fact linking a student to a class for an academic year. Proposed lifecycle:

- `pending`: a proposed/allocated placement that is not yet active;
- `active`: the student's current class placement for that academic year;
- `transferred`: ended because the student changed class;
- `completed`: completed the year/period as defined by the school;
- `withdrawn`: left before completion;
- `on_leave`: temporarily not participating while history remains.

The exact state names and whether completion is per year or per term are **O2-02/O2-04**. The model must support at least one active enrollment per academic year, multiple historical enrollments, a class change, withdrawal, leave, and graduation without destructive updates.

Recommended rules for discussion:

1. A student has at most one active enrollment per academic year; multiple historical rows are allowed.
2. A class transfer ends the old enrollment with an effective end date/reason and creates a new active row in a transaction.
3. A school-year rollover creates a new enrollment; it never changes the prior year row.
4. Withdrawal, graduation, and leave end or suspend the placement using explicit status/effective dates.
5. Only an authorized administrator or an approved academic action can create, change, or end an enrollment.
6. Enrollment history is not deleted when a student leaves or an account is deactivated.

Whether a term can have a separate active placement is an open academic policy. The recommended base rule is one active class per academic year, with term information attached to academic assignments/terms rather than duplicating student class membership.

### 8.7 School-year transitions and concurrency

Opening a new year is a workflow, not a bulk destructive update. It should:

- create/validate the academic year and terms;
- create year-specific classes;
- copy only approved configuration templates;
- create new assignments and enrollments explicitly;
- retain prior-year records and close them with effective dates;
- use transactions and row locks for class capacity, active homeroom, and enrollment uniqueness;
- be restartable/observable if implemented as a batch or job.

No automatic rollover behavior is authorized until grade, term, promotion, and completion rules are approved.

### 8.8 Deferred academic features

No attendance, grade, report card, schedule, extracurricular, gradebook, or assessment table is included. These require a separate requirements decision, not merely a nullable column on `classes` or `enrollments`.

---

## 9. PPDB domain contract

### 9.1 Applicant is an admission-domain person, not an internal role (P2-D-08)

An applicant has an `admission_applications` record and related applicant/guardian/document data. The applicant does **not** receive a `users` row, an internal role, a dashboard, or unrestricted account merely by submitting an application.

The current fixed roles remain exactly `administrator`, `guru`, and `siswa`. No `applicant` value may be added to `UserRole`.

The application may be associated with a future `student_id` after controlled conversion, but the application must remain the authoritative record of what was submitted, by whom, at what time, and under which consent/version.

### 9.2 Minimum PPDB records

| Entity | Purpose and minimum contract |
|---|---|
| `admission_periods` | Publicly visible admission information tied to an academic year, opening/closing timestamps, status, public description, and operational quota information. It is not an internal User. |
| `admission_applications` | Opaque registration number, period, applicant identity/contact, consent capture, source, current status, assigned reviewer, submitted/decision timestamps, and conversion link/state. Sensitive; never public. |
| `application_guardians` | Guardian identity/contact/relationship for this application, with primary/emergency metadata if the school requires it. Sensitive; not a User. |
| `application_documents` | Document type/version, private media reference, processing/verification state, and review metadata. Never a public URL. |
| `admission_reviews` | Reviewer ownership/decision history and internal notes. Only authorized administrators/reviewers may read internal notes. |
| `application_status_histories` | Append-oriented transition history with prior/new status, actor type/ID where safe, reason, and timestamp. Applicant/system actions are not falsely attributed to a `users` row. |
| `admission_announcements` (optional) | Separate public PPDB announcement content only if the school needs a content type distinct from admission-period information. Do not duplicate Berita. |

If quotas vary by grade/program, use an explicit quota entity with its scope and effective dates rather than one ambiguous integer on the period. If quotas are only a period total, no extra table is necessary. The owner must decide before a PPDB migration.

### 9.3 Registration number and applicant access

**P2-D-09 — use an opaque registration number plus a separate, expiring applicant-status mechanism.** The registration number alone must not grant access to an application or document. The recommended default for discussion is:

- generate a cryptographically random, non-sequential registration number;
- keep it unique and immutable;
- do not include it in public page URLs, logs, filenames, or Redis keys;
- use a short-lived, single-purpose signed status link tied to the verified contact channel, with rate limiting and generic responses;
- let the applicant view only a deliberately limited projection (status, requested correction, and public next step), never reviewer notes, internal scores, staff identity, private files, or other applicants;
- do not create a User or an applicant session with internal permissions.

Whether the status channel is email link, verified phone link, or a future limited applicant identity is **O2-03**. No guardian/applicant authentication is designed here beyond that decision.

### 9.4 Application status state machine

The following is the recommended state contract for owner review. It is deliberately explicit rather than a mutable `status` field alone.

| State | Meaning | Allowed next states | Actor/guardrail |
|---|---|---|---|
| `draft` | Applicant or authorized staff is preparing an incomplete application. | `submitted`, `withdrawn`, `expired` | Only the applicant-status mechanism or authorized staff may act. Drafts cannot be accepted. |
| `submitted` | Required applicant data and consent were captured; validation/review has not completed. | `under_review`, `revision_requested`, `withdrawn`, `expired` | Staff can validate; applicant cannot silently edit after submission. |
| `under_review` | A single assigned administrator owns the review at a time. | `revision_requested`, `accepted`, `rejected`, `waitlisted` | Row lock prevents concurrent decisions. Reviewer scope is checked for every read/write. |
| `revision_requested` | Staff requested a correction; only the requested fields/documents are editable. | `submitted`, `withdrawn`, `expired` | Applicant status message is sanitized; internal reason is not leaked. Revision deadline is explicit. |
| `accepted` | A final acceptance decision is recorded with an acceptance date. | Conversion is a separate operation; no silent return to review | Conversion can be pending, failed, or completed without changing the decision history. |
| `rejected` | Final rejection decision. | `withdrawn` only if an approved administrative correction is needed; otherwise terminal | No new application edit in this record. A new application requires a new period/process. |
| `waitlisted` | Applicant remains eligible but is not currently accepted. | `under_review`, `accepted`, `rejected`, `withdrawn`, `expired` | Any promotion is a new decision/history event. |
| `withdrawn` | Applicant or authorized staff ended the application. | Terminal | Preserve reason and history. |
| `expired` | The application missed the approved deadline/expiry rule. | Terminal unless an administrator records an explicit, audited extension/reopen action | Do not silently revive a draft. |

`accepted` is not the same as `converted`. A student/account/enrollment failure after acceptance must be visible as an operational conversion state and retried safely; it must not rewrite the acceptance decision as if it never happened.

### 9.5 Review ownership and concurrency

**P2-D-10 — one active review owner at a time in the first release.** The application has an assigned administrator/reviewer reference. A reviewer may open only applications within that assignment. An administrator can reassign a reviewer, but the reassignment is a locked, audited event.

- Two administrators cannot decide the same application concurrently; the service locks the application row and verifies the expected current state/version.
- The reviewer identity is an administrator `users` reference, not a PPDB role.
- Internal notes and reviewer identity are not applicant-visible.
- A rejected/failed/denied action is recorded safely without exposing the target's sensitive data to the applicant.
- A panel, multiple simultaneous reviewers, scoring, ranking, and conflict-of-interest workflow are **O2-06** and are not assumed.

### 9.6 Submitted data, consent, and duplicate prevention

The application must store the data submitted at each relevant point, the consent/notice version accepted, and the time. Do not overwrite the historical record of what was submitted when a correction is made.

Recommended duplicate controls are layered rather than a single unsafe natural-key unique constraint:

1. Rate-limit by privacy-safe IP/device/session/contact digest.
2. Detect likely duplicates using normalized contact plus approved identity signals inside an open period; do not expose why a match was found.
3. Put a likely duplicate into an administrator review/merge decision rather than silently deleting or rejecting it.
4. Keep the opaque registration number unique and immutable.
5. Do not make a shared parent email, a common phone, or an optional national ID globally unique unless the school has explicitly approved that rule and a lawful correction process. Legitimate siblings/guardians may share contact data.
6. Never put raw identity data in a Redis key, URL, filename, ordinary log, or public cache.

The exact signal set, whether a hard uniqueness constraint is required, and the review/merge procedure are **O2-04** and **O2-07**.

### 9.7 Documents

Documents are private media references, never raw blobs in `admission_applications` and never public filenames. Required document types, formats, maximum sizes, versions, expiry, malware scanning, and retention are **O2-08**.

The media contract in Section 11 applies immediately: private disk, server-side content validation, randomized key, processing/quarantine state, and short-lived controlled access. A failed or rejected scan must not leave a usable reference.

### 9.8 Acceptance and controlled conversion

**P2-D-11 — conversion is a separate, transactional domain action, not a side effect of changing a label.**

A safe conversion action must:

1. lock the accepted application and verify it is still accepted, not withdrawn, and belongs to an open/valid period;
2. verify consent, required data, quota, class capacity, and target academic year;
3. resolve or link the canonical student profile without creating a duplicate;
4. create/link the student's guardian relationships from the application data, preserving provenance;
5. allocate one student number and create the approved enrollment;
6. record the application-to-student/enrollment conversion link and idempotency result;
7. write the status/decision/audit history;
8. commit the durable conversion facts;
9. request an optional, separately controlled `siswa` account invitation after commit;
10. queue notifications/media/cleanup side effects after commit.

The transaction must not depend on email delivery, a CDN response, or a queue worker succeeding. If account invitation or notification fails, the student conversion remains traceable and an operator can retry safely. Retrying the action must return the existing student/enrollment rather than create another one.

Whether acceptance immediately converts, waits for applicant confirmation, or waits for an administrator placement deadline is **O2-09**. The recommended default is a controlled conversion action after acceptance, with a visible pending state; it is not an automatic User creation on submission.

---

## 10. Public website data contract

### 10.1 Access-level definitions

- **Public:** an explicitly allowlisted field on an explicitly published/approved record, readable without login.
- **Authenticated-only:** requires an active account and ownership/scope; authentication alone is not enough.
- **Role-restricted:** requires a fixed role and an ownership, assignment, reviewer, or other resource scope.
- **Administrator-only:** requires an active, verified administrator policy and usually an audit event.
- **Private/sensitive:** stored on a private disk or in a sensitive domain projection; never rendered as a public field.

### 10.2 Minimum domain visibility matrix

| Future domain | Public | Authenticated-only | Role-restricted | Administrator-only | Private/sensitive rule |
|---|---|---|---|---|---|
| School profile | Approved school name, public description, approved address/contact, vision/mission, public logo/hero, public social links | None required for the public projection | None for public fields | Internal school fields, unpublished changes, operational settings | Do not expose private operational notes, credentials, or internal-only contact. |
| Guru | Only explicitly approved professional projection and published photo, if B-13/B-15 approve it | Own full profile/account fields | Assigned class/subject context; approved teacher fields | All teacher records, employment/admin fields, corrections, publication approval | NIP/employee number, personal contact, birth data, address, private photo, account data are excluded publicly. |
| Siswa | **None in the initial release** | Own account/profile, own academic data | Assigned Guru sees minimum necessary fields; no cross-student access | Full student/admission records, sensitive reads/exports | No public student name, photo, number, class, achievement recipient, or document. |
| Guardian | **None** | None by default; a Siswa may see only explicitly approved linked contact information if approved | Guru only for a specifically approved assignment/field | Full guardian records, corrections, retention, sensitive reads | Relationship, contact, address, identity data are confidential/sensitive. |
| Prestasi | Anonymized school-level achievement title, category, level, date, sanitized description, organizer, and approved non-identifying media | None for public record | Internal recipients/drafts are role-restricted | Manage, approve, publish, correct, archive, and inspect internal recipient data | No public student recipient identity, photo, number, class, quotation, or identifying combination. |
| Berita | Published title, slug, excerpt, sanitized body, publication date, approved cover | None for public record | Drafts/scheduled content and contributor work | Create/edit/publish/archive and moderation | Drafts, unpublished revisions, author private contact, and unpublished media are not public. |
| Galeri | Published album/item captions, approved public media, event date, accessibility alt text | Private/unlisted albums only if explicitly implemented | Contributor upload/edit scope if granted | Album/item/media management, moderation, publication and deletion | No student face/identity in a public album without a new privacy decision; originals/metadata may be private even when a derivative is public. |
| PPDB | Published period information, requirements, dates, quota/selection explanation approved for public display, and public instructions | Own limited status/correction view only through the approved applicant mechanism | Internal reviewers only | All applications, documents, reviews, decisions, conversion, duplicates, retention | Registration number, applicant/guardian PII, documents, reviewer notes, status history, and internal quota/selection data are never public. |
| User account | None | Own account/security fields | Own role-scoped dashboard data | Other accounts, role/status, invitations, recovery, audit/security | Passwords, hashes, reset/verification/remember tokens and session IDs never leave protected credential/session paths. |

### 10.3 Safe media and document exposure

- Public images are served from an explicitly public bucket/prefix or controlled public disk only after publication/moderation approval.
- Private documents are served through a fresh authorization decision and a short-lived signed URL or controlled stream. The URL is not a bearer credential without a time limit.
- Public original images may be replaced by sanitized derivatives. EXIF, GPS, camera serial data, names, student numbers, and registration numbers must be removed or not copied.
- Teacher photos require a publication/withdrawal decision. Student photos remain private.
- Public captions, alt text, filenames, EXIF, and structured metadata are public data and must follow the same student privacy rule.
- Downloadable documents must never be served from a public disk, even if the filename is random.
- Public page caching is limited to public-safe projections; private responses use `private, no-store` or equivalent and are not placed in shared Redis caches.

---

## 11. Conceptual media contract

### 11.1 Media is metadata plus an external object

`media_assets` is a metadata record. `media_variants` is a derived metadata record. The binary is stored on a filesystem/object-storage disk through Laravel's filesystem abstraction; it is not stored in MariaDB.

No private document blob, base64 representation, or raw upload body belongs in an application/audit record.

### 11.2 Minimum media metadata

Proposed media fields include:

- random `object_key` and configured `disk`;
- original filename kept only as restricted metadata;
- server-detected MIME type and byte size;
- checksum;
- uploader/owner user reference;
- processing status (`quarantined`, `processing`, `ready`, `failed`, `deleted`);
- access visibility (`private`, `public`);
- moderation/publication approval state where the media is used publicly;
- optional image width/height and sanitized metadata state;
- timestamps and deletion/quarantine timestamps.

The exact disk names, provider, formats, maximum dimensions, and scan service are **O2-10**. The domain must not hard-code local paths or S3-specific behavior.

### 11.3 Ownership and visibility

- `uploader_user_id` records who uploaded, not necessarily who owns the business meaning.
- Public content tables hold explicit media foreign keys; PPDB document tables hold explicit private media references. Do not use an unconstrained polymorphic owner for a privacy-sensitive document.
- A media asset's business owner module controls publication and deletion. A generic media row must not be publicly addressable by path.
- A private source may generate private variants only. A processing bug must not move a source to a public disk.
- Deleting a business record does not blindly delete shared media. A reference/count check, retention check, and explicit media action decide what happens to the object and variants.

### 11.4 Consent and authorization

- Upload authorization is separate from publication authorization.
- A teacher photo, gallery image, or other identifiable media requires the approved consent/approval record before public use; a generic media visibility flag is not consent.
- Student media is private by default and cannot be made public under A-11.
- A public media URL is issued only after a public record's publication state and media moderation state are both valid.
- Withdrawal/unpublishing invalidates public cache entries and removes/revokes the public derivative according to the media retention decision; the original metadata is not silently hard-deleted.

### 11.5 Upload and processing controls

Every future upload path must apply:

- authentication and resource authorization where applicable;
- extension allowlist plus server-side MIME/content inspection;
- file size, pixel/dimension, and request-size limits;
- safe active-content policy (deny SVG or other active formats unless a reviewed sanitizer exists);
- randomized, non-identifying storage keys;
- private storage for personal documents and unapproved assets;
- malware scanning/quarantine for PPDB documents and other untrusted documents;
- metadata stripping/re-encoding for public images;
- bounded processing with a visible failure state;
- after-commit jobs for thumbnails, sanitization, scan results, and notifications.

No implementation detail should assume a particular antivirus or object-storage provider. The media service boundary must make the provider replaceable.

---

## 12. Data classification and handling

The following is the minimum first-release classification table. Classification applies to fields and projections, not merely to tables.

| Classification | Representative fields/records | Read rule | Write rule | Logging | Cache | Additional audit |
|---|---|---|---|---|---|---|
| **Public** | Approved school name/public contact; published Berita fields; anonymized Prestasi; published public media; public PPDB period information | Guest may read only published/approved projection | Administrator or explicitly authorized content workflow; publication is a separate action | Safe event metadata only; no unnecessary PII | Redis/public cache allowed with explicit TTL/version/invalidation | Publish/unpublish/archive and media visibility changes |
| **Internal** | Academic years/terms; classes; subjects; assignment existence; non-sensitive counts; internal navigation/content drafts | Active Guru/Siswa may see only role/scope-approved summaries; administrator manages | Authorized academic/content staff through Form Request/Policy/service | IDs, action, result, correlation ID; no full free text by default | Public-safe summaries may be cached; role/dashboard data generally not | Creation, status change, assignment, export, and privileged read |
| **Confidential** | Full Guru profile; student profile; guardian record; class membership; PPDB application; reviewer notes; account/profile contact data | Administrator, or exact owner/assigned scope; field projection required | Authorized domain workflow; corrections and sensitive writes explicit | Redacted minimal context; never log complete records | No shared/public cache; private/no-store for sensitive responses | Sensitive read, correction, export, private download, denial, and state transition |
| **Sensitive** | NIK/official identity data, birth data, private photos, identity documents, PPDB documents, reviewer decisions, audit details, password/reset/verification/session secrets | Strongly restricted administrator/reviewer/owner; fresh decision for download | Approved security/privacy workflow only | **Never** log secrets or unnecessary sensitive PII; audit identifiers/results only | Never shared cache; no raw values in Redis | Mandatory sensitive-read/download/export audit; PPDB and identity events |
| **Credential/security** (cross-cutting) | Password hash, reset token, verification token, remember token, session ID, application keys and service credentials | Authentication/session subsystem only | Authentication/security subsystem only | Never | Never in application cache payloads | Security events record outcome, not secret values |

Additional rules:

- Do not log passwords, password hashes, raw reset/verification/remember tokens, session IDs, raw identity documents, full PPDB answers, or unnecessary student/guardian data.
- A field classified as confidential or sensitive must be hidden from serialization and excluded from public query projections.
- Cache keys should use opaque content IDs or versioned slugs, not names, emails, student numbers, NIK, registration numbers, or other PII.
- An audit snapshot may include the field name, action, result, and a redacted before/after value only when the value is approved for audit. Prefer omission over partial masking when re-identification is possible.
- “Can be logged” never means “must be logged.” Logging is minimized and purpose-limited.

---

## 13. Retention and lifecycle contract

No retention duration is invented in this phase. The following lifecycle semantics are binding design guidance; exact periods, legal holds, backup expiry, and deletion authority require the open decisions in Section 18.

### 13.1 Lifecycle meanings

| Action | Meaning | Use for |
|---|---|---|
| **Deactivate** | Remove login or operational eligibility while retaining the person and history | User account, temporarily unavailable teacher/student, closed assignment |
| **Archive** | Remove from normal current views while retaining a read-only historical record | Closed academic year, ended public content, completed PPDB period, historical person record |
| **Anonymize** | Remove or irreversibly transform identifying values while retaining non-identifying history/audit | Approved retention expiry of a public achievement or old applicant record; never an ad hoc admin action |
| **Delete** | Remove the record/object according to an approved retention and legal process | Expired temporary files, abandoned uploads, or a specifically approved erasure; never a default for personal history |

### 13.2 Entity lifecycle rules

| Data set | Default lifecycle | Deletion/retention rule |
|---|---|---|
| Users | `suspend` blocks access; `inactive` disables login; account deactivation preserves audit and historical references | Do not hard-delete by default. Anonymize account contact fields only after an approved period; preserve actor IDs or safe snapshots needed for audit. |
| Guru records | Employment `leave`/`inactive`/`retired`; profile remains historical | Keep academic/content references. Remove public media according to withdrawal/retention; no ordinary hard delete. |
| Siswa records | `on_leave`, `graduated`, `transferred`, `withdrawn`, then optional `archived` | Keep enrollment and decision history. Identity/photo/document fields follow approved retention; public student data is never created. |
| Guardians | `inactive` when no longer responsible; `archived` after approved retention | End relationships with effective dates. Do not cascade-delete a guardian with a student; duplicate correction is an audited merge/re-link. |
| Enrollments | Status transition/end-date, never overwrite history | Retain according to academic/legal policy. Student deletion must not orphan history. |
| Homeroom/teaching assignments | End-date and close; retain old rows | Never delete solely because a year/teacher/class changed. |
| PPDB applications | `archived` after period closure; status history remains append-oriented | Raw applicant/guardian PII and documents follow approved period-specific retention; an application record is not deleted just because a period is archived. |
| PPDB documents | `retained`, `superseded`, or retention-deleted metadata plus object lifecycle | Do not expose or hard-delete an object while a legal/operational hold or required review exists. Deletion must cover object versions/variants/caches as appropriate. |
| Public media | Unpublish/withdraw first; then retention/delete object and variants | Public derivative removal does not automatically delete a private source. Never allow a private source to become public during cleanup. |
| Audit records | Append-only; corrections are new events | Retention, legal hold, administrator access, and backup expiry are explicit open decisions. No update/delete shortcut. |

A scheduled cleanup job may be implemented later, but it must be observable, idempotent, hold-aware, and tested against a backup/restore process. It must not be designed as an automatic hard-delete default.

---

## 14. Authorization matrix

### 14.1 Enforcement layers

The matrix is a **proposed resource contract**, not an implementation. For every protected operation, future code must use the applicable layers:

1. authentication middleware;
2. active account and, where appropriate, email verification;
3. coarse role middleware for route groups;
4. Form Request authorization and validation;
5. resource Policy abilities;
6. ownership, assignment, reviewer, or administrator scope;
7. query-level scoping and field-level projection;
8. a fresh authorization decision for private downloads/exports;
9. negative-access tests.

A role name is not a permission. `role=guru` never means “all students.” `role=siswa` never means “all student data.”

### 14.2 Detailed matrix

`—` means denied unless a future, separately approved decision changes the contract. Public entries always mean the public projection, not the full record.

| Resource/ability | Public/guest | Administrator | Guru | Siswa | Scope/override rule |
|---|---|---|---|---|---|
| User account: list/view other accounts | — | View/manage | — | — | Active, verified administrator only; sensitive reads audited. |
| User account: own view/security fields | — | Own and all | Own | Own | Exact user ownership; account is not a person profile. |
| User role/status/activation/deactivation | — | Manage | — | — | Explicit lifecycle service and audit; no hard delete. |
| Guru profile: public professional projection | Approved projection only | Manage all | View public projection | View public projection | Only fields allowlisted by B-13; no account/private fields. |
| Guru profile: own approved fields | — | Manage | View/update only own approved fields | — | Ownership; private employment/admin fields excluded. |
| Guru profile: another person's private fields | — | Manage | — | — | No Guru-wide directory access by role alone. |
| Siswa profile: public projection | — | — | — | — | **None in first release.** |
| Siswa profile: own record | — | View/manage | — | View own approved fields | Exact `students.user_id` ownership; no public directory. |
| Siswa profile: assigned student | — | View/manage | View minimum necessary assigned fields | — | Active/effective homeroom or teaching assignment only; no cross-class/history by default. |
| Guardian record: full/contact/identity | — | Manage | Only if an approved assignment requires a specific field | Only explicitly approved own-linked information | Sensitive read/audit; no general student/guardian browsing. |
| Class/group: public aggregate | Only if separately approved and non-identifying | Manage | View assigned/current context | View own placement | Membership/name is not public student PII. |
| Class/group: administrative detail | — | Manage | View assigned classes only | — | Effective assignment scope; no arbitrary class enumeration. |
| Enrollment: own/history | — | View/manage | View minimum assigned context | View own history | Siswa ownership; Guru assignment scope; administrator audit for sensitive reads. |
| Enrollment: create/change/end | — | Manage through academic service | — | — | Explicit action ability, lock, effective dates, and history. No mass assignment from request arrays. |
| Teacher assignment: own/assigned | — | Manage | View own assignments | — | Assignment ownership; no Guru access to another teacher's private data. |
| Teacher assignment: manage others | — | Manage | — | — | Administrator only; audit. |
| PPDB public information | Published period/instructions only | Manage | — | — | No applicant PII in public projection. |
| PPDB submit/status/correction | Submit through approved public flow; limited own status | Manage all | — | Own linked application only if a future policy explicitly permits | Applicant is not a User; access token/session is separate and expiring. |
| PPDB review/internal notes/documents | — | Assigned reviewer/manager | — | — | Reviewer ownership; sensitive read/download audit; possession of URL is insufficient. |
| Berita: published | View | Manage all | View | View | Public content must be published and sanitized. |
| Berita: draft/contributor work | — | Manage | Only if a separate contribution ability is approved | — | Ownership/contribution scope; publication remains administrator action. |
| Prestasi: anonymized published | View | Manage | View | View | Public projection cannot include student identity. |
| Prestasi: internal recipient data | — | Manage | — | — | Internal recipients are not public projection fields. |
| Galeri: published approved items | View | Manage | View | View | Album/item/media publication state and privacy check. |
| Galeri: upload/edit | — | Manage | Only if separately granted | — | No Guru upload by role alone. |
| School profile: public fields | View | Manage | View | View | Administrator manages internal/unpublished fields. |
| Notifications | — | Own/broadcast as policy allows | Own | Own | Notification recipient ownership; no applicant/guardian internal account implied. |
| Audit/private exports | — | View/perform approved action | — | — | Administrator override is explicit, reason/audit required; no raw secrets/PII in export. |

### 14.3 Administrator override

Administrator override is a deliberate policy ability, not a bypass of validation, privacy classification, storage rules, or audit. Sensitive administrator reads, exports, downloads, corrections, publication decisions, and retention actions must be attributable and redacted. A 403/404 response must not reveal whether a private record exists to an unauthorized actor.

### 14.4 Policy/query obligations

The future implementation must test both direct resource access and list scoping:

- a Guru cannot use a direct student ID outside their assignment;
- a Siswa cannot use a direct enrollment, guardian, student, or account ID belonging to someone else;
- an unassigned Guru cannot enumerate class rosters through search, export, count, or a relationship endpoint;
- a PPDB applicant cannot use a registration number without the separate status mechanism and cannot see internal notes/documents;
- public content queries select only public-safe columns and published/approved states;
- an administrator's broad list remains paginated and access-logged for sensitive domains.

---

## 15. Performance design

### 15.1 Capacity interpretation

The existing architecture document provides a **planning hypothesis**, not an approved capacity claim: approximately 1,000 active sessions in a rolling window, 100 dynamic requests/second sustained, up to 100 concurrent in-flight dynamic requests, and a 250 requests/second burst. B-22/O2-14 must confirm the workload, data volumes, SLOs, and staging topology. This blueprint does not promise that a single VPS supports that load.

### 15.2 High-read paths

| Path/entity | Query pattern and relationship loading | Pagination/eager loading | Cache candidate |
|---|---|---|---|
| School home/profile | One singleton and a small set of published navigation/content counts | No unbounded relationship loading | Redis public projection with versioned invalidation |
| Public Berita | Filter published/scheduled state, order by `published_at` and stable ID; load approved cover only | Cursor/keyset pagination after volume warrants it; bounded page size | Redis/list cache with publish/update invalidation |
| Public Prestasi | Filter published state and achievement date; never join public student identity | Bounded list; no recipient hydration | Public anonymized projection cache is acceptable |
| Public Galeri | Published album → published items → ready public media; stable sort order | Paginate items, eager-load only public media metadata | Public metadata cache; serve media via Nginx/CDN, not PHP |
| Public Guru | Only approved/published projection; active employment and publication state | Bounded directory; eager-load approved public photo/assignment projection | Public directory cache is acceptable after B-13 approval |
| Guru dashboard | Current assignments and class counts for the authenticated teacher | Paginate students; eager-load the minimum class/assignment context | Do not globally cache personalized dashboards |
| Siswa dashboard | Resolve linked student, current enrollment, and permitted history | Bounded history; eager-load current class/term only | No global student cache |
| Administrator people/academic lists | Status/filter/sort with scoped joins | Always paginate; eager-load only required columns | Cache only non-sensitive aggregate counts if measured useful |
| PPDB review queue | Filter status, assigned reviewer, period, submitted time; lock only on transitions | Paginate; do not load documents or full applicant data into list rows | No shared PPDB cache |
| Private document download | Resolve authorized media reference and issue short-lived access | Not a list path | No response/object cache in application layer; object/CDN policy controls public only |

### 15.3 High-write paths

- Account provisioning/deactivation and role changes: current identity lifecycle plus audit/session invalidation; rate-limited and low volume.
- PPDB submission/revision/review/conversion: transaction, row lock, duplicate controls, history, and after-commit jobs; highest correctness priority.
- Enrollment/class/assignment changes: transaction, uniqueness validation, effective dates, and history; do not bulk update without a reviewed workflow.
- Media upload/processing: write quarantine/private object, then asynchronous scan/thumbnail/metadata work; retry idempotently by media ID.
- Public content publish/unpublish/archive: write content and media visibility, invalidate public cache, and record audit.
- Guardian/student corrections: low-volume, high-sensitivity writes with reason, restricted access, and audit.

### 15.4 Index design principles

Indexes are selected from query paths, not copied from a generic list. Foreign keys must be indexed by their constraint or an intentional composite index; do not add redundant single-column indexes automatically. Important candidate paths and their rationale are detailed in the schema blueprint. All candidates require MariaDB query-plan verification with representative data before migration approval.

Likely high-value composite paths include:

- published content by status/date/ID;
- Guru scope by teacher/assignment/effective dates/class;
- active enrollment by student/year/status and class/status;
- PPDB reviewer queue by period/status/reviewer/submission time;
- unique business identifiers and opaque registration numbers.

Avoid indexing every status/date/search column independently. Check selectivity, sort order, existing indexes, write cost, and `EXPLAIN` results. No partitioning, read replica, or sharding is introduced by this blueprint.

### 15.5 Redis and queue candidates

Redis is later infrastructure, not a business source of truth. Candidate uses are:

- public school/news/achievement/gallery/PPDB-information cache with explicit TTL and invalidation;
- production sessions and rate limits;
- queued email and applicant/status notifications;
- media scan/thumbnail/metadata processing;
- PPDB document/duplicate/status side effects;
- authorized exports and maintenance/retention jobs.

Do not place raw student, guardian, or PPDB PII in Redis keys, values, cache payloads, or queue payloads. Jobs carry opaque IDs and minimal context, are after-commit, idempotent, retryable with backoff, and have explicit failure handling. `cache`, `jobs`, and framework infrastructure tables may remain development fallbacks but do not become a second business model.

### 15.6 Queue and processing boundaries

- Media processing must preserve source visibility and quarantine failed files.
- PPDB conversion must commit durable records before notifications; email failure cannot duplicate or reverse the student.
- Exports are private, authorized, expiring, and audited.
- Scheduled cleanup is hold-aware and must be tested with backup/restore; it is not a generic hard-delete task.
- Redis failure behavior and worker supervision remain an operations gate (B-23), not an assumption in this phase.

---

## 16. Security design review

| Risk | Blueprint mitigation | Required implementation evidence later |
|---|---|---|
| IDOR on student/profile/enrollment/document IDs | Resource Policy plus query scope plus ownership/assignment/reviewer checks on every list/detail/export/download; use non-confirming 404 where existence is sensitive | Cross-user, cross-class, cross-role, direct-ID, and export negative tests |
| Excessive data exposure | Explicit public field allowlists, query-level column selection, public projections, `hidden` sensitive attributes, no `select *` for public pages | Snapshot/response tests proving private fields cannot appear in HTML, JSON, error, or export output |
| Authorization bypass from role middleware | Middleware is coarse only; Form Requests, Policies, scope services, and ownership checks are mandatory | Policy matrix plus endpoint tests proving middleware cannot replace resource authorization |
| Mass assignment of role/status/identity | Explicit fillable/guarded allowlists, Form Requests, DTOs/services for workflows; no request-array mass updates | Tests with unexpected `role`, `status`, `user_id`, ownership, and status fields |
| ID enumeration | Opaque public slugs/registration numbers where needed, generic errors/responses, rate limits, no existence-confirming 403 for sensitive direct IDs | Enumeration and response-shape tests |
| File upload abuse | Extension + content/MIME validation, size/dimension limits, private disks, random keys, quarantine/scan, metadata stripping, no active content | Upload abuse, MIME mismatch, oversized file, SVG, failed-scan, and private-download tests |
| Public/private media confusion | Explicit visibility, business publication state, separate disks/buckets, no public path for private source, derivative visibility inheritance | Test that a private asset/URL never resolves publicly and deletion does not publish a derivative |
| PPDB document exposure | Private document metadata, fresh authorization before signed URL, short expiry, download audit, rate limit, no registration-number-only access | Applicant/other-reviewer/Guru/Siswa denial tests and URL-expiry tests |
| Student privacy | No student public projection; anonymized public achievements only; no student face gallery; no PII in filenames/alt text/EXIF/cache keys/logs | Public projection, media metadata, cache, and log inspection tests |
| Sensitive logging | Structured redacted events; no passwords, tokens, session IDs, raw documents, or full PII; hash/digest rate-limit keys | Log/assertion tests and review of exception context |
| SQL injection/search abuse | Eloquent/query builder bindings, allowlisted sort/filter identifiers, bounded pages, no interpolated raw SQL | Dynamic filter/sort security tests and MariaDB query review |
| CSRF/session abuse | Web state changes POST + CSRF; session regeneration and active/verified checks; no sensitive session payloads | Feature tests for cross-site mutation and account deactivation invalidation |
| PPDB duplicate/race abuse | Rate limits, privacy-safe duplicate detection, row locks, state/version checks, idempotent conversion, audit history | Concurrent submission/review/conversion and retry tests |
| Retention/deletion abuse | Status/hold-aware workflows, no default hard delete, audit corrections, backup/object consistency | Retention dry-run, hold, failed-cleanup, and restore tests |
| Single-school boundary drift | No `school_id`; new school support requires an explicit architecture decision | Code/schema review for accidental tenant fields and cache-key assumptions |

The first release should fail closed. A missing profile link, missing assignment, missing reviewer scope, missing publication approval, or missing retention decision must deny the operation rather than default to broad access.

---

## 17. Migration dependency plan for future implementation

No migration is created in this phase. The following is the dependency order to use after the corresponding owner gates are resolved.

| Future migration increment | Dependencies | Contents after approval | Gate before writing files |
|---|---|---|---|
| **3A — Identity/person foundation** | Existing `users`; B-05 identity/link decision; B-13/B-14/B-19 privacy classification; B-21 if identity security is expanded | School profile, Guru, Siswa, guardian, and `student_guardians` tables; explicit nullable/unique user links; factories and policy tests. No `school_id`. | Approve person fields, identifiers, status semantics, public projections, retention, and account-link rules. |
| **3B — Academic foundation** | 3A; B-01/B-02/B-03/B-04 | Academic years, terms, classes, subjects, homeroom assignments, teaching assignments, enrollments, factories, scope/policy tests. | Approve calendar, grade vocabulary, class capacity/status, assignment scope, enrollment uniqueness and transitions. |
| **3C — Media/public content** | 3A and B-15/B-16; B-12 where achievements depend on people | Media metadata/variants, Berita, anonymized Prestasi and internal recipients, Galeri albums/items, publication/cache/media tests. | Approve public allowlists, consent/moderation, disk/visibility, formats, and retention. |
| **3D — PPDB** | 3A/3B/3C; B-07/B-08/B-09/B-10/B-11/B-12; audit/notification minimum | Admission periods/quotas as approved, applications, applicant guardians, private documents, reviews, status history, controlled conversion. | Approve applicant access, state machine, quotas, reviewer model, document rules, consent, conversion timing, and retention. |
| **3E — Platform capabilities** | Event contracts from 3A–3D; B-17/B-18/B-19/B-20/B-23 as applicable | Audit store, notification preferences/delivery as approved, exports, maintenance/cleanup jobs, Redis integration. | Approve event catalog, redaction, channels, retention, backup/RPO/RTO, and failure behavior. |
| **3F — Performance/security validation** | Representative implementation | MariaDB/Redis integration, index/query-plan review, load test, upload/private-URL tests, restore test. | Workload/SLO and deployment topology approved; results documented. |

The order can be refined during implementation review, but a later module must not write an earlier module's canonical records directly or create speculative tables to bypass its gate. In particular, PPDB conversion must call the owning People/Academic actions rather than silently inserting a student row from a controller.

---

## 18. OPEN DECISIONS REQUIRING OWNER APPROVAL

The following are deliberately unresolved. The recommended defaults are for discussion only. Phase 0's migration gate remains closed for any area whose decision is unresolved.

### O2-01 — Official identifiers and minimum identity fields

- **Decision:** Which identifiers are mandatory: Guru NIP/employee number, Siswa student number, NISN, NIK, and which fields are optional/conditional?
- **Why it matters:** Determines field nullability, validation, duplicate handling, masking, retention, and PPDB intake.
- **Options:** (A) minimal operational identifiers only; (B) full official identifiers for students/guardians; (C) configurable per program/period.
- **Recommended default for discussion:** Require a unique Guru employee number and unique Siswa student number; collect NIK/NISN only where a documented legal/admission process needs them, with sensitive classification and no public use.
- **Schema impact:** Required/nullable columns, unique constraints, correction workflow, encrypted/masked handling.
- **If delayed:** People/PPDB migrations and forms cannot be finalized; placeholder identifiers must not be added.

### O2-02 — Academic calendar, grade levels, and programs

- **Decision:** Supported grade levels, number/order of terms, semester vs trimester terminology, and whether programs/strands exist.
- **Why it matters:** Determines academic year/term constraints, class labels, quotas, enrollment transitions, and rollover.
- **Options:** (A) two semesters and numeric levels; (B) configurable one-or-more terms; (C) program/strand-specific calendar.
- **Recommended default for discussion:** One year with one-or-more ordered terms; numeric grade levels validated to the school's approved list; no program entity until a real program requirement exists.
- **Schema impact:** `terms` cardinality/sequence, class level representation, optional program entity, enrollment/assignment scope.
- **If delayed:** Academic tables and PPDB quotas/periods remain blocked.

### O2-03 — PPDB applicant access/status channel

- **Decision:** Draft/resume mechanism and how an applicant proves ownership of an application for status/correction.
- **Why it matters:** Determines whether a separate applicant identity table, token lifecycle, contact verification, and expiry rules are needed; it must not create an internal role.
- **Options:** (A) no draft; short-lived email status link; (B) verified contact plus expiring link; (C) separate limited applicant identity.
- **Recommended default for discussion:** No anonymous persistent draft; opaque registration number plus short-lived, rate-limited, verified email status link; no internal account.
- **Schema impact:** Possible application identity/access fields or table, token metadata, consent and retention fields.
- **If delayed:** Public submission UX, duplicate prevention, and PPDB access policy cannot be implemented safely.

### O2-04 — Enrollment and school-year transition rules

- **Decision:** One active enrollment per year versus term, promotion/transfer/withdrawal/leave/completion statuses, and whether acceptance places immediately.
- **Why it matters:** Determines uniqueness, history, current-placement queries, and conversion transaction.
- **Options:** (A) one active class per year; (B) term-specific active classes; (C) multiple concurrent activities/classes.
- **Recommended default for discussion:** One active class per academic year, explicit historical rows, end-dated transfers, no destructive rollover.
- **Schema impact:** Enrollment status enum, uniqueness/indexes, effective dates, promotion action and conversion fields.
- **If delayed:** Academic and PPDB conversion migrations are blocked.

### O2-05 — Guru scope and minimum student fields

- **Decision:** Whether homeroom and subject assignments both grant access, whether historical assignments grant access, and exactly which student fields an assigned Guru may see.
- **Why it matters:** Prevents unrestricted student access and avoids an over-broad or unusable dashboard.
- **Options:** (A) homeroom only; (B) homeroom plus subject assignment; (C) time-bounded role-specific scopes with a separate permission layer.
- **Recommended default for discussion:** Both current homeroom and current teaching assignments, no historical access by default, minimum necessary fields only.
- **Schema impact:** Assignment tables/effective dates, possible field-view metadata, indexes and policy/query scope.
- **If delayed:** Guru dashboard and student Policies cannot be safely implemented.

### O2-06 — PPDB review ownership and selection

- **Decision:** One administrator, panel, ranking/scoring, chronological order, quota priority, conflicts of interest, and reassignment rules.
- **Why it matters:** Determines reviewer columns, review tables, state transitions, fairness, and audit.
- **Options:** (A) one assigned administrator/manual decision; (B) panel with multiple reviews; (C) scored/ranked selection.
- **Recommended default for discussion:** One assigned administrator at a time, manual auditable decision, no automated ranking in the first release.
- **Schema impact:** Reviewer assignment/uniqueness, review entity, optional score/panel entities, indexes and workflow tests.
- **If delayed:** PPDB review schema and transition service remain blocked.

### O2-07 — PPDB duplicate identity and correction policy

- **Decision:** Which signals identify duplicates, whether a hard unique key is required, and how legitimate shared contacts/duplicate submissions are resolved.
- **Why it matters:** Prevents double admission while not rejecting siblings or exposing identity information.
- **Options:** (A) soft detection plus administrator merge decision; (B) hard unique normalized identity; (C) external identity verification.
- **Recommended default for discussion:** Soft, privacy-safe duplicate detection and review; unique registration number only; no global unique parent email/phone/NIK.
- **Schema impact:** Optional digest/identity-reference entity, duplicate flags, merge/correction history, indexes.
- **If delayed:** PPDB submission/duplicate controls cannot be finalized.

### O2-08 — PPDB documents, consent, and retention

- **Decision:** Required document types by level/program, formats/size, versions, validity, malware scanning, applicant consent text/version, and retention after acceptance/rejection/withdrawal.
- **Why it matters:** Determines private media/document fields, processing states, legal basis, and deletion safety.
- **Options:** (A) fixed minimal document set; (B) program/period-specific requirements; (C) external document provider.
- **Recommended default for discussion:** Fixed allowlisted document types per approved level, private disk, server validation plus malware scanning, versioned references, explicit retention schedule.
- **Schema impact:** Document type/version columns, consent record fields, media processing state, retention/hold metadata.
- **If delayed:** PPDB documents/media and real personal-data collection are blocked.

### O2-09 — PPDB acceptance/conversion timing and account invitation

- **Decision:** Immediate conversion, applicant confirmation, administrator placement deadline, and whether a Siswa account is invited automatically after conversion.
- **Why it matters:** Determines conversion state, enrollment timing, user provisioning, email retry behavior, and applicant communication.
- **Options:** (A) immediate conversion + account invite; (B) accepted then manual conversion; (C) accepted then confirmed/deadline conversion.
- **Recommended default for discussion:** Accepted decision is separate; a controlled conversion action creates student/enrollment after confirmation or deadline, and account invitation is an explicit post-commit step.
- **Schema impact:** Conversion status/timestamps, target class/period fields, invitation relation, idempotency constraints.
- **If delayed:** Student conversion and PPDB transaction cannot be implemented safely.

### O2-10 — Public Guru, publication consent, and media formats

- **Decision:** Exact public teacher fields, whether names/photos are searchable/indexable, consent authority/withdrawal, gallery public/private modes, and allowed image/document formats.
- **Why it matters:** Determines public projections, media fields, caching, and privacy.
- **Options:** (A) no public people directory; (B) administrator-approved professional directory; (C) opt-in directory with richer media.
- **Recommended default for discussion:** No public student media; administrator-approved minimal Guru directory; public images only after consent/moderation; private documents remain private.
- **Schema impact:** Publication/consent fields, media visibility/moderation fields, possible public slug.
- **If delayed:** People/media/public-content migrations and public pages remain blocked.

### O2-11 — Prestasi recipient model and public form

- **Decision:** Whether internal recipient records are required and which non-identifying fields may be public.
- **Why it matters:** Determines whether a recipient join table is necessary and prevents accidental student identification.
- **Options:** (A) school-level achievement only; (B) internal recipient links with anonymized public projection; (C) identifiable public recipients (requires changing A-11).
- **Recommended default for discussion:** Option B if the school needs internal attribution; public output remains school-level/anonymized and never joins student identity.
- **Schema impact:** Optional recipient entity with explicit student/teacher foreign keys, internal-only projection and indexes.
- **If delayed:** Achievement schema and content policy remain blocked.

### O2-12 — Content workflow and contributor roles

- **Decision:** Whether Guru may create/edit Berita, Prestasi, or Galeri drafts; who may publish/archive; whether draft visibility is contributor-only.
- **Why it matters:** Determines Policy abilities, author ownership, moderation, and cache invalidation.
- **Options:** (A) administrator-only content; (B) Guru contributors with administrator publication; (C) more complex editorial workflow.
- **Recommended default for discussion:** Administrator manages/publishes; Guru contribution is a separately granted, ownership-scoped ability and not implied by `role=guru`.
- **Schema impact:** Author/contributor references, status fields, possibly assignment/permission metadata; no permission table until justified.
- **If delayed:** Content authorization matrix remains incomplete.

### O2-13 — Retention, legal holds, audit, backup, and deletion authority

- **Decision:** Retention periods for students, guardians, PPDB, documents, media, notifications, exports, audit, and backups; who may authorize deletion/anonymization; RPO/RTO and restore ownership.
- **Why it matters:** Prevents irreversible data loss and determines cleanup/backup metadata.
- **Options:** (A) policy-specific durations; (B) indefinite operational retention with archival; (C) short minimum-retention policy with legal holds.
- **Recommended default for discussion:** Retain academic/audit history by default, separate sensitive document retention, hold-aware deletion, encrypted/tested backups, no automatic hard deletion.
- **Schema impact:** Retention/hold metadata, audit event catalog, possibly cleanup job records.
- **If delayed:** Real personal data, scheduled cleanup, production launch, and some audit tables remain blocked.

### O2-14 — Performance workload, data volumes, and SLOs

- **Decision:** Meaning of 1,000 concurrent users, representative volumes, latency/error/queue targets, and staging pass criteria.
- **Why it matters:** Indexes, pagination, cache, queue workers, and capacity depend on real workload.
- **Options:** (A) active-session hypothesis already documented; (B) in-flight-request target; (C) mixed workload with measured production-like data.
- **Recommended default for discussion:** Use the existing 1,000 active-session/100-RPS hypothesis only for test planning, then replace it with measured SLOs.
- **Schema impact:** Final composite indexes, partitioning (if ever needed), capacity-related metadata.
- **If delayed:** Performance claims and final index approval remain blocked; base tables may still be reviewed.

### O2-15 — Notification, Redis, mail, and storage operations

- **Decision:** First-release channels, sender identity, queue supervision, Redis isolation/failure behavior, object-storage topology, and provider monitoring.
- **Why it matters:** Affects notification payload privacy, PPDB conversion side effects, media/download availability, and operational safety.
- **Options:** (A) database notifications + database fallback; (B) in-app/email with Redis; (C) additional channels/providers.
- **Recommended default for discussion:** In-app and email only, Redis-backed later infrastructure, separate public/private storage, no silent unsafe fallback.
- **Schema impact:** Notification preferences/delivery records, queue metadata, storage/media operational fields.
- **If delayed:** Platform migrations and PPDB production readiness remain blocked.

### O2-16 — Administrator MFA and controlled provisioning

- **Decision:** MFA factor, enrollment/recovery/bypass policy, and first-administrator provisioning process.
- **Why it matters:** Phase 0 made MFA a production launch requirement; identity security is not complete without it.
- **Options:** (A) mandatory TOTP; (B) WebAuthn/passkeys; (C) another reviewed factor.
- **Recommended default for discussion:** Mandatory MFA for every administrator before administrative access, with audited recovery and no unreviewed bypass.
- **Schema impact:** MFA factors/recovery metadata may require additive identity migrations; no factor schema is created in this planning phase.
- **If delayed:** Production identity/security approval remains blocked even if domain design is reviewable.

### O2-17 — Locale, timezone, and official data-entry conventions

- **Decision:** Indonesian display/storage conventions, phone/address/name normalization, date validity, and official identifier validation.
- **Why it matters:** Affects indexes/search, audit timestamps, date boundaries, and validation.
- **Options:** (A) UTC storage with `Asia/Jakarta` presentation; (B) store all dates in local time; (C) configurable school timezone.
- **Recommended default for discussion:** Store timestamps in UTC, present in `Asia/Jakarta`, use ISO dates at boundaries, normalize account email to lowercase, and validate official identifiers without exposing them.
- **Schema impact:** Column types, date/time defaults, normalized fields, length/collation choices, correction rules.
- **If delayed:** Identity/people/academic/PPDB validation and final index choices remain blocked.

### O2-18 — Backup RPO/RTO and restore ownership

- **Decision:** Backup scope/frequency, encryption/offsite storage, RPO, RTO, restore-test frequency, and responsible owner.
- **Why it matters:** Database and private media must be restorable together; a backup without a tested restore is not a recovery plan.
- **Options:** (A) database-only backup; (B) encrypted database plus private media/object storage; (C) managed offsite full-service recovery.
- **Recommended default for discussion:** Encrypted offsite database and private-media backups with documented RPO/RTO and a scheduled restore exercise.
- **Schema impact:** Usually no business schema change, but retention/cleanup and operational metadata depend on it.
- **If delayed:** Production launch and reliable retention/deletion remain blocked.

---

## 19. Phase 0 decision traceability

This table shows how the blueprint addresses each open Phase 0 item. “Specified” means this document supplies a recommendation; it does not mean the owner has approved it.

| Phase 0 item | Phase 2 treatment | Remaining owner gate |
|---|---|---|
| B-01 Academic scope/calendar | Sections 8.1–8.2, 8.8; deferred optional academic features are explicit | O2-02 |
| B-02 Class structure | Section 8.3; year-specific class and history rules | O2-02 and class merge/rename decision |
| B-03 Teacher assignment scope | Sections 5.3–5.4; explicit time-bounded scope | O2-05 |
| B-04 Student enrollment/movement | Sections 6.3, 8.6–8.7; historical/active distinction | O2-04 |
| B-05 Identity/account linkage | Sections 4.1–4.6; no guardian/account coupling; immutable IDs | O2-01, O2-16, O2-17 |
| B-06 Guardians | Section 7; person-only, many-to-many, primary/emergency, no login | Relationship vocabulary/retention approval |
| B-07 Applicant identity/access/consent | Section 9.3 and 9.6; no internal User; status link recommended | O2-03, O2-08 |
| B-08 PPDB state machine | Section 9.4; explicit states/transitions/history | O2-06, O2-07, O2-09 |
| B-09 PPDB documents | Sections 9.7 and 11; private media contract | O2-08 |
| B-10 PPDB quotas/capacity | Section 9.2; period-level baseline and explicit quota branch | O2-02, quota owner decision |
| B-11 Review/ranking/decisions | Section 9.5; one active reviewer recommendation | O2-06 |
| B-12 Acceptance/conversion | Section 9.8; idempotent controlled action | O2-04, O2-09 |
| B-13 Public teacher profiles | Section 5.5; no directory until allowlist approved | O2-10 |
| B-14 Student achievement visibility | Sections 6.4 and 10.2; anonymized/school-level only inherited boundary | O2-11 for internal recipient model |
| B-15 Gallery/media privacy | Sections 9.7, 10.3, 11; private by default | O2-08, O2-10, O2-13 |
| B-16 Other public content | Section 10 and schema blueprint; publication state required | O2-12 |
| B-17 Notification channels | Section 15.5; candidates only, no channel schema | O2-15 |
| B-18 Audit scope/retention | Sections 12–13; audit requirement and redaction defined | O2-13, O2-16 |
| B-19 Retention/deletion | Section 13; no automatic hard deletion | O2-13 |
| B-20 Backup/RPO/RTO | Section 18 O2-18; explicit production gate | O2-18 |
| B-21 Administrator MFA | Referenced as an inherited identity gate, not implemented here | O2-16 |
| B-22 Performance/SLO | Section 15; hypothesis only, measurement required | O2-14 |
| B-23 Redis/mail/storage/failure | Section 15.5; later operations boundary | O2-15 |
| B-24 Locale/timezone | Identifier/lifecycle guidance in Sections 4, 8, 16 | O2-17 |

## 20. Phase 2 review handoff

This document is ready for **implementation review as a design proposal** when the companion schema blueprint is reviewed alongside it. It is not fully approved, and it does not authorize Phase 3 implementation.

Before any future domain migration, reviewers should:

1. resolve or explicitly defer each O2 decision relevant to the migration area;
2. confirm the fixed role and public student-privacy boundaries remain unchanged;
3. review the logical entity model, field classifications, relationships, indexes, and delete behavior;
4. approve the transaction, authorization, and audit boundaries;
5. prepare an additive migration and MariaDB integration-test plan;
6. assign product, technical, and privacy/security owners.

Until that review is recorded, the only safe implementation state remains the current Phase 1 identity foundation with no Sekolah Kita domain schema.
