<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
# SEKOLAH KITA — AI DEVELOPMENT RULES

## Project

Nama: Sekolah Kita

Jenis: Sistem Informasi Manajemen Sekolah berbasis web.

Framework:
- Laravel 13
- PHP 8.5+
- Vite
- MariaDB
- Redis

Environment development:
- Android
- Termux
- Ubuntu PRoot
- ARM64

Production target:
- Ubuntu VPS
- Nginx
- PHP-FPM
- MariaDB
- Redis
- HTTPS

---

## ATURAN WAJIB

### 1. Keamanan Data

JANGAN:
- DROP DATABASE
- DROP TABLE tanpa persetujuan
- TRUNCATE data production
- Menghapus database
- Menghapus migration yang sudah digunakan
- Menghapus data pengguna
- Menjalankan destructive command tanpa persetujuan

Database development saat ini:

Database:
sekolah_kita

Database engine:
MariaDB

---

### 2. Project Boundary

Bekerja hanya di:

/home/dev/workspace/sekolah-kita

Jangan memodifikasi:
- filesystem Android
- project lain
- konfigurasi sistem
- database di luar kebutuhan project

---

### 3. Laravel

Gunakan Laravel conventions.

Prioritaskan:
- Controllers
- Form Requests
- Policies
- Middleware
- Services jika diperlukan
- Eloquent Models
- API Resources jika diperlukan
- Jobs
- Events
- Notifications

Hindari memasukkan terlalu banyak business logic ke Controller.

---

### 4. Database

Gunakan:
- MariaDB
- Eloquent
- migrations
- seeders
- factories

Gunakan foreign key dan indexing secara tepat.

Pertimbangkan:
- indexing kolom pencarian
- indexing foreign key
- unique constraint
- nullable hanya jika memang diperlukan
- database transaction untuk operasi multi-step

Jangan menggunakan SQLite sebagai database utama project.

---

### 5. Authentication & Authorization

Project memiliki role:

- administrator
- guru
- siswa

Authorization harus menggunakan mekanisme Laravel yang sesuai.

Jangan hanya menyembunyikan tombol/menu untuk membatasi akses.

Server-side authorization WAJIB diterapkan.

---

### 6. Security

Perhatikan:
- CSRF
- XSS
- SQL injection
- mass assignment
- authorization
- authentication
- rate limiting
- validation
- secure file upload
- password hashing
- session security
- audit logging jika diperlukan

Jangan menyimpan:
- password
- API key
- secret
- database credential

di source code.

Gunakan `.env`.

---

### 7. Frontend

Prioritaskan:
- responsive design
- mobile-first
- accessibility
- performance
- semantic HTML
- reusable components

UI harus modern, ringan, profesional, dan cocok untuk website sekolah.

Jangan menambahkan library frontend besar jika tidak diperlukan.

---

### 8. Performance

Target aplikasi harus mampu berkembang menuju:

±1000 concurrent users.

Gunakan secara tepat:
- Redis
- caching
- queue
- eager loading
- pagination
- database indexing
- optimized queries

Jangan melakukan premature optimization.

---

### 9. Testing

Setelah perubahan signifikan:

1. Jalankan test yang relevan.
2. Periksa migration.
3. Periksa route.
4. Periksa authorization.
5. Periksa validation.
6. Periksa error/log.

Jangan menganggap pekerjaan selesai hanya karena kode berhasil dibuat.

---

### 10. Dependency

Sebelum menambahkan package:

1. Pastikan Laravel belum menyediakan fitur tersebut.
2. Evaluasi apakah package benar-benar diperlukan.
3. Pilih package yang maintained.
4. Hindari dependency yang tidak perlu.

---

### 11. Git

Buat perubahan dalam unit yang jelas.

Gunakan commit yang deskriptif.

Contoh:

feat: add school profile management

fix: prevent unauthorized student access

refactor: extract admission service

Jangan melakukan force push.

Jangan menghapus history Git.

---

### 12. Workflow AI Agent

Sebelum mengimplementasikan fitur besar:

1. Inspect existing code.
2. Jelaskan pemahaman terhadap requirement.
3. Buat implementation plan.
4. Identifikasi file yang akan diubah.
5. Implementasikan secara bertahap.
6. Jalankan test.
7. Periksa hasil.
8. Laporkan perubahan.

Untuk perubahan database besar, berhenti dan minta persetujuan sebelum menjalankan destructive operation.

---

## PRIORITAS

Urutan prioritas:

1. Correctness
2. Security
3. Maintainability
4. Performance
5. User experience
6. Visual polish

Jangan mengorbankan security atau correctness demi kecepatan development.
