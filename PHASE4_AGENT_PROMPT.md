Baca dan ikuti AGENTS.md dan PROJECT_SPEC.md terlebih dahulu.

Lanjutkan proyek Sekolah Kita setelah checkpoint Phase 3.

CHECKPOINT:
- Phase 0 selesai
- Phase 1 selesai
- Phase 2 selesai
- Phase 3 selesai
- Commit: ff79c68
- Laravel 13
- PHP 8.5+
- MariaDB
- Redis
- PHPUnit menggunakan database sekolah_kita_test
- Jangan mengubah database development sekolah_kita secara destruktif
- Jangan commit
- Jangan push

TUGAS:
Implementasikan Phase 4: Public Website & Content Core.

Cakupan:
1. School Profile
2. Berita
3. Prestasi
4. Galeri
5. Guru public directory
6. Contact Us
7. Public layout/navigation
8. Admin publication workflow

ATURAN:
- Inspect repository sebelum mengubah apa pun.
- Baca:
  AGENTS.md
  PROJECT_SPEC.md
  docs/PHASE_0_APPROVAL.md
  docs/PHASE_2_DOMAIN_DECISIONS.md
  docs/PHASE_2_SCHEMA_BLUEPRINT.md
  docs/PHASE_3_IMPLEMENTATION_PLAN.md
- Pertahankan arsitektur single-school.
- Pertahankan administrator/guru/siswa.
- Gunakan Laravel conventions.
- Form Request untuk validasi.
- Policy/Gate untuk authorization.
- Service hanya bila domain logic memang membutuhkan.
- Gunakan eager loading.
- Hindari N+1.
- Pagination untuk collection publik.
- Public content hanya menampilkan status published.
- Draft/unpublished tidak boleh bocor ke public.
- Gunakan cache untuk query public read-heavy yang memang layak.
- Redis-compatible Laravel cache.
- Tambahkan cache invalidation saat konten berubah.

PRIVACY:
- Tidak ada public student directory.
- Tidak ada identifiable student achievement publik.
- Jangan expose teacher national_id, address, phone, private email, birth data.
- Public teacher hanya field yang aman.
- Media harus divalidasi.
- Jangan expose private storage path.
- Jangan masukkan secret ke source/log/test.

BERITA:
- title
- slug
- excerpt
- body
- cover image
- publication status
- published_at
- author
- public listing
- detail
- pagination
- authorization
- validation

PRESTASI:
- title
- description
- achievement date
- category
- level
- media
- publication state
- privacy-safe public representation
- listing/detail
- authorization

GALERI:
- album
- title
- description
- media items
- publication state
- secure upload validation
- safe filenames
- public listing/detail
- pagination
- privacy checks

GURU:
- public-safe profile
- listing
- detail
- visibility/publication control
- no sensitive fields

CONTACT:
- public contact form
- validation
- rate limiting
- safe storage/logging
- admin-only access to submissions where appropriate

UI:
- responsive mobile-first layout
- header/navigation
- footer
- homepage integration
- breadcrumbs where useful
- semantic HTML
- accessible forms
- empty states
- validation states
- 404 handling

DATABASE:
- migrations only when required
- foreign keys
- indexes
- factories/seeders
- no destructive migration commands
- no migrate:fresh
- no db:wipe
- no DROP
- no TRUNCATE

TESTS:
Add/update tests for:
- public routes
- publication workflow
- authorization
- privacy
- validation
- uploads/media where applicable
- MariaDB schema/index integration
- cache behavior where meaningful

WORKFLOW:
inspect
plan
implement
migrate safely
test
fix failures
review
retest
final audit

Run:
php artisan test --compact
git diff --check

At the end report:
- files changed
- migrations
- models
- policies
- services
- routes
- views
- implemented public pages
- admin capabilities
- security/privacy controls
- cache/performance work
- tests
- exact test result
- remaining TODO
- exact git status

Do not commit or push.
