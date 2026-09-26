# Sekolah Kita

Sistem Informasi Manajemen Sekolah berbasis web. Ringkasan operasional, akun,
master data akademik, PPDB, dan konten publik dalam satu aplikasi.

Stack: Laravel 13, PHP 8.5, MariaDB, Redis, Vite, Tailwind CSS 4, CKEditor 5.

---

## Persyaratan

- PHP 8.3+ (dibangun dan diuji pada 8.5)
- Composer 2
- MariaDB dan Redis
- Node.js 20+ dan npm

---

## Menjalankan secara lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

---

## Data uji pengembangan (UAT)

Aplikasi tidak pernah membuat akun lewat `php artisan migrate` atau
`db:seed` bawaan. Untuk lingkungan lokal dan UAT, gunakan satu perintah
eksplisit:

```bash
php artisan sekolah:seed-demo
```

Perintah tersebut menulis secara idempotent (aman dijalankan berulang kali):

- tiga akun uji dengan peran administrator, guru, dan siswa;
- tahun ajaran aktif beserta term;
- kelas, mata pelajaran, guru, siswa, wali, dan enrollment;
- assignment mengajar dan wali kelas;
- satu periode PPDB yang terbuka beserta pengajuan pada beberapa status;
- sedikit contoh berita.

Kredensial lokal (hanya untuk pengembangan, **jangan dipakai di produksi**):

| Peran | Email | Kata sandi |
| --- | --- | --- |
| Administrator | `admin.test@sekolah.local` | `SekolahKita#Dev2026` |
| Guru | `guru.test@sekolah.local` | `SekolahKita#Dev2026` |
| Siswa | `siswa.test@sekolah.local` | `SekolahKita#Dev2026` |

Perintah menolak dijalankan ketika `APP_ENV=production` dan tidak punya
pengecualian untuk itu, sehingga fixture tidak pernah masuk ke deployment
sekolah.

---

## Ringkasan fitur

| Area | Lokasi |
| --- | --- |
| Dashboard per peran | `/dashboard/administrator`, `/dashboard/guru`, `/dashboard/siswa` |
| Workspace guru | `/guru/kelas` |
| Master data guru dan siswa | `/admin/teachers`, `/admin/students` |
| Master data akademik | `/admin/academic-years`, `/admin/terms`, `/admin/classes`, `/admin/subjects` |
| Penugasan dan enrollment | `/admin/teaching-assignments`, `/admin/class-teacher-assignments`, `/admin/enrollments` |
| PPDB | `/admin/ppdb/periods`, `/admin/ppdb/applications`, `/admin/ppdb/review` |
| Konten publik | `/admin/news`, `/admin/achievements`, `/admin/gallery-albums`, `/admin/teacher-directory`, `/admin/school-profile` |
| Sistem | `/notifications`, `/admin/audit-logs`, `/health/ready` |

Dokumentasi lebih lengkap ada di `docs/`:

- `ARCHITECTURE_DECISIONS.md` — keputusan arsitektur yang disetujui
- `SECURITY_REVIEW.md` — tinjauan keamanan
- `RICH_TEXT_EDITOR.md` — penyunting isi berita, unggah gambar, dan sanitasi
- `BROWSER_VERIFICATION.md` — verifikasi end-to-end dengan Chromium sungguhan
- `PRODUCTION_RUNBOOK.md` — prosedur operasional produksi
- `PRODUCTION_CAPACITY_GUIDE.md` — panduan kapasitas

---

## Perintah yang berguna

```bash
php artisan test                        # seluruh pengujian
php artisan sekolah:seed-demo            # data uji lokal
php artisan platform:readiness           # laporan kesiapan produksi
php artisan platform:heartbeat           # denyut scheduler
php artisan news:prune-staged-article-images   # pembersihan gambar artikel terlantar
npm run build                            # aset produksi
npm run dev                              # Vite dev server
npm run test:e2e                         # verifikasi browser (butuh server berjalan)
```

Gaya kode mengikuti Laravel Pint:

```bash
./vendor/bin/pint
```
