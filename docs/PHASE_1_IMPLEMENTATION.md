# Phase 1 Implementation — Secure Foundation

**Project:** Sekolah Kita  
**Implementation date:** 2026-09-24  
**Scope:** Identity and authentication foundation only  
**Status:** Scoped implementation passes its automated tests. The additive migration is intentionally pending review and has not been applied to the existing MariaDB database.

## 1. Scope and boundaries

This phase implements the application foundation and identity/authentication infrastructure. It does **not** implement students, teachers, guardians, academic classes, enrollment, PPDB, news, achievements, gallery, or other public-content/domain modules.

No public registration flow was added. User accounts are intended to be provisioned through a controlled administrator process.

## 2. Baseline inspected before implementation

The following were inspected before application changes:

- Laravel `13.33.0`, PHP `8.5.4`, Composer `2.10.3`.
- The default `User` model and the three existing framework migrations.
- The live MariaDB `users`, `password_reset_tokens`, and `sessions` schema.
- `config/auth.php`, session configuration, `bootstrap/app.php`, routes, middleware, providers, factories, seeders, tests, and Composer dependencies.
- The application had no login/logout controllers, password-reset routes, email-verification routes, role model, policies, or authentication middleware.
- No Fortify, UI, Sanctum, or other authentication package was present.

The existing MariaDB baseline contains the three framework migrations in batch 1 and no user rows at inspection time. Existing migrations were not edited or removed.

Laravel Boost was installed as required by the repository instructions. It is a development tooling dependency and is not an authentication package.

## 3. Authentication foundation

### Routes and controllers

The following native web flows were added:

| Flow | Routes | Implementation |
|---|---|---|
| Login/logout | `GET/POST /login`, `POST /logout` | `AuthenticatedSessionController` |
| Password reset request | `GET/POST /forgot-password` | `PasswordResetLinkController` |
| Password reset completion | `GET/POST /reset-password` | `NewPasswordController` |
| Email verification notice | `GET /email/verify` | `EmailVerificationPromptController` |
| Verification resend | `POST /email/verification-notification` | `EmailVerificationPromptController` |
| Signed verification link | `GET /email/verify/{id}/{hash}` | `VerifyEmailController` |

Laravel's session guard, Eloquent user provider, password broker, reset-token repository, notifications, and `MustVerifyEmail` contract are used. No external authentication package was added.

### Login and session security

- Email input is trimmed and normalized to lowercase before lookup.
- Passwords are checked with Laravel's configured hasher; a valid dummy hash is checked when the account is not found to reduce account-enumeration timing differences.
- Only an account with `status=active` and an assigned fixed role can log in.
- Successful login regenerates the session ID and CSRF token.
- Failed login responses use one generic credential error for invalid credentials and inactive accounts.
- Logout is POST-only, CSRF-protected, invalidates the session, regenerates the CSRF token, and cycles the remember token.
- Laravel's `auth.session` middleware is applied to protected and verification routes so password changes invalidate existing authenticated sessions.
- `UserSessionInvalidator` rotates remember tokens and deletes database-backed sessions for the affected user. Other session drivers rely on the native `auth.session` password-hash check and remember-token rotation.

### Password hashing and reset

- The existing `hashed` Eloquent cast remains the password storage boundary.
- New passwords use Laravel's password defaults with a minimum length of 12 characters and a maximum of 255 characters.
- Password reset links use Laravel's native broker, 60-minute expiry, and broker throttling.
- Reset tokens are generated and stored by Laravel's token repository; raw reset tokens are never logged or persisted by application code.
- A successful reset updates the password, rotates the remember token, invalidates database sessions where applicable, and emits Laravel's `PasswordReset` event.
- Password-reset request responses are generic whether or not the email exists. Invalid reset tokens also receive a generic error.

### Email verification

`App\Models\User` implements `MustVerifyEmail`. Active users may sign in to reach the verification notice, but the normal dashboard and administrator area require the built-in `verified` middleware. Verification links are signed and rate-limited.

### Rate limiting

Native Laravel rate limiters are registered in `AppServiceProvider`:

- Login: separate per-IP, per-normalized-account, and per-account/IP limits.
- Password reset: per-IP and per-account limits, in addition to the broker throttle.
- Email verification and verification-link handling: per-user and per-IP limits.

Rate-limit keys use one-way digests rather than raw email addresses or IP addresses. The configured values are conservative starting values and should be tuned using production monitoring before launch.

## 4. Role and account-status model

### Fixed roles

`App\Enums\UserRole` contains the stable internal codes required for the first release:

- `administrator`
- `guru`
- `siswa`

No `roles`, `permissions`, pivot tables, permission package, applicant role, guardian role, or multi-tenant role model was created.

### Account status

`App\Enums\AccountStatus` contains:

- `active`
- `inactive`
- `suspended`

The `User` model casts `role` and `status` to these enums. `role` and `status` are not mass assignable through the public fillable allowlist. Role/status changes must go through the lifecycle service.

`AccountLifecycleService` provides explicit, authorized operations for activation, suspension, deactivation, and role changes. It uses a database row lock, rotates the remember token, invalidates database sessions, and writes a redacted security event to the application log. It does not hard-delete users.

## 5. Authorization foundation

### Middleware

The following aliases are registered in `bootstrap/app.php`:

- Laravel `auth` for authentication.
- Laravel `auth.session` for password/session consistency.
- `active` for `EnsureAccountIsActive`.
- `verified` for Laravel email verification.
- `role` for `EnsureUserHasRole`.

Protected routes use authentication, session consistency, active-account, email-verification, and fixed-role checks. Inactive, suspended, missing-role, and unverified accounts cannot reach normal protected areas.

### Policies and gates

`App\Policies\UserPolicy` is the identity-resource policy foundation:

- Administrators may view/manage other user accounts.
- Users may view/update their own account.
- Hard deletion is denied.
- Activation, suspension, deactivation, and role assignment are administrator-only and additionally require an active, verified administrator.

The `manage-users` and `access-administrator-area` gates provide coarse cross-cutting checks. No student, teacher, guardian, class, enrollment, PPDB, or other domain-specific policy was implemented.

## 6. Security controls

Implemented controls include:

- CSRF tokens on every state-changing authentication form.
- Session fixation prevention through ID and CSRF-token regeneration.
- Password hashing through the model cast and a 12-character minimum password policy.
- Generic authentication and password-reset errors to reduce account enumeration.
- Native rate limiting for login, reset, and verification operations.
- Native hashed, expiring password-reset tokens.
- Signed, expiring email-verification URLs.
- Explicit fillable/hidden model attributes; passwords and remember tokens remain hidden from serialization.
- Active/suspended account enforcement on every protected request.
- Server-side Gates and Policies; UI visibility is not treated as authorization.
- Redacted security logging for login success/rejection, logout, account lifecycle changes, password reset events, and email verification. Passwords, raw reset tokens, and raw credentials are never logged.
- Production-default secure session cookies through configuration; the current local environment remains HTTP-compatible.
- The fixed-credential test seeder was removed. `DatabaseSeeder` no longer creates `test@example.com` or any known production credential.

The application log is not a replacement for the future append-only audit store. Audit retention, legal holds, sensitive-read auditing, and administrator audit access remain later platform work.

## 7. Minimal frontend

The Laravel starter page was replaced with a small Sekolah Kita shell. The implementation adds only authentication-related UI:

- Login.
- Forgot-password request.
- Password reset form.
- Email verification notice and resend form.
- Generic 403 and 419 pages.
- A minimal authenticated landing page and administrator access placeholder.

The views use the existing Blade/Vite/Tailwind foundation. No complete dashboard or domain module was added.

## 8. Database migration plan and safety verification

### Migration created

`database/migrations/2026_09_24_115047_add_role_and_status_to_users_table.php`

The additive `up()` plan is:

1. Add nullable `users.role` as `VARCHAR(32)` after `email_verified_at`.
2. Add non-null `users.status` as `VARCHAR(20)` with default `inactive`.
3. Add `users_status_index` for status filtering.
4. Add `users_role_status_index` for role/status filtering.

No table is dropped, no column is removed or renamed, and no existing row is deleted. Existing accounts receive the safe default `inactive` and no role until an authorized administrator explicitly provisions them. This is intentional fail-closed behavior.

Before applying the migration to a shared environment, take a reviewed database backup, confirm the maintenance window and table-lock impact, and deploy the application code only after the additive schema change is available. No automatic backfill is included because role assignment requires controlled administrator decisions.

The migration's `down()` is limited to local/test teardown. A production rollback would require a reviewed backup/recovery decision; it was not executed.

### Migration commands and result

- `php artisan migrate:status` showed the three original migrations as `Ran` and the new migration as `Pending`.
- `php artisan migrate --pretend --no-interaction` generated only additive `ALTER TABLE ... ADD` statements and the two indexes.
- The real migration was **not** run against the existing MariaDB database.
- No `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, `DROP`, or `TRUNCATE` command was run against the development database.

The migration is MariaDB-compatible and also passed the isolated test migration path used by the SQLite test suite. A disposable MariaDB integration database is still required before release.

## 9. Files created and modified

### Application files created

- `app/Enums/AccountStatus.php`
- `app/Enums/UserRole.php`
- `app/Http/Controllers/AdministratorFoundationController.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Controllers/Auth/EmailVerificationPromptController.php`
- `app/Http/Controllers/Auth/NewPasswordController.php`
- `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- `app/Http/Controllers/Auth/VerifyEmailController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Middleware/EnsureAccountIsActive.php`
- `app/Http/Middleware/EnsureUserHasRole.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Http/Requests/Auth/ResetPasswordRequest.php`
- `app/Http/Requests/Auth/SendPasswordResetLinkRequest.php`
- `app/Listeners/LogAuthenticationEvents.php`
- `app/Policies/UserPolicy.php`
- `app/Services/AccountLifecycleService.php`
- `app/Services/UserSessionInvalidator.php`
- `database/migrations/2026_09_24_115047_add_role_and_status_to_users_table.php`
- `lang/en/auth.php`
- `lang/en/passwords.php`
- `lang/en/verification.php`
- `resources/views/admin/foundation.blade.php`
- `resources/views/auth/forgot-password.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/auth/reset-password.blade.php`
- `resources/views/auth/verify-email.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/errors/403.blade.php`
- `resources/views/errors/419.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/auth.blade.php`
- `tests/Feature/AccountLifecycleTest.php`
- `tests/Feature/AuthenticationTest.php`
- `tests/Feature/AuthorizationTest.php`
- `docs/PHASE_1_IMPLEMENTATION.md`

### Application files modified

- `AGENTS.md` (Laravel Boost setup)
- `app/Models/User.php`
- `app/Providers/AppServiceProvider.php`
- `bootstrap/app.php`
- `composer.json`
- `composer.lock`
- `config/auth.php`
- `config/session.php`
- `database/factories/UserFactory.php`
- `database/seeders/DatabaseSeeder.php`
- `resources/views/welcome.blade.php`
- `routes/web.php`

### Boost tooling files created

`boost.json`, `opencode.json`, `.mcp.json`, `.agents/`, and `.claude/` were generated by the mandated Laravel Boost installation. No authentication package was added.

## 10. Tests added

### Test files

- `tests/Feature/AuthenticationTest.php`
- `tests/Feature/AuthorizationTest.php`
- `tests/Feature/AccountLifecycleTest.php`

The tests cover the requested minimum behaviors:

1. Successful login.
2. Invalid login rejection.
3. Logout.
4. Session ID regeneration after login.
5. Inactive account rejection.
6. Role middleware allowing a matching role.
7. Unauthorized role access denial.
8. Password-reset link and completion flow.
9. Unverified-user behavior, resend, and signed verification.
10. Policy-level authorization denial.

Additional coverage includes login throttling, generic reset responses, CSRF form tokens, database-session invalidation after reset, unverified administrator denial, account lifecycle transitions, and mass-assignment protection for role/status.

## 11. Commands and results

| Command | Result |
|---|---|
| `php -v` | PHP 8.5.4 available |
| `composer -V` | Composer 2.10.3 available |
| `composer require laravel/boost --dev` | Boost 2.10.0 installed as mandated repository setup |
| `php artisan boost:install` | Completed successfully |
| `vendor/bin/pint --dirty --format agent` | Passed |
| `composer test` | **30 tests passed, 103 assertions** |
| `php artisan route:list --except-vendor` | 13 web routes verified |
| `php artisan event:list` | Authentication event listener discovery verified |
| `php artisan view:cache` | All Blade templates compiled successfully |
| `php artisan route:cache` / `config:cache` | Cacheability checks passed; caches cleared afterward |
| `php artisan migrate:status` | New migration remains pending |
| `php artisan migrate --pretend --no-interaction` | Additive SQL verified; no migration executed |
| `npm run build` | Vite build passed; only the existing optional `fontaine` optimization warning was shown |
| `composer validate --strict` | Passed |
| `composer audit` | No security advisories found |
| `npm audit --omit=dev` | 0 vulnerabilities found |
| `git diff --check` | Passed |

The default test harness uses isolated in-memory SQLite with array cache/session/mail. This is not a production database configuration. The migration was checked with MariaDB `--pretend`; a disposable MariaDB integration run remains a release requirement.

## 12. Known limitations and remaining requirements

The following are intentionally not complete in this phase:

- **Administrator MFA:** no external provider, factor schema, enrollment, recovery codes, or bypass policy was implemented. MFA remains a production launch requirement for administrator access.
- **Controlled provisioning:** no administrator user-management UI or invitation workflow was added. The fixed test seeder was removed; the first administrator must be provisioned through a controlled, reviewed process.
- **Password change:** the authenticated self-service password-change flow is not included yet; reset is implemented.
- **Redis:** current application drivers remain unchanged. Redis-backed sessions, cache, queues, and rate limits require the infrastructure phase and Redis integration tests.
- **MariaDB integration:** no destructive or shared-database test run was performed. Run the suite against a disposable MariaDB schema before release.
- **Mail:** local mail logging remains the current environment behavior. Production sender identity, provider, queueing, retries, and failure monitoring remain open.
- **Session hardening:** absolute session lifetime/reauthentication, Nginx HTTPS/security headers, trusted proxies, and production cookie deployment remain infrastructure work.
- **Audit and privacy:** the append-only audit store, sensitive-read auditing, retention/legal holds, backup/restore ownership, and incident response are not yet implemented.
- **Domain authorization:** student ownership policies, Guru assignment scope, administrator resource policies for future modules, and query scoping are deferred until their domain decisions are approved.
- **Domain modules:** no student, teacher, guardian, academic, PPDB, news, achievement, gallery, or public-content functionality was started.
- **Browser smoke testing:** no browser automation was run because no desktop browser session was connected; Blade rendering, feature requests, and the Vite build were verified instead.

## 13. Phase 2 readiness

The scoped Phase 1 foundation is **ready for Git review and for the next identity/domain planning increment**, subject to:

1. Review and controlled application of the additive migration in the target environment.
2. Approval of the remaining identity/MFA and administrator provisioning decisions.
3. Establishing the MariaDB/Redis integration test environment before production release.

It is **not yet production-launch ready**. Phase 2 domain work must not begin by adding student, teacher, guardian, academic, enrollment, PPDB, or public-content schemas until the relevant Phase 0 decisions are resolved.
