# Architecture

Pundasyon is a normal Laravel 13 app. Everything a signed-in person does happens in one [Filament](https://filamentphp.com) panel at `/app`; other programs use the API at `/api/v1`.

## Folders

| Folder | What's in it |
|---|---|
| `app/Actions` | Business logic, one class per job (`SuspendUser`, `SaveRole`, `IssueApiToken`). Controllers and screens call these instead of doing the work themselves. |
| `app/Enums` | Fixed values: `UserStatus`, `SystemRole`, `LoginEventType`. |
| `app/Filament` | The panel: `Resources` (Users, Roles, Activity log, and generated modules), `Pages` (dashboard, settings, profile, sign-in, API tokens), `Widgets`. |
| `app/Http/Controllers/Api/V1` | API controllers. Thin: authorise, call an action or query, return a resource. |
| `app/Http/Requests/Api/V1` | Validation (and authorisation) for every API input. |
| `app/Http/Resources/V1` | The shape of every API output. Models are never returned directly. |
| `app/Http/Middleware` | Security headers, HTTPS, signing out suspended people. |
| `app/Listeners` | Sign-in history and activity, default role on sign-up, impersonation records. |
| `app/Models` | Eloquent models. `Role` and `Permission` extend Spatie's. |
| `app/Modules` | The module generator (`make:module`, `remove:module`). |
| `app/Policies` | One policy per model. Arch tests fail if a model has none. |
| `app/Settings` | Settings classes stored in the database (spatie/laravel-settings). |
| `app/Support` | Small helpers: the permission list, API error format, avatars, access rules. |
| `config/permissions.php` | The list of permissions for the core modules. |
| `config/security.php` | Security headers, HTTPS, rate limits, history retention. |
| `modules.json` | Modules made by `make:module`, with their permissions and files. |
| `stubs/module` | Templates for generated modules. |
| `routes/api/modules` | One routes file per generated module. |

## Request flow

**Panel**: Filament checks the person is signed in, active (suspended people are signed out) and has confirmed their email. Each resource asks its policy before listing, viewing, creating, editing or deleting.

**API**: `auth:sanctum` turns the bearer token into a user, `EnsureApiUserIsActive` refuses suspended accounts, `throttle:api` limits requests per token, then the form request validates and authorises, and an API resource shapes the answer. Exceptions become one JSON error format in `App\Support\ApiErrors`.

## Permissions

One check in `AppServiceProvider` (`Gate::before`) decides every permission:

1. An API token only carries the permissions picked when it was made.
2. Super admins pass, except when deleting their own account.
3. Everyone else needs a role or direct grant with that permission.

Policies use permission names (`$user->can('users.update')`) and add rules such as "admins can't edit super admins". See [permissions](permissions.md).

## Data that changes over time

- **Activity log** (spatie/laravel-activitylog): model changes with before and after values, plus events written by the app.
- **Sign-in history** (`login_events`): every attempt, for the "Sign-ins and devices" page and the dashboard.
- **Settings** (`settings` table): cached; `ApplySettings` copies them into the running config at boot and after saving.

## Background work

The queue worker sends emails (password resets, email confirmation). The scheduler runs daily clean-ups: sign-in history, the activity log and expired API tokens. Both must run in production; see [deployment](deployment.md).

## Choices worth knowing

- **Filament for every screen**, including sign-in and profile, so there's one look and far less code to maintain.
- **Generated modules use the normal Laravel folders**, so they read like hand-written code. `modules.json` remembers which files belong to which module.
- **Times are stored in UTC** and shown in `APP_DISPLAY_TIMEZONE`.
- **Private files** (profile photos) are on the `local` disk and served through short-lived signed links at `/private-files`; public files (the logo) are on the `public` disk.
