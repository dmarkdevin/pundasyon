# Changelog

Notable changes, newest first. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Security

- OWASP Top 10 review before release; all findings fixed or documented in [docs/security-review.md](docs/security-review.md). Notably: nobody can change or impersonate an account with more access than theirs; uploads can't point at other private files; the account lockout can't be used to lock someone out from elsewhere; password reset doesn't reveal which emails have accounts; API tokens are revoked on password change or deletion and expire after 90 days by default.

### Added

- Guides: [building features](docs/building-features.md), with tested tutorials for CRUD modules, custom actions, records people own, exports, settings, scheduled jobs, emails and API versions; and [make it yours](docs/make-it-yours.md), for starting your own app from Pundasyon.
- `php artisan app:install`: sets up the app key, database, roles and permissions, storage link and the first super admin; `--demo` adds demo accounts and sample data.
- Module generator: `make:module` writes a model, migration, factory, seeder, policy, permissions, admin screens, API endpoints and Pest tests; `remove:module` takes them out again.
- REST API at `/api/v1` with Sanctum tokens limited to chosen permissions, per-token rate limits, filtering, sorting, paging, one error format, and interactive docs at `/docs/api`.
- API tokens page for people to create and revoke their own tokens.
- Dashboard widgets that follow permissions: user counts, today's sign-ins, a 30-day sign-ups chart and recent activity.
- Settings stored in the database: app name and logo, maintenance message, sign-up and password rules, account lockout, and mail.
- Activity log of data changes, access changes, sign-ins, settings changes and impersonation.
- Users and roles screens with permissions named `{module}.{action}`, policies for every model, suspension, soft deletes, impersonation, and profile photos.
- Sign-up you can turn off, email confirmation, password reset, two-factor sign-in with recovery codes, password rules with a breached-password check, account lockout, sign-in history and "log out other devices".
- Security headers, HTTPS redirects and HSTS in production.
- Laravel Sail setup with a queue worker and scheduler, Pest, Pint, Larastan level 8, GitHub Actions on SQLite, MySQL and PostgreSQL, and Dependabot.
