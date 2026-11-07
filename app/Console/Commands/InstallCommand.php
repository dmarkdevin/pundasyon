<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Permissions\SyncPermissions;
use App\Enums\SystemRole;
use App\Models\User;
use App\Settings\AuthSettings;
use App\Support\Permissions;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

#[Signature('app:install
    {--name= : The first super admin\'s name}
    {--email= : The first super admin\'s email address}
    {--password= : The first super admin\'s password (prefer typing it when asked, so it isn\'t kept in your shell history)}
    {--demo : Add demo accounts and sample data}
    {--no-signup : Turn public sign-up off}
    {--force : Allow running in production, including the demo data}')]
#[Description('Set up Pundasyon: app key, database, permissions, storage link and the first super admin. Safe to run again.')]
final class InstallCommand extends Command
{
    public function handle(SyncPermissions $sync): int
    {
        if ($this->laravel->isProduction() && ! $this->option('force')) {
            $this->components->error('This is production. Run with --force if you mean it.');

            return self::FAILURE;
        }

        $this->components->info('Setting up '.config('app.name').'.');

        $this->ensureAppKey();
        if (! $this->migrate()) {
            return self::FAILURE;
        }

        Permissions::reset();
        $this->components->task('Roles and permissions', function () use ($sync): bool {
            $sync();

            return true;
        });
        $this->components->task('Public storage link', fn (): bool => is_link(public_path('storage')) || $this->callSilently('storage:link') === self::SUCCESS);

        if (! $this->superAdminExists() && ! $this->createSuperAdmin()) {
            return self::FAILURE;
        }

        $this->configureSignUp();

        if ($this->option('demo')) {
            $this->components->task('Demo accounts and sample data', fn (): bool => $this->callSilently('db:seed', ['--class' => DemoSeeder::class, '--force' => true]) === self::SUCCESS);
            $this->components->warn('Demo accounts use the password "password". Don\'t keep them on a server others can reach.');
        }

        $this->newLine();
        $this->components->info('Done. Sign in at '.rtrim((string) config('app.url'), '/').'/app');
        $this->components->twoColumnDetail('Build the CSS and JavaScript', 'npm install && npm run build');
        $this->components->twoColumnDetail('Add your first module', 'php artisan make:module Product --fields="name:string,price:decimal"');

        return self::SUCCESS;
    }

    /**
     * A missing key would make every encrypted value (two-factor secrets, the SMTP password) unreadable later.
     */
    private function ensureAppKey(): void
    {
        if (! is_file(base_path('.env')) && is_file(base_path('.env.example'))) {
            copy(base_path('.env.example'), base_path('.env'));
            $this->components->info('Created .env from .env.example.');
        }

        if (blank(config('app.key'))) {
            $this->components->task('App key', fn (): bool => $this->callSilently('key:generate', ['--force' => true]) === self::SUCCESS);
        }
    }

    /**
     * Migrations, with the reason shown when they fail. Right after `sail up` the
     * database may still be starting, so it's worth saying that running again is safe.
     */
    private function migrate(): bool
    {
        $output = new BufferedOutput;
        $error = null;

        try {
            $ok = Artisan::call('migrate', ['--force' => true], $output) === self::SUCCESS;
        } catch (Throwable $exception) {
            $ok = false;
            $error = $exception->getMessage();
        }

        if ($ok) {
            $this->components->twoColumnDetail('Database', '<fg=green;options=bold>DONE</>');

            return true;
        }

        $this->components->error('The database couldn\'t be set up: '.Str::limit($error ?? trim($output->fetch()), 400));
        $this->components->warn('If you just started the containers, the database may still be starting. Wait a few seconds and run app:install again; it\'s safe to repeat.');

        return false;
    }

    private function superAdminExists(): bool
    {
        $exists = User::role(SystemRole::SuperAdmin->value)->exists();

        if ($exists) {
            $this->components->info('A super admin already exists, so none was created.');
        }

        return $exists;
    }

    private function createSuperAdmin(): bool
    {
        $this->components->info('Create the first super admin. They can do everything, so use a strong password.');

        $data = [
            'name' => $this->option('name') ?? text('Name', required: true),
            'email' => $this->option('email') ?? text('Email address', required: true),
            'password' => $this->option('password') ?? password('Password', required: true, hint: 'At least '.app(AuthSettings::class)->password_min_length.' characters.'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::default()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            $this->components->warn('No super admin was created. Run php artisan app:install again.');

            return false;
        }

        $user = User::query()->create($validator->validated());
        $user->markEmailAsVerified();
        $user->assignRole(SystemRole::SuperAdmin->value);

        $this->components->info("Super admin {$user->email} created.");

        return true;
    }

    private function configureSignUp(): void
    {
        $settings = app(AuthSettings::class);
        $open = match (true) {
            (bool) $this->option('no-signup') => false,
            ! $this->input->isInteractive() => $settings->registration_enabled,
            default => confirm('Should anyone be able to sign up?', default: $settings->registration_enabled, hint: 'You can change this later under Settings.'),
        };

        if ($open !== $settings->registration_enabled) {
            $settings->registration_enabled = $open;
            $settings->save();
        }

        $this->components->twoColumnDetail('Public sign-up', $open ? 'on' : 'off');
    }
}
