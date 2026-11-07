# API

Version 1 lives at `/api/v1`. Interactive docs, generated from the code, are at `/docs/api`. They're open to everyone in local development, and only to admins and super admins on other environments.

## Getting a token

Either sign in through the API:

```bash
curl -X POST https://your-app.example.com/api/v1/auth/login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{
    "email": "you@example.com",
    "password": "your password",
    "device_name": "Accounting sync",
    "abilities": ["users.view"],
    "expires_in_days": 90
  }'
```

```json
{
  "data": {
    "token": "3|kq9W...",
    "token_type": "Bearer",
    "name": "Accounting sync",
    "abilities": ["users.view"],
    "expires_at": "2027-02-05T10:00:00+00:00",
    "user": { "id": 1, "name": "Ana Reyes", "email": "you@example.com", "roles": ["admin"] }
  }
}
```

Or make one in the app under **API tokens** in the user menu.

- The token is shown once. Store it like a password.
- `abilities` is optional; leave it out for every permission you have. A token never has more than its owner.
- `expires_in_days` is optional, up to 365; tokens from this endpoint expire after 90 days by default. A password change, suspension or deletion revokes every token.
- If two-factor sign-in is on, add the current 6-digit code as `"code"`. Each code works once. Recovery codes only work on the web sign-in page.
- Failed sign-ins count towards the same account lockout as the web, and appear in the sign-in history.
- Suspended accounts and accounts with an unconfirmed email can't sign in.

## Using a token

```bash
curl https://your-app.example.com/api/v1/auth/me \
  -H 'Accept: application/json' -H 'Authorization: Bearer 3|kq9W...'
```

Only tokens work: a browser's session cookie isn't accepted. Sign out (revoke the token) with `POST /api/v1/auth/logout`.

## Endpoints

| Method and path | Permission |
|---|---|
| `POST /auth/login` | none |
| `GET /auth/me`, `POST /auth/logout` | any valid token |
| `GET /users`, `GET /users/{id}` | `users.view` |
| `POST /users` | `users.create` |
| `PATCH /users/{id}` | `users.update` |
| `DELETE /users/{id}`, `POST /users/{id}/restore` | `users.delete`, `users.restore` |
| `GET /roles`, `GET /roles/{id}` | `roles.view` |
| `POST /roles`, `PATCH /roles/{id}`, `DELETE /roles/{id}` | `roles.create`, `roles.update`, `roles.delete` |
| Generated modules, such as `/products` | `products.view` and so on |

The same rules as the admin screens apply: you can only give roles or permissions you have, can't change your own roles, and can't change someone whose access is greater than yours.

## Lists

```bash
curl 'https://your-app.example.com/api/v1/users?filter%5Bsearch%5D=ana&filter%5Bstatus%5D=active&sort=-created_at&per_page=50' \
  -H 'Accept: application/json' -H 'Authorization: Bearer 3|kq9W...'
```

- `filter[...]`: the allowed filters for each endpoint are listed in the docs. Users: `search`, `status`, `role`, `trashed` (`with` or `only`).
- `sort`: one or more fields, comma-separated; prefix with `-` for descending.
- `per_page`: 1 to 100 (15 by default), with `page` for the page number.

The brackets must be URL-encoded (`%5B` and `%5D`) in `curl`, or curl treats them as a pattern.

Unknown filters or sorts get a `400` with `"code": "invalid_query"`.

## Responses

Success:

```json
{ "data": { "id": 5, "name": "Ana Reyes" } }
```

Lists add `links` (first, last, prev, next) and `meta` (current_page, per_page, total, and so on).

Errors always look like this; `errors` only appears for validation problems:

```json
{
  "message": "The given data was invalid.",
  "code": "validation_failed",
  "errors": { "email": ["The email has already been taken."] }
}
```

| Status | `code` |
|---|---|
| 400 | `invalid_query` |
| 401 | `unauthenticated` |
| 403 | `forbidden` |
| 404 | `not_found` |
| 405 | `method_not_allowed` |
| 422 | `validation_failed` |
| 429 | `too_many_requests` |
| 500 | `server_error` (details only when `APP_DEBUG` is on) |

## Limits and browsers

- `API_RATE_LIMIT` requests a minute per token (60 by default). Responses include `X-RateLimit-Limit` and `X-RateLimit-Remaining`.
- `API_LOGIN_RATE_LIMIT` sign-in attempts a minute per email and address (5 by default), and `API_LOGIN_RATE_LIMIT_PER_ADDRESS` from one address across all emails (20).
- Browsers on other sites can only call the API if their address is in `CORS_ALLOWED_ORIGINS` (comma-separated, for example `https://app.example.com`).

## Versions

A change that would break existing clients goes in a new `/api/v2` group. `/api/v1` keeps working until its users have moved.
