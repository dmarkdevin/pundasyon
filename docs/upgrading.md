# Upgrading

## Dependencies

Dependabot opens weekly pull requests for Composer packages, npm packages and GitHub Actions. CI runs the full checks on each one. For a manual update:

```bash
composer update
npm update
php artisan filament:upgrade   # also runs automatically after composer update
composer check                 # style, static analysis and tests
composer check:generator       # generated modules still work
```

Read the release notes of Laravel, Filament, Livewire and the Spatie packages before major version jumps. Change one major version at a time.

## Pundasyon itself

If you started from Pundasyon and want later improvements:

1. Read [CHANGELOG.md](../CHANGELOG.md) for what changed.
2. Bring the changes in with Git, for example `git remote add upstream <pundasyon-url>` then `git fetch upstream` and `git merge upstream/main`. Resolve conflicts in files you've changed.
3. Run:

   ```bash
   composer install
   php artisan migrate
   php artisan permissions:sync
   npm ci && npm run build
   composer check
   ```

## After changing the templates

If you've edited `stubs/module`, modules made earlier keep their old code. To regenerate a module that has no changes of yours:

1. `php artisan remove:module Product` and answer no when asked about the table, so the data stays.
2. `php artisan make:module Product --fields="..."` with the same fields.
3. Delete the new `..._create_products_table.php` migration, because the table already exists.
