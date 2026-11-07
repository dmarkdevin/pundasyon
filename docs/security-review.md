# Security review: OWASP Top 10 (2025)

Done on 2026-11-07, before v1.0.0, by a reviewer who hadn't written the code, checking code and the running app. Every finding below was fixed or accepted, and each fix has a regression test in `tests/Feature/SecurityReviewTest.php` (or the test named).

## Findings and what was done

| # | Category | Severity | Finding | Status |
|---|---|---|---|---|
| 1 | A01 Broken access control | High | Anyone with `users.update` could change the password or email of an account with more access (for example a helpdesk role resetting an admin's password), then sign in as it. Same for delete, restore and impersonation. | **Fixed.** Changing, deleting, restoring, suspending or impersonating someone now needs your permissions to cover all of theirs (`App\Support\AccessCeiling`). Peers can still manage each other. |
| 2 | A01 / A05 | Medium | A tampered profile-photo upload could get a signed link to any file on the private disk, and replacing the photo could delete that file. | **Fixed.** `preventFilePathTampering()` on the avatar and logo uploads; only `avatars/...` image paths are stored, served or deleted. |
| 3 | A07 / A06 | Medium | Anyone could keep any account locked by failing 5 sign-ins every 15 minutes. The lock message suggested resetting the password, which didn't lift it. | **Fixed.** The lock is per email and address, with an account-wide lock only at 10 times that (stops distributed guessing without letting one attacker lock the owner out). A password reset lifts it. |
| 4 | A07 | Medium/Low | A two-factor code could be reused on the API within its time window. | **Fixed.** Codes are refused the second time. |
| 5 | A07 | Low | Password reset said "We can't find a user with that email address". | **Fixed.** It gives the same answer for every email. Sign-up still says an email is taken, as any sign-up form must; turn sign-up off if that matters. |
| 6 | A07 | Low | One address could try one password against unlimited accounts on the API. | **Fixed.** API sign-in is also limited per address (20 a minute, `API_LOGIN_RATE_LIMIT_PER_ADDRESS`). |
| 7 | A09 Logging | Low | Direct permissions, initial roles on create and tokens made through the API sign-in weren't logged. | **Fixed.** All three are in the activity log. |
| 8 | A07 | Low | Tokens survived a password change and a (soft) delete, and never expired by default. | **Fixed.** Both revoke all tokens; API sign-in tokens expire after 90 days unless asked otherwise. |
| 9 | A03 / A08 | Low | The API docs viewer came from unpkg.com without integrity checks, and the page allowed all of unpkg.com. | **Fixed.** Pinned version with SRI hashes; the policy only allows that package. |
| 10 | A03 | Low | CI actions pinned to movable tags, no audit step; Mailpit image `latest`. | **Fixed.** Actions pinned to commit SHAs, `composer audit` and `npm audit` in CI, Mailpit pinned, Dependabot watches the compose file. |
| 11 | A02 Misconfiguration | Low | Session cookie not marked secure unless set; MySQL and Mailpit reachable from the local network; PHP version in `X-Powered-By`. | **Fixed.** Secure cookie by default in production; ports bound to 127.0.0.1; header removed. |
| 12 | A10 | Info | Role bulk delete relied on the model throwing for built-in roles. | **Fixed.** Each record is authorised. |
| 13 | A08 | Info | `remove:module` trusted `modules.json`: a changed entry could delete any file, all permissions or any table. | **Fixed.** Entries must match what `make:module` writes for that model, or nothing is changed. Generated modules can't redefine core permissions. JSON fields are limited to 100 items. |

## Accepted, and documented

- **`'unsafe-inline'` and `'unsafe-eval'` in the script policy.** Filament is built on Alpine.js, which needs them, so the policy doesn't stop injected scripts by itself. Output escaping (Blade, Filament) is the defence; the policy still blocks framing, outside connections and other hosts.
- **Generated modules aren't scoped per owner.** Anyone with `products.view` sees every product. For records that belong to one person, add an ownership check to the generated policy and query.
- **Failed two-factor codes on the web** count against Filament's per-visitor limit (5 a minute), not the account lockout.

## Checked and sound

The single permission check and API token limits, the role and permission ceiling on screens and API, mass assignment, insecure direct object references on tokens, sessions, sign-in history and the activity log, injection (no raw SQL, escaped output), CORS and CSRF, security headers, storage of secrets (two-factor secret encrypted, recovery codes hashed, SMTP password encrypted), timing-safe sign-in messages, suspension, and dependency audits (`composer audit`, `npm audit`: no known vulnerabilities).
