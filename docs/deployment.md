# Deployment

Sail is for development. On a server, run Pundasyon like any Laravel 13 app: PHP-FPM or FrankenPHP behind a web server, plus a queue worker and the scheduler.

## Server

- PHP 8.4 with the extensions Laravel 13 needs, plus `intl`, `gd` and `pdo_mysql` (or `pdo_pgsql`)
- MySQL 8 or PostgreSQL 14 or newer
- Composer 2, and Node 22 to build the CSS and JavaScript
- HTTPS (a certificate from your host, or Let's Encrypt)

## First deploy

```bash
git clone <repository-url> /var/www/pundasyon
cd /var/www/pundasyon
composer install --no-dev --optimize-autoloader
cp .env.example .env            # then edit it (see below)
php artisan app:install --force # database, permissions, storage link and your super admin
npm ci && npm run build
php artisan optimize
php artisan filament:optimize
```

Point the web server's document root at `public/`. Give the web server user write access to `storage/` and `bootstrap/cache/` only.

## `.env` checklist

| Setting | Value |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` (never `true` on a server: errors would show secrets) |
| `APP_KEY` | Set by `app:install`. Keep it: changing it makes two-factor secrets and the SMTP password unreadable. |
| `APP_URL` | Your `https://` address |
| `APP_DISPLAY_TIMEZONE` | Your users' time zone, such as `Asia/Manila` |
| `DB_*` | Your database |
| `SESSION_DRIVER` | `database` (the "Sign-ins and devices" page needs it) |
| `SESSION_SECURE_COOKIE` | `true` (the default in production when left empty) |
| `MAIL_*` | Your mail service, or set it under Settings → Email |
| `TRUSTED_PROXIES` | Your load balancer or proxy addresses, or `*` if every request comes through one you control |
| `CORS_ALLOWED_ORIGINS` | Sites allowed to call the API from a browser, if any |
| `SECURITY_FORCE_HTTPS` | `true` |

## Background processes

The queue worker sends emails. Without it, nobody gets password-reset or confirmation emails. Run it with Supervisor or systemd and restart it on every deploy:

```ini
[program:pundasyon-queue]
command=php /var/www/pundasyon/artisan queue:work --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
```

The scheduler cleans up sign-in history, the activity log and expired API tokens. Add one cron entry:

```cron
* * * * * cd /var/www/pundasyon && php artisan schedule:run >> /dev/null 2>&1
```

## Every deploy

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan permissions:sync
npm ci && npm run build
php artisan optimize
php artisan filament:optimize
php artisan queue:restart
php artisan up
```

Set the maintenance message under Settings → General before running `php artisan down`.

## Backups

Back up the database and `storage/app` (profile photos are in `storage/app/private`, the logo in `storage/app/public`). Keep `.env` (above all `APP_KEY`) somewhere safe and separate.

## Before going live

- [ ] `APP_DEBUG=false` and `APP_ENV=production`
- [ ] HTTPS works and `http://` redirects to it
- [ ] Queue worker and scheduler are running
- [ ] Mail works (Settings → Email → Send a test email)
- [ ] Demo data isn't installed (`app:install --demo` refuses on production without `--force`)
- [ ] Public sign-up is set the way you want (Settings → Sign-in and passwords)
- [ ] Your super admin account has two-factor sign-in turned on
- [ ] Backups run, and you've tried restoring one
- [ ] `/docs/api` only opens for admins
- [ ] `expose_php = Off` in the server's `php.ini`
