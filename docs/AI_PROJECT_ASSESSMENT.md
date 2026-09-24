# Sekolah Kita — AI Project Assessment

**Assessment type:** Read-only implementation and architecture assessment  
**Project root:** `/home/dev/workspace/sekolah-kita`  
**Assessment date:** 2026-09-24

## Audit scope and safety

This assessment was prepared after reading:

- `AGENTS.md`
- `PROJECT_SPEC.md`

The following project areas were inspected:

- Laravel project structure
- Routes and route registration
- Controllers
- Models and domain layer
- Migrations and live database metadata
- Seeders and factories
- Application configuration
- Frontend structure and existing UI
- Authentication
- Authorization
- Test setup
- Composer and NPM dependencies
- Database configuration
- Redis configuration
- Environment settings
- Existing UI/components
- Deployment and operations artifacts

The audit did not modify application code, configuration files, `.env`, migrations, seed data, or the database. No packages were installed. No migrations, seeders, tests, builds, queue workers, or scheduler workers were run. No files were deleted or overwritten.

The only intended repository change for this request is this assessment document.

### Runtime verification

Read-only runtime checks reported:

- PHP `8.5.4`
- Composer `2.10.3`
- Laravel Framework `13.33.0`
- Current database server: MariaDB `12.3.3`
- Laravel database connection name: `mysql`
- Database name: `sekolah_kita`
- Database tables: 9
- Database size: approximately 272 KB
- Existing migrations: all three reported as run in batch 1
- Redis endpoint: connection refused at `127.0.0.1:6379`
- PHP Redis extension: not installed
- Current cache, session, and queue drivers: database
- Current mail driver: `log`
- Current application environment: `local`
- Current debug mode: enabled
- Current locale: `en`
- Current timezone: UTC
- `public/storage`: not linked
- Scheduled tasks: none
- Pending jobs: none
- Failed jobs: none
- Composer dependency audit: no advisories found
- NPM production dependency audit: 0 vulnerabilities found

Selected non-secret environment settings were inspected. Credentials and application keys were not reproduced.

> Laravel Boost is not installed. The project instructions request installing it before application changes, but package installation was explicitly prohibited for this assessment.

---

# 1. What already exists

## 1.1 Laravel application foundation

The repository is a standard Laravel 13 application initialized from the Laravel skeleton. The project has the conventional directories for:

- `app/Http`
- `app/Models`
- `app/Providers`
- `bootstrap`
- `config`
- `database`
- `resources`
- `routes`
- `tests`
- `public`
- `storage`

`bootstrap/app.php:8-21` configures:

- Web routes from `routes/web.php`
- Console routes from `routes/console.php`
- A public health route at `/up`
- Laravel’s standard web middleware group
- Minimal JSON exception behavior for API-like requests

The custom middleware callback at `bootstrap/app.php:14-16` is empty. No custom middleware has been registered.

`app/Providers/AppServiceProvider.php` is also empty. There are no application-level gates, policies, observers, rate limiters, model bindings, or service registrations.

## 1.2 Basic user authentication model

`app/Models/User.php` contains the default Laravel user model.

Existing positive behavior:

- Extends `Illuminate\Foundation\Auth\User\Authenticatable`.
- Uses `HasFactory`.
- Uses `Notifiable`.
- Allows only `name`, `email`, and `password` through `#[Fillable]`.
- Hides `password` and `remember_token` through `#[Hidden]`.
- Casts `email_verified_at` to a datetime.
- Uses Laravel’s `hashed` cast for passwords.

The model has no role, account status, profile relationships, authorization behavior, or school relationships.

`config/auth.php` provides the default session guard, Eloquent user provider, password reset broker, token expiry, and password reset throttle configuration. These are framework defaults, not completed authentication flows.

## 1.3 Default framework tables

The existing migrations are:

- `database/migrations/0001_01_01_000000_create_users_table.php`
- `database/migrations/0001_01_01_000001_create_cache_table.php`
- `database/migrations/0001_01_01_000002_create_jobs_table.php`

They create framework infrastructure:

### Users and sessions

- `users`
- `password_reset_tokens`
- `sessions`

### Cache

- `cache`
- `cache_locks`

### Queue

- `jobs`
- `job_batches`
- `failed_jobs`

The live database also contains Laravel’s `migrations` table. No Sekolah Kita domain tables exist.

## 1.4 One public route and starter UI

`routes/web.php:5-7` contains one route:

```php
Route::get('/', function () {
    return view('welcome');
});
```

The only project view is `resources/views/welcome.blade.php`, which is the default Laravel welcome page. It contains Laravel branding, documentation links, generic starter content, and a large inline Laravel graphic.

The frontend foundation consists of:

- Vite
- Laravel Vite plugin
- Tailwind CSS 4
- Instrument Sans font configuration
- `resources/css/app.css`
- `resources/js/app.js`

`resources/js/app.js` contains only a comment, so there is no application JavaScript behavior.

## 1.5 Test harness

The repository contains:

- PHPUnit 12
- `tests/TestCase.php`
- `tests/Unit/ExampleTest.php`
- `tests/Feature/ExampleTest.php`

The unit test only asserts that `true` is true. The feature test only checks that `/` returns HTTP 200.

`phpunit.xml` configures fast test defaults:

- In-memory SQLite
- Array cache
- Array session
- Synchronous queue
- Array mail
- Bcrypt rounds set to 4

## 1.6 Configuration scaffolding

Standard configuration exists for:

- Application
- Authentication
- Cache
- Database
- Filesystem
- Logging
- Mail
- Queue
- Services
- Session

The configuration already contains alternatives for MariaDB, Redis cache, Redis queues, Redis sessions, S3-compatible storage, and multiple mail providers. These are unused framework capabilities at present.

---

# 2. What is missing

The modules listed in `PROJECT_SPEC.md:32-49` have not been implemented.

| Specification module | Current status |
|---|---|
| Public Website | Only the Laravel welcome page exists |
| Authentication | Base model/config only; no login or logout flow |
| User Management | Absent |
| School Profile | Absent |
| Berita | Absent |
| Prestasi | Absent |
| Galeri | Absent |
| Guru | No domain model, routes, controllers, or policies |
| Siswa | No domain model, routes, controllers, or policies |
| PPDB | Absent |
| Contact | Absent |
| Dashboard | Absent |
| Notification | `Notifiable` exists, but no notification classes or migration |
| Audit Log | Absent |
| Cache | Framework tables/config only; no application cache usage |
| Queue | Framework tables/config only; no jobs or workers |

Additional missing foundations include:

- API route boundary, if required
- CORS policy, if API is required
- Role and permission definitions
- Policies and gates
- Form Requests
- Middleware
- Services/actions
- Jobs
- Events/listeners
- Notifications
- API Resources, if API is required
- Observers or domain events
- Error/exception presentation
- Media abstraction
- Audit service
- Scheduled tasks
- CI/CD
- Deployment configuration
- Load-test configuration

## 2.1 Public website gaps

There are no pages for:

- Sekolah Kita home page
- School profile
- Public news listing
- News detail
- Achievements
- Gallery
- Teacher directory
- Student directory, if approved
- PPDB public information
- Contact page
- Public announcements
- Privacy or retention information

There is no project branding, public navigation, footer, SEO metadata, sitemap, Open Graph metadata, or structured school information.

## 2.2 Authentication gaps

There are no:

- Login controller or route
- Logout controller or route
- Registration flow
- Password reset routes or views
- Email verification flow
- Password change flow
- Account activation/deactivation flow
- Invitation flow
- Login throttling
- Account lockout
- MFA
- API authentication
- Authenticated route group

The default welcome page conditionally checks for a `login` route, but no such route exists.

## 2.3 Authorization gaps

The project specification defines these roles:

- Administrator
- Guru
- Siswa

The application has no representation or enforcement for any of them. There are no:

- Role columns or role tables
- Permission matrix
- Policies
- Gates
- Role middleware
- Ownership checks
- Teacher/class scope
- Student self-access scope
- Administrator-only policies
- Authorization tests

## 2.4 Domain gaps

There are no models, migrations, factories, seeders, controllers, requests, services, or views for:

- School profile
- Guru
- Siswa
- Guardians
- Academic years
- Terms/semesters
- Classes
- Subjects
- Teaching assignments
- Enrollments
- Attendance, if required
- Grades, if required
- Schedules, if required
- Berita
- Prestasi
- Gallery albums
- Gallery items
- Media assets
- PPDB periods
- PPDB applications
- Guardians
- Application documents
- Contact messages
- Notifications
- Audit logs

## 2.5 Operations gaps

The repository has no visible deployment or operations artifacts for:

- Nginx
- PHP-FPM
- Queue workers
- Scheduler workers
- Supervisor/systemd
- HTTPS
- Security headers
- Backups
- Restore procedures
- Log rotation deployment
- Redis authentication/TLS
- Monitoring
- Error reporting
- CI/CD

The existing `public/.htaccess` is an Apache rewrite file and does not configure the intended Nginx production environment.

---

# 3. Potential architectural problems

## 3.1 Specification and implementation are disconnected

`PROJECT_SPEC.md` is high-level and explicitly says detailed module requirements will be determined later. The current code contains no corresponding domain implementation.

The main architectural risk is implementing modules without first defining:

- Module boundaries
- Data ownership
- Role permissions
- PPDB workflow
- Academic structure
- Public/private data rules
- Media privacy
- Notification behavior
- Audit requirements

## 3.2 No application module structure

The application has no logical modules or domain folders. If all future behavior is placed directly in controllers, the result will likely become difficult to maintain and insecure.

A modular monolith is recommended. Each module should own its:

- Controllers
- Form Requests
- Policies
- Services/actions
- Models
- Views
- Tests
- Jobs
- Notifications

Microservices are not justified at the current scale or project maturity.

## 3.3 Database configuration drift

The project requirements specify MariaDB and database `sekolah_kita`, but:

- `.env.example:23-28` defaults to SQLite.
- `config/database.php:20` falls back to SQLite when `DB_CONNECTION` is absent.
- `config/database.php:67-85` contains a MariaDB connection, but its default database name is `laravel`.
- The current environment uses connection name `mysql` against a MariaDB server.

The current setup can work, but configuration naming is inconsistent and a fresh setup can silently initialize SQLite instead of MariaDB.

## 3.4 Dangerous setup scripts

`composer.json:35-41` defines a `setup` script that includes `php artisan migrate --force`.

`composer.json:61-64` also creates `database/database.sqlite` and runs migrations during project creation.

These generic scripts should not be used as production deployment procedures. Production deployment needs an explicit, reviewed release process.

## 3.5 Database-backed shared infrastructure

Sessions, cache, and queues currently all use MariaDB through the database driver. This is a potential source of contention because application queries, session writes, cache reads/writes, and queue polling share the same database.

This is acceptable for a low-traffic starter, but Redis should become the shared production infrastructure for sessions, cache, rate limits, and queues.

## 3.6 Route and controller design

The root route is an anonymous closure. Future business routes should use controllers or invokable actions.

Benefits include:

- Testability
- Route caching
- Consistent middleware
- Thin controllers
- Reusable authorization and validation
- Clear module ownership

## 3.7 API ambiguity

`bootstrap/app.php` contains JSON exception handling for `api/*`, but there is no `routes/api.php`, no API middleware registration, no CORS configuration, and no API authentication package.

The project should explicitly choose between:

- Web-only application for the first release
- Versioned API for a future mobile/third-party integration

Adding API routes without an authentication, versioning, rate-limit, and resource strategy would be premature.

## 3.8 Test/production divergence

The test configuration uses SQLite in memory. This is useful for fast tests but does not validate:

- MariaDB behavior
- Foreign-key behavior
- MariaDB indexes
- Full-text search
- Transaction behavior
- Redis sessions
- Redis cache
- Redis queues
- Production migration behavior

A separate MariaDB/Redis integration test environment will be required.

## 3.9 Single-school versus multi-school ambiguity

The specification describes one school but does not explicitly decide whether the system will support multiple schools.

This decision must be made before major migrations:

- If single-school only, avoid unnecessary tenancy complexity.
- If multiple schools may be supported, add a school entity and `school_id` to tenant-owned tables before meaningful data is created.

Retrofitting multi-school isolation later is expensive.

## 3.10 No operational process architecture

The target stack is Nginx, PHP-FPM, MariaDB, Redis, and HTTPS, but the repository does not define:

- PHP-FPM sizing
- OPcache policy
- Nginx routing
- Static asset caching
- Queue supervision
- Scheduler execution
- Log collection
- Health/readiness behavior
- Backup and restore procedures
- Release rollback

---

# 4. Security concerns

## 4.1 Existing positive controls

The following controls are already present:

- Password hashing through the `hashed` cast.
- Password and remember token hidden from serialization.
- Restricted fillable attributes.
- Escaped Blade output in the current view.
- JSON session serialization.
- `cache.serializable_classes => false`.
- `.env`, `.env.backup`, SQLite, vendor, logs, and generated build assets are ignored by Git.
- Default Eloquent parameterization is used; no raw SQL was found in application source.
- No `eval`, `unserialize`, shell execution, or unsafe raw HTML pattern was found in the inspected source.

## 4.2 High-priority concerns

### Debug and HTTP defaults

The current local environment has:

- `APP_DEBUG=true`
- `APP_URL=http://localhost:8000`
- `LOG_LEVEL=debug`
- `APP_NAME=Laravel`

If copied into production, these values can expose stack traces, environment information, query details, and sensitive log data.

Production must explicitly use:

- `APP_ENV=production`
- `APP_DEBUG=false`
- HTTPS
- Correct application name and URL
- Production-appropriate log levels
- Centralized or rotated logs

### Fixed seeded credential

`database/factories/UserFactory.php:31` uses the known literal password `password` for test data.

`database/seeders/DatabaseSeeder.php:20-23` defines a fixed account:

- Email: `test@example.com`
- Name: `Test User`
- Password: inherited from the factory

The seeder is not environment-aware and is not safe to run in production. The first administrator must be provisioned through a controlled process, not a default seeder.

### Missing authentication and authorization

There are no login routes, authenticated route groups, policies, or role checks. This is not an active bypass of existing protected functionality because no protected business routes exist, but it becomes critical as soon as dashboard or admin routes are added.

### No rate limiting

No application rate limiters were found for:

- Login
- Password reset
- Contact submissions
- PPDB submissions
- Search
- Uploads
- Future API endpoints

Rate limiting should use Redis in production.

### No audit trail

Administrative actions cannot currently be attributed or reconstructed.

An audit system should record:

- Actor
- Action
- Target type and ID
- Before/after values
- Timestamp
- IP address or privacy-safe representation
- User agent
- Request/correlation ID

Passwords, tokens, raw identity documents, and unnecessary personal data must not be logged.

### File upload and media security

No upload endpoint currently exists, so there is no active upload vulnerability. Future gallery, profile, and PPDB uploads require:

- MIME and extension validation
- Image content verification
- File-size limits
- Randomized filenames
- Private storage for personal documents
- Authorization checks
- Malware scanning where appropriate
- Metadata sanitization
- Secure temporary URLs
- Download auditing

`config/filesystems.php:33-47` configures the local disk with `serve => true`. Private files should be served only through valid signed/temporary URLs. Sensitive documents must never be placed on the public disk.

### Security headers

The repository has the default Apache `.htaccess`, but no production Nginx configuration or application security-header policy.

The deployment layer should define at least:

- HTTPS and HSTS
- Content Security Policy
- `X-Content-Type-Options: nosniff`
- Referrer Policy
- Frame protection
- Permissions Policy
- Trusted proxy configuration
- Secure cookie behavior

### Session security

Current session configuration includes:

- Database driver
- 120-minute lifetime
- `http_only=true`
- `same_site=lax`
- JSON serialization
- Session encryption disabled by default
- Secure cookie setting not explicitly enabled

For production:

- Use HTTPS-only cookies.
- Regenerate the session after login.
- Define idle and absolute timeouts.
- Keep session payloads small.
- Avoid sensitive data in sessions.
- Consider Redis sessions for horizontal scaling.
- Avoid local file sessions on multiple app nodes.

### Privacy and data governance

No implementation exists for:

- Student-data access boundaries
- Consent
- Data retention
- Data deletion
- Export controls
- Sensitive-read auditing
- Backup encryption
- Data classification

These are required before storing real student, guardian, or admission data.

## 4.3 Dependency audit

Point-in-time checks found:

- No Composer security advisories.
- 0 NPM production vulnerabilities.

This is positive, but dependency auditing should be automated in CI. Secure coding, authorization, configuration, and data governance remain necessary.

---

# 5. Database design requirements

The current database is only a Laravel framework schema. A Sekolah Kita schema should be designed before feature migrations are created.

## 5.1 General requirements

1. Keep authentication data separate from teacher and student profiles.
2. Use MariaDB as the primary development and production database.
3. Use `utf8mb4`, strict mode, and InnoDB.
4. Add foreign keys explicitly with deliberate delete behavior.
5. Index all foreign keys.
6. Index status, date, search, and frequently filtered columns.
7. Add unique constraints for email, NIP, student number, registration number, and slugs.
8. Use database transactions for multi-step workflows.
9. Use status histories for PPDB and other stateful processes.
10. Use soft deletion or explicit status transitions instead of hard deletion for personal records.
11. Store media binaries outside the database.
12. Redact sensitive values from audit snapshots.
13. Use append-only migrations after the current migrations have been deployed.
14. Do not edit or remove migrations already used in an existing environment.

## 5.2 Current schema observations

The live `users` table has:

- `id`
- `name`
- `email`
- `email_verified_at`
- `password`
- `remember_token`
- `created_at`
- `updated_at`

The only reported index is the unique email index.

The `sessions.user_id` column is indexed but does not have a foreign-key constraint because the migration uses `foreignId()->index()` rather than `constrained()`.

The database uses:

- InnoDB
- `utf8mb4_unicode_ci`

This is a reasonable starting point for Indonesian names, but application-level normalization and validation are still required.

## 5.3 Important unresolved database decisions

Before migrations are written, decide:

- Single-school or multi-school architecture
- Whether parents/guardians need accounts
- Whether grades, attendance, schedules, and extracurricular activities are in scope
- How students move between classes and academic years
- Whether student information can be public
- Whether deleted users must retain historical records
- Grade data types
- PPDB periods, quotas, and selection rules
- Required PPDB documents
- Media retention and deletion policy
- Student-data retention and export policy

---

# 6. Authentication and RBAC requirements

## 6.1 Authentication flows

The initial implementation should provide:

- Login
- Logout
- Password reset
- Email verification for staff and administrators
- User profile
- Password change
- Account activation/deactivation
- Administrator-managed invitations
- Session regeneration after login
- Login throttling
- Optional administrator MFA

Public registration should not automatically create an unrestricted application user. PPDB applicants should use a separate controlled registration flow.

## 6.2 Initial role model

Because the specification currently defines only three roles, a fixed internal role representation is sufficient initially:

- `administrator`
- `guru`
- `siswa`

Use stable internal codes and separate display labels. Policies should not depend on translated display strings.

If custom roles or fine-grained permissions become necessary, introduce normalized `roles`, `permissions`, and relationship tables then. A permission package is not required for the first implementation.

## 6.3 Proposed permission matrix

This is a proposed starting point and should be confirmed before implementation.

| Capability | Administrator | Guru | Siswa |
|---|---|---|---|
| School profile | Manage | View | View |
| User accounts | Manage | Own account | Own account |
| Teacher data | Manage | Own/assigned data | None |
| Student data | Manage | Assigned classes only | Own data only |
| News | Manage and publish | Create/edit assigned drafts if permitted | View published |
| Achievements | Manage | Create/edit assigned entries if permitted | View published |
| Gallery | Manage | Upload if permitted | View published |
| PPDB | Manage and review | None | Public applicant flow or own application |
| Notifications | Own and broadcast | Own | Own |
| Audit logs | View | None | None |
| System settings | Manage | None | None |

## 6.4 Server-side enforcement

Every protected operation should use:

- Authentication middleware
- Form Request authorization
- Policies
- Explicit query scoping
- Route model binding combined with policy checks
- Ownership validation

Examples:

- A Guru may access only students in assigned classes.
- A Siswa may access only their own profile and records.
- Administrator permissions must be explicit and auditable.
- Hiding a menu item is not authorization.

---

# 7. Performance requirements for approximately 1,000 concurrent users

The target “1,000 concurrent users” must be defined precisely. It could mean:

- 1,000 authenticated sessions
- 1,000 simultaneous browser users
- 1,000 simultaneous requests
- A lower request rate with many idle sessions

These are different capacity requirements.

## 7.1 Application tier

Recommended baseline:

- Nginx with HTTPS and HTTP/2 or HTTP/3
- PHP-FPM with OPcache
- Stateless Laravel nodes
- Load balancer when more than one node is needed
- Production config, route, and view caches
- `APP_DEBUG=false`
- Structured centralized logging

Convert the root closure to a controller before relying on route caching.

## 7.2 Database tier

- MariaDB/InnoDB as source of truth
- Indexes for relationships, filters, dates, and searches
- Eager loading to prevent N+1 queries
- Pagination for all large tables
- Selective columns instead of broad `select *`
- Query budgets for dashboards and list pages
- Slow-query monitoring
- Connection limits aligned with PHP-FPM workers
- Read replicas only after measuring read-heavy workloads
- Regular backups and tested restoration

## 7.3 Cache strategy

Cache suitable read-heavy data:

- School profile
- Public navigation
- Published news lists
- Published achievements
- Gallery metadata
- Public landing-page queries

Do not globally cache personalized dashboards or student data.

Use:

- Explicit TTLs
- Cache versioning or tag invalidation
- Cache warming after deployment
- Locking or stale-while-revalidate behavior for expensive regeneration

## 7.4 Queue strategy

Move non-request work out of the web request:

- Email
- Notifications
- Image thumbnails
- Media validation
- Imports
- Exports
- PPDB document processing
- Announcement delivery
- Audit exports

Queue workers must be supervised and monitored.

## 7.5 Media strategy

For gallery and PPDB documents:

- Use object storage or a private disk.
- Serve public images through a CDN or Nginx.
- Generate thumbnails asynchronously.
- Avoid sending large files through PHP-FPM.
- Use signed URLs for private documents.

## 7.6 Capacity validation

Before making a capacity commitment, run staging load tests with:

- Representative student, teacher, news, gallery, and PPDB data
- Authenticated and anonymous traffic
- Peak login/session traffic
- PPDB submission bursts
- Concurrent media requests
- Realistic cache hit/miss ratios

Measure:

- Requests per second
- p50/p95/p99 latency
- Error rate
- PHP-FPM saturation
- MariaDB connections and slow queries
- Redis latency and memory
- Queue depth and oldest job age
- Worker memory usage

A small single VPS should not be assumed to support 1,000 simultaneous active requests without measurement.

---

# 8. Redis, cache, and queue requirements

## 8.1 Current state

Redis configuration exists in `config/database.php:146-182`, including:

- `phpredis` client
- Default Redis connection
- Separate cache connection
- Database numbers
- Username/password support
- Retry and backoff settings
- Key prefixing

However:

- PHP Redis extension is not installed.
- The local Redis server refused connections.
- Current session, cache, and queue drivers are database.
- No application code uses Redis.
- No worker or deployment configuration exists.
- No Redis TLS, ACL, or production network documentation exists.

## 8.2 Production target

Use Redis for:

- Sessions
- Application cache
- Queue jobs
- Rate limiting
- Distributed locks

Likely future settings include:

- `CACHE_STORE=redis`
- `SESSION_DRIVER=redis`
- `QUEUE_CONNECTION=redis`
- Explicit cache and queue Redis connections
- Environment-specific prefixes
- Redis authentication
- Private network or TLS
- Memory and eviction policy appropriate for each workload

Redis logical databases are not strong security isolation. For high reliability, use separate Redis instances or managed services for cache, queue, and sessions/rate limits.

Queue persistence must be considered carefully. An eviction policy appropriate for cache can remove queued jobs if the same instance is used without separation.

## 8.3 Queue design

Each job should have:

- Explicit queue name
- Retry count
- Backoff
- Timeout
- Idempotency behavior
- Failure handling
- Monitoring
- Cleanup strategy

`config/queue.php` currently has `after_commit=false`. Transactional workflows should dispatch jobs after commit or use transaction-aware dispatching.

Recommended queue groups:

- `mail`
- `notifications`
- `media`
- `ppdb`
- `exports`
- `maintenance`

Laravel’s built-in queue monitoring can be used initially. A dedicated dashboard such as Horizon is optional and should only be added when operational requirements justify it.

---

# 9. Frontend and UI requirements

## 9.1 Current frontend

The current source files are:

- `resources/views/welcome.blade.php`
- `resources/css/app.css`
- `resources/js/app.js`
- `vite.config.js`

`welcome.blade.php` is the default Laravel page, not a Sekolah Kita interface. It contains:

- Laravel branding
- Laravel documentation links
- Laracasts links
- Generic starter content
- English text
- Large inline Laravel artwork
- Stale conditional references to `/dashboard`, `login`, and `register`

No reusable layouts or components were found under:

- `resources/views/layouts`
- `resources/views/components`

No Alpine, Livewire, Vue, React, Inertia, or component library is installed.

Generated assets exist under `public/build`, but the directory is ignored by Git. The current generated JavaScript asset is zero bytes because `resources/js/app.js` has no application code. A deployment must build assets separately.

## 9.2 Recommended UI approach

Use server-rendered Blade with the existing Vite/Tailwind foundation for the first implementation.

This is appropriate because:

- The public site needs SEO.
- Initial forms are mostly standard CRUD workflows.
- A large JavaScript framework is not currently required.
- Blade, Vite, and Tailwind are already configured.

Add small amounts of progressive JavaScript only where interaction requires it. Select a larger SPA framework only after dashboard requirements are known.

## 9.3 Reusable component requirements

Create reusable Blade components for:

- Public layout
- Dashboard layout
- Navigation
- Sidebar
- Breadcrumb
- Button
- Form input
- Select
- Textarea
- File input
- Validation summary
- Alert/flash message
- Card
- Table
- Pagination
- Empty state
- Loading state
- Status badge
- Confirmation dialog
- Notification item

## 9.4 Public website requirements

The public site should include:

- School identity and branding
- Responsive header and footer
- School profile
- News
- Achievements
- Gallery
- Teacher information
- Student information only if privacy policy permits
- PPDB information
- Contact form
- SEO title and description
- Open Graph metadata
- Canonical URLs
- Sitemap
- Accessible images with alt text
- Indonesian language and date formatting

## 9.5 Dashboard requirements

### Administrator

- User and account management
- School profile
- Content publishing
- PPDB review
- Notifications
- Audit overview
- System health indicators

### Guru

- Assigned classes
- Assigned students
- Relevant academic data
- Content drafts
- Notifications
- Own profile

### Siswa

- Own profile
- Own academic information
- Own notifications
- Published school information
- No access to other students’ private data

## 9.6 Accessibility and responsive requirements

Target WCAG 2.2 AA where practical:

- Semantic HTML
- Keyboard navigation
- Visible focus states
- Proper labels
- Accessible error messages
- Screen-reader-friendly navigation
- Color contrast
- Reduced-motion support
- Responsive layouts for Android/mobile browsers
- No client-side-only authorization decisions

---

# 10. Recommended implementation phases in dependency order

## Phase 0 — Requirements and architecture decisions

Before writing migrations:

- Confirm single-school versus multi-school design.
- Confirm role definitions and permission matrix.
- Decide whether guardians need accounts.
- Confirm academic scope.
- Define PPDB workflow and statuses.
- Define public versus private student information.
- Define media and document retention.
- Confirm notification channels.
- Confirm locale, timezone, date, and number formats.
- Define performance SLOs and backup requirements.
- Decide whether an API or mobile application is required.

Deliverables:

- Approved domain and authorization specification.
- Approved data classification and privacy rules.
- Approved nonfunctional requirements.

## Phase 1 — Application and infrastructure foundation

Future work would include:

- Align `.env.example` with MariaDB and Redis.
- Standardize the MariaDB connection name.
- Define production cache/session/queue drivers.
- Add secure cookie, HTTPS, trusted proxy, and logging settings.
- Add application name, locale, timezone, and branding.
- Add deployment documentation for Nginx, PHP-FPM, workers, and scheduler.
- Add CI for tests, formatting, dependency audits, and builds.
- Establish code standards and test conventions.
- Replace the root closure with a controller.
- Replace the default Laravel welcome page.
- Add base layouts, reusable components, error pages, and health/readiness behavior.

## Phase 2 — Identity and RBAC

- Extend `users` with role and account status.
- Add teacher and student profile relationships.
- Implement login/logout.
- Implement password reset and email verification.
- Implement account invitation and deactivation.
- Add role middleware.
- Add policies and gates.
- Add rate limiting.
- Add secure administrator provisioning.
- Add authentication and authorization tests.

## Phase 3 — School directory and academic context

- Create school profile.
- Create teacher and student domain models.
- Create academic years, terms, classes, subjects, and assignments if confirmed.
- Create enrollment workflows.
- Implement role-scoped dashboards.
- Add factories, seeders, and policy tests.
- Add privacy controls for student data.

## Phase 4 — Public content and media

- School profile public page.
- News and achievement publishing workflow.
- Gallery albums and media uploads.
- Public teacher directory.
- Contact form and moderation.
- SEO metadata and sitemap.
- Public cache invalidation.
- Private/public storage separation.
- Media validation and asynchronous processing.

## Phase 5 — PPDB

- Admission periods and public information.
- Application form and validation.
- Guardian data.
- Private document uploads.
- Anti-spam and rate limiting.
- Application review workflow.
- Status history and audit logging.
- Acceptance-to-student conversion.
- Enrollment creation.
- Applicant notifications.

## Phase 6 — Notifications, audit, and operations

- Notification center.
- Queued email delivery.
- Administrator announcements.
- Audit log implementation.
- Scheduled cleanup and retention tasks.
- Report/export jobs.
- Queue monitoring and alerts.
- Backup and restore procedures.

## Phase 7 — Performance and security hardening

- Redis production setup.
- Queue worker and scheduler deployment.
- Database index and query review.
- Cache profiling.
- Object storage/CDN setup.
- Load testing with realistic data.
- Security header and Nginx review.
- Dependency and container scanning.
- Backup restoration test.
- Incident and rollback runbook.

---

# Current project assessment

The repository has a valid Laravel 13 foundation but no Sekolah Kita business implementation.

Current readiness:

- **Framework foundation:** Usable
- **Authentication foundation:** Partial configuration only
- **Authorization:** Not implemented
- **Domain model:** Not implemented
- **Public website:** Not implemented
- **Production readiness:** Not ready
- **Security readiness:** Baseline only
- **Scalability readiness:** Unverified
- **Testing readiness:** Minimal scaffold only

The main implementation blocker is not framework capability. It is the absence of approved domain, security, tenancy, and operational decisions.

The next implementation increment should be requirements clarification plus secure identity/RBAC and infrastructure foundation. Feature migrations should follow only after those decisions are approved.

---

# Proposed architecture

Use a **modular monolith** rather than microservices.

Recommended logical modules:

1. **Identity and Access**
   - Users
   - Roles
   - Authentication
   - Policies
   - Invitations
   - Account lifecycle

2. **School**
   - School profile
   - Public identity
   - School settings

3. **People**
   - Guru
   - Siswa
   - Guardians
   - Profile data

4. **Academic**
   - Academic years
   - Terms
   - Classes
   - Subjects
   - Teacher assignments
   - Enrollments
   - Optional attendance and grades

5. **Content and Media**
   - Berita
   - Prestasi
   - Gallery
   - Media assets
   - Publishing workflow

6. **Admissions**
   - PPDB periods
   - Applications
   - Guardians
   - Documents
   - Review workflow
   - Enrollment conversion

7. **Communication**
   - Contact messages
   - Notifications
   - Email delivery
   - Announcement broadcasts

8. **Platform**
   - Audit logs
   - Queue jobs
   - Cache invalidation
   - Reports
   - Operational commands

## Runtime topology

```text
Browser / Public Users / Staff / Students
                  |
               HTTPS
                  |
               Nginx
        Static assets, TLS, headers
                  |
       Stateless Laravel / PHP-FPM
          |          |          |
       MariaDB     Redis     Object Storage
       source of    cache,     media,
       truth       sessions,
                   queues,
                   rate limits
                  |
          Queue workers / Scheduler
                  |
          Mail and background jobs
```

## Application conventions

- Controllers remain thin.
- Form Requests handle validation and authorization.
- Policies handle model-level authorization.
- Services/actions handle multi-step workflows.
- Eloquent models represent domain data.
- Jobs handle asynchronous side effects.
- Notifications handle user-facing messages.
- Database transactions protect multi-step writes.
- MariaDB remains the source of truth.
- Redis is shared infrastructure, not permanent business storage.
- Blade is used for initial server-rendered pages.
- API routes are added only if mobile or third-party integration is required.

---

# Proposed database entities

The following is a conceptual design only. No migrations were created.

## Identity and access

| Entity | Important fields and relationships |
|---|---|
| `users` | Extend the existing table with role, status, last login, locale, and lifecycle fields |
| `teachers` | One-to-one `user_id`, unique NIP, personal/academic profile |
| `students` | One-to-one optional `user_id`, unique student number, identity and enrollment data |
| `user_invitations` | Email, role, token, expiry, inviter, accepted timestamp |
| `roles` | Optional if custom roles are required |
| `permissions` | Optional if fine-grained permissions are required |
| `role_user` | Optional many-to-many role assignment |
| `permission_role` | Optional role-permission mapping |

For the initial fixed three-role scope, a controlled role column on `users` is simpler. Normalized RBAC should be introduced only if custom roles are confirmed.

## School and academic data

| Entity | Important fields and relationships |
|---|---|
| `school_profiles` | Singleton school identity, address, contact, vision/mission, logo, social links |
| `academic_years` | Start/end dates and active status |
| `terms` | Belongs to academic year; start/end dates; active status |
| `classes` | Academic year, grade level, name, homeroom teacher |
| `subjects` | Subject name, code, description |
| `teaching_assignments` | Teacher, class, subject, academic year |
| `enrollments` | Student, class, academic year, status, enrollment date |
| `class_teachers` | Additional explicit teacher/class relationship if required |

Academic entities beyond the basic directory should be confirmed against detailed requirements.

## Public content and media

| Entity | Important fields and relationships |
|---|---|
| `news_articles` | Title, slug, body, status, author, publication time, SEO fields |
| `news_categories` | Category name and slug |
| `news_category_article` | Article/category relationship |
| `tags` | Optional content tags |
| `achievements` | Title, description, category, level, date, publisher, status |
| `achievement_recipients` | Explicit student/teacher recipient relationships |
| `gallery_albums` | Title, description, cover, status |
| `gallery_items` | Album, media asset, caption, date, sort order |
| `media_assets` | Disk, path, original name, MIME, size, checksum, visibility, uploader |
| `media_variants` | Thumbnail and responsive image variants |

For achievement recipients, explicit foreign keys are preferable to an unconstrained polymorphic relationship when referential integrity is important.

## PPDB

| Entity | Important fields and relationships |
|---|---|
| `admission_periods` | Academic year, opening/closing dates, quota, status |
| `admission_applications` | Opaque registration number, applicant data, status, submitted time |
| `application_guardians` | Guardian identity and contact information |
| `application_documents` | Document type, private media asset, verification status |
| `application_status_histories` | Previous status, new status, actor, note, timestamp |
| `admission_announcements` | Public PPDB information and announcements |
| `admission_reviews` | Reviewer, decision, notes, reviewed timestamp |

Acceptance should use a transaction that validates state, records the transition, creates or links the student, creates enrollment, records audit, and queues notification.

## Communication and platform

| Entity | Important fields and relationships |
|---|---|
| `contact_messages` | Name, contact details, subject, message, moderation status |
| `notifications` | Laravel database notification data and read state |
| `notification_preferences` | User-level channel/category preferences |
| `audit_logs` | Actor, action, target, before/after data, request metadata |
| `site_settings` | Only for settings not belonging to the school profile |
| `failed_jobs` | Existing Laravel infrastructure |
| `jobs` | Existing Laravel infrastructure, operationally replaced by Redis queue |

## Index and constraint requirements

Plan indexes for:

- Normalized unique email
- Teacher NIP
- Student number
- User role and status
- Class and academic year
- Enrollment uniqueness
- News status and publication date
- Unique news slug
- Achievement date and category
- Gallery album and sort order
- Media disk/path uniqueness
- Admission registration number
- Admission status and submission time
- Notification recipient and read state
- Audit actor and timestamp
- Audit target and timestamp

Foreign keys should use explicit `constrained()` relationships and deliberate `cascade`, `restrict`, or `nullOnDelete` behavior.

---

# Implementation roadmap

## Phase 0 — Requirements and decisions

Before creating any migration:

- Confirm school tenancy.
- Confirm the role/permission matrix.
- Decide whether guardians need accounts.
- Confirm academic features.
- Define PPDB statuses and transitions.
- Define public and private student-data rules.
- Define media and document retention.
- Confirm notification channels.
- Confirm locale and timezone.
- Define performance SLOs.
- Define backup, restore, and retention requirements.
- Decide whether an API/mobile client is required.

## Phase 1 — Secure foundation

- Align the example environment with MariaDB and Redis.
- Define production settings for sessions, cache, queues, mail, storage, logging, and secure cookies.
- Add Nginx/PHP-FPM/worker/scheduler documentation.
- Add CI for tests, formatting, builds, and dependency audits.
- Replace the closure route and starter page.
- Create base layouts, reusable components, error pages, and health/readiness behavior.

## Phase 2 — Identity and RBAC

- Add role and account status to the user model through an approved migration.
- Implement login/logout/reset/verification.
- Implement invitations and account deactivation.
- Add policies, gates, middleware, and rate limits.
- Add secure administrator provisioning.
- Add authentication and authorization tests.

## Phase 3 — School and people

- Add school profile.
- Add Guru and Siswa profiles.
- Add academic context if confirmed.
- Add role-scoped dashboards.
- Add factories, seeders, privacy controls, and policy tests.

## Phase 4 — Public content

- Add public school profile.
- Add Berita and Prestasi workflows.
- Add gallery and media metadata.
- Add teacher directory.
- Add Contact moderation.
- Add SEO and public caching.
- Add private/public storage separation.

## Phase 5 — PPDB

- Add admission periods and public information.
- Add application and guardian forms.
- Add private document handling.
- Add anti-abuse controls.
- Add review and status history.
- Add acceptance-to-student conversion.
- Add applicant notifications.

## Phase 6 — Platform capabilities

- Add notification center and email queue.
- Add audit logging.
- Add scheduled cleanup and retention tasks.
- Add export/report jobs.
- Add queue monitoring and operational alerts.

## Phase 7 — Scale and hardening

- Deploy Redis and supervised workers.
- Tune indexes and queries using measurements.
- Configure object storage/CDN.
- Run realistic load tests.
- Review Nginx and security headers.
- Run dependency, container, and application security checks.
- Test backup restoration.
- Document incident response and rollback.

---

# Risks and recommendations

## Highest-priority risks

1. **Unresolved domain requirements** — modules are listed without workflows, permissions, or acceptance criteria.
2. **Authentication/RBAC absent** — dashboards and admin routes must not be added before server-side authorization exists.
3. **SQLite defaults conflict with MariaDB requirements** — fresh setup can initialize the wrong database.
4. **Redis is not operational** — PHP Redis extension and service availability are missing locally.
5. **Default seeder contains a predictable credential** — it must never be used in production.
6. **No PII/media security model** — gallery, student, guardian, and PPDB data require private storage and access controls.
7. **No meaningful tests or CI** — the current tests only validate starter behavior.
8. **No production operations definition** — queue workers, scheduler, Nginx, HTTPS, backups, monitoring, and log rotation are missing.
9. **Tenancy is undecided** — adding or removing school scoping later will be costly.
10. **Performance target is undefined** — 1,000 concurrent users must be converted into measurable workload and SLOs.

## Recommendations

- Treat the current database as an initialized framework baseline, not a completed application schema.
- Do not run `migrate:fresh`, `migrate:reset`, `db:wipe`, or similar commands against the existing database.
- Do not use the generic `composer setup` command in production.
- Make MariaDB the documented development and production default.
- Keep SQLite limited to fast unit tests or isolated experiments.
- Implement authentication and policies before dashboards.
- Use native Laravel authorization first; avoid packages until requirements justify them.
- Use Blade and the existing Vite/Tailwind foundation for the first UI.
- Use Redis for sessions, cache, rate limiting, and queues in production.
- Use object storage and signed URLs for private documents.
- Treat audit logging, privacy, backups, and observability as platform requirements.
- Define “1,000 concurrent users” as a measurable workload before making capacity claims.
- Add MariaDB and Redis integration tests before production.
- Keep dependencies minimal and evaluate Laravel built-in capabilities first.
- Create only additive migrations after the current migration baseline is approved.

## Final recommendation

The next implementation phase should be requirements clarification plus secure identity/RBAC and infrastructure foundation. Business migrations and feature modules should follow only after the domain, tenancy, privacy, and permission decisions are approved.
