# Security

What Pundasyon does to protect accounts and data, and what each measure stops. Report problems privately as described in [SECURITY.md](../SECURITY.md).

## Accounts

| Protection | Stops |
|---|---|
| Password rules (length, mixed case, numbers, symbols), changeable under Settings | Easily guessed passwords |
| Breached-password check against Have I Been Pwned; only the first 5 characters of the password's SHA-1 hash are sent | Passwords already leaked elsewhere |
| 5 sign-in attempts a minute per visitor (Filament) and per email and address (API), and 20 a minute per address on the API | Fast guessing, and one password tried on many accounts |
| Account lockout: 5 failures from one address lock that email there for 15 minutes; 50 from anywhere lock it everywhere (both changeable). A password reset lifts it. | Guessing, without letting one attacker lock the owner out |
| The same lockout and messages for emails with no account, the same password-reset answer for every email, and timing-safe password checks | Finding out which emails have accounts (sign-up necessarily says when an email is taken; turn sign-up off if that matters) |
| Two-factor sign-in with an authenticator app (each code works once), and recovery codes stored hashed | Stolen passwords |
| Two-factor secrets stored encrypted, never serialised | Secrets leaking from database backups or API output |
| Email confirmation for new accounts and for email changes | Accounts on addresses people don't own |
| Sign-in history and "log out other devices" | Unnoticed or lingering access |
| Suspension signs people out everywhere and revokes their API tokens; a password change or deletion also revokes tokens; API sign-in tokens expire after 90 days by default | Access after someone should have lost it |

## Access

| Protection | Stops |
|---|---|
| A policy for every model, checked by screens and the API | Reaching data by guessing URLs or IDs |
| Nobody can grant roles or permissions they don't have, and nobody can change, suspend, delete or impersonate someone with more access than they have, checked on the server | Raising your own access, or taking over a stronger account by resetting its password |
| Nobody can change their own roles, suspend or delete themselves | Lock-outs and quiet self-promotion |
| Admins can't edit, suspend, delete or impersonate super admins | Admins taking over the top accounts |
| API tokens limited to chosen permissions, even for super admins | A leaked token doing more than its job |
| Impersonation limited to super admins, never of other super admins, always logged | Unaccountable use of other accounts |
| Read-only activity log of changes, access changes, sign-ins and settings | Changes nobody can trace |

## Data

- Mass assignment: models only accept listed fields; status changes go through dedicated actions.
- Every API input is validated by a form request; every API output goes through a resource, so hidden fields such as passwords and two-factor secrets never leave.
- Lists eager-load their relations (tests check query counts don't grow with the list).
- Uploads: images only, size-limited. Profile photos are private and served through 30-minute signed links; old photos are deleted when replaced. The logo is public and SVG isn't accepted, because SVG can carry scripts.
- The SMTP password is stored encrypted and never sent back to the browser. Settings changes are logged by name, never by value.

## Browser and network

- Security headers on every response: Content-Security-Policy, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy` and `Cross-Origin-Opener-Policy`. Edit them in `config/security.php`.
- The policy allows inline scripts and `eval` for scripts, because Alpine.js inside Filament needs them. The API docs page also allows the one pinned viewer package on unpkg.com, checked with integrity hashes.
- `X-Powered-By` is removed; on a server also set `expose_php = Off`.
- In production: plain HTTP is redirected to HTTPS and HSTS is sent. Behind a proxy, set `TRUSTED_PROXIES`.
- CSRF protection on every form; the API only accepts tokens, never session cookies.
- CORS: only sites in `CORS_ALLOWED_ORIGINS` can call the API from a browser.

## Reviews

The OWASP Top 10 review before v1.0.0, with every finding and what was done, is in [security-review.md](security-review.md).

## Your part

See the [deployment checklist](deployment.md): `APP_DEBUG=false`, HTTPS, secure cookies, the queue worker and scheduler, backups, and keeping dependencies up to date.
