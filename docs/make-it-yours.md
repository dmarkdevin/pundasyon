# Make it yours

Pundasyon is meant to be the start of your own app. This page takes you from a copy of it to your own project.

## 1. Get your own copy

On GitHub, click **Use this template**, then **Create a new repository**. You get a new repository with Pundasyon's files and none of its history. Then clone it:

```bash
git clone https://github.com/<you>/<your-app>.git
cd <your-app>
```

Without GitHub, clone Pundasyon and start a new history:

```bash
git clone https://github.com/dmarkdevin/pundasyon.git my-app
cd my-app
rm -rf .git && git init -b main
```

Then follow the README's [quick start](../README.md#quick-start).

## 2. Rename it

| What | Where | Change |
|---|---|---|
| App name | `.env` and `.env.example`: `APP_NAME` | Used for the first install, emails and the authenticator app. After installing, change it under **Settings → General**. |
| Database name | `.env` and `.env.example`: `DB_DATABASE` | Before the first `sail up`. Otherwise, run `./vendor/bin/sail down -v` first. This deletes the local database. |
| Sender address | `.env` and `.env.example`: `MAIL_FROM_ADDRESS` | Or set it under **Settings → Email**. |
| Package name | `composer.json`: `name`, `description` | For example `acme/crm`. Then run `./vendor/bin/sail composer validate`. |
| Brand colour | `app/Providers/Filament/AppPanelProvider.php`: `Color::Teal` | Any colour from `Filament\Support\Colors\Color`, or `Color::hex('#1d4ed8')`. |
| Logo | **Settings → General** | PNG, JPG or WebP. |
| Panel address | `AppPanelProvider.php`: `->path('app')` | For example `admin`. Also change the redirect in `routes/web.php` and the sign-in link `app:install` prints in `app/Console/Commands/InstallCommand.php`. |
| Local ports | `.env`: `APP_PORT`, `FORWARD_DB_PORT`, `FORWARD_MAILPIT_*`, `VITE_PORT`, `SAIL_SUBNET` | So it can run beside other Sail projects. |
| Documents | `README.md`, `CHANGELOG.md`, `SECURITY.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md` | Rewrite them for your app. Start `CHANGELOG.md` with an empty "Unreleased" section. |

Find what's left with:

```bash
grep -rni pundasyon --exclude-dir={vendor,node_modules,.git,public} .
```

## 3. The licence

Pundasyon is MIT licensed. You can use it in closed-source and commercial apps. Keep the copyright notice in `LICENSE`, or include it wherever you ship the code. Your own code can use any licence you choose.

## 4. Demo data

`app:install --demo` adds demo accounts with the password `password`, plus sample users and sign-ins. Use it locally only.

To start over without demo data:

```bash
./vendor/bin/sail artisan migrate:fresh --force
./vendor/bin/sail artisan app:install
```

`migrate:fresh` deletes every table in the local database. On a server, run `app:install` without `--demo`. It refuses to run in production unless you pass `--force`.

## 5. Choose what to keep

| You don't need | Do this |
|---|---|
| Public sign-up | Turn off **Settings → Sign-in and passwords → Anyone can sign up**, or install with `--no-signup`. Admins then create accounts. |
| Impersonation | Remove the impersonate action from `app/Filament/Resources/Users/Tables/UsersTable.php` and `Pages/EditUser.php`, and `impersonate` from `users` in `config/permissions.php`. |

Run `./vendor/bin/sail composer check` after each removal. The tests that covered what you removed fail: delete them.

The API has no off switch. Make modules with `--no-api` if you don't need it. Only people with an account and an active status can get a token.

## 6. Build your features

Start with [building features](building-features.md). It walks through a CRUD module, records people own, a feature without a module, settings, scheduled jobs, emails and API versions.

## 7. Database

Sail runs MySQL 8. CI also tests SQLite and PostgreSQL, so either works. To use PostgreSQL locally, add Sail's `pgsql` service (see the [Sail docs](https://laravel.com/docs/sail)) and set `DB_CONNECTION=pgsql`, `DB_HOST=pgsql` and `DB_PORT=5432` in `.env`.

## 8. Ship it

Follow the [deployment checklist](deployment.md). Before going live, read [security](security.md), including the accepted risks in the [security review](security-review.md).

## 9. Stay up to date

Your copy doesn't follow Pundasyon after you create it. To bring in later fixes:

```bash
git remote add pundasyon https://github.com/dmarkdevin/pundasyon.git
git fetch pundasyon
git log --oneline main..pundasyon/main
git cherry-pick <commit>
```

Pick the fixes you want instead of merging everything: your copy will have changed. Releases and what changed in each are on the [releases page](https://github.com/dmarkdevin/pundasyon/releases). Dependabot opens pull requests for package updates in your own repository. [Upgrading](upgrading.md) covers Laravel and Filament upgrades.
