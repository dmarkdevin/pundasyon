# Permissions

## Roles

| Role | Built in | Can do |
|---|---|---|
| `super-admin` | yes | Everything, except delete their own account. |
| `admin` | yes | Every permission except those in `not_for_admins` in `config/permissions.php` (by default, impersonation). |
| `user` | yes | Nothing in the admin area. Given to people who sign up. |
| Your own | no | The permissions you tick on the Roles screen. |

Built-in roles can't be renamed or deleted, by anyone.

## Permission names

Permissions are named `{module}.{action}`:

| Permission | Lets you |
|---|---|
| `users.view`, `users.create`, `users.update`, `users.delete`, `users.restore` | Manage users |
| `users.impersonate` | Sign in as another user (never as a super admin) |
| `roles.view`, `roles.create`, `roles.update`, `roles.delete` | Manage roles |
| `settings.update` | Change settings |
| `activity.view` | Read the activity log |
| `{module}.view/create/update/delete/restore` | For every module made with `make:module` |

Core permissions are listed in `config/permissions.php`; generated modules are added from `modules.json`. Permissions are defined in code because code checks them by name, so there's no screen to create them.

## Adding a permission

1. Add the action to the module in `config/permissions.php`, for example `'users' => [..., 'export']`.
2. Run `php artisan permissions:sync`. New permissions go to the admin role automatically, unless listed in `not_for_admins`. Permissions you took away from admins aren't given back.
3. Check it where it matters: `$user->can('users.export')`, `Gate::authorize('users.export')`, or in a policy.

## Rules that always apply

- **Policies everywhere.** Every model has a policy; architecture tests fail otherwise. Screens and the API both ask the policy, so hiding a button is never the only protection.
- **You can't give what you don't have.** Roles and permissions you can assign are limited to the permissions you hold, on screen and on the server.
- **No changing your own access.** Nobody can change their own roles, suspend themselves or delete their own account.
- **Nobody can manage someone stronger.** Changing, suspending, deleting, restoring or impersonating someone needs your permissions to include all of theirs. Otherwise resetting a stronger account's password would hand over its access. People with the same permissions can manage each other. Super admins can manage everyone, but can't delete their own account.
- **API tokens are narrower still.** A token only has the permissions picked when it was made, even for a super admin.

## How the check works

`AppServiceProvider` registers one `Gate::before` check (Spatie's own is turned off in `config/permission.php`):

```php
// 1. A token can only use its own abilities.
// 2. Super admins pass, except for deleting themselves.
// 3. Everyone else: does a role or direct grant give this permission?
```

Roles and permissions always use the `web` guard, also during API requests.
