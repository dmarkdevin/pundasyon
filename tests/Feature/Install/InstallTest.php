<?php

declare(strict_types=1);

use App\Models\User;
use App\Settings\AuthSettings;

it('sets everything up and creates the first super admin', function (): void {
    $this->artisan('app:install', ['--name' => 'Ana Reyes', '--email' => 'ana@example.com', '--password' => 'Correct-horse-42', '--no-interaction' => true])
        ->assertSuccessful();

    $ana = User::query()->where('email', 'ana@example.com')->sole();
    expect($ana->isSuperAdmin())->toBeTrue()
        ->and($ana->hasVerifiedEmail())->toBeTrue()
        ->and(App\Models\Role::query()->pluck('name')->all())->toContain('admin', 'user');
});

it('asks for the super admin when no options are given', function (): void {
    $this->artisan('app:install')
        ->expectsQuestion('Name', 'Ana Reyes')
        ->expectsQuestion('Email address', 'ana@example.com')
        ->expectsQuestion('Password', 'Correct-horse-42')
        ->expectsConfirmation('Should anyone be able to sign up?', 'no')
        ->assertSuccessful();

    expect(User::query()->where('email', 'ana@example.com')->sole()->isSuperAdmin())->toBeTrue()
        ->and(app(AuthSettings::class)->registration_enabled)->toBeFalse();
});

it('refuses a weak password and creates nobody', function (): void {
    $this->artisan('app:install', ['--name' => 'Ana', '--email' => 'ana@example.com', '--password' => 'short', '--no-interaction' => true])
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('is safe to run again', function (): void {
    $options = ['--name' => 'Ana', '--email' => 'ana@example.com', '--password' => 'Correct-horse-42', '--no-interaction' => true];

    $this->artisan('app:install', $options)->assertSuccessful();
    $this->artisan('app:install', [...$options, '--email' => 'second@example.com'])
        ->expectsOutputToContain('A super admin already exists')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(1);
});

it('turns sign-up off when asked', function (): void {
    $this->artisan('app:install', ['--name' => 'Ana', '--email' => 'ana@example.com', '--password' => 'Correct-horse-42', '--no-signup' => true, '--no-interaction' => true])
        ->assertSuccessful();

    expect(app(AuthSettings::class)->registration_enabled)->toBeFalse();
});

it('adds demo accounts and sample data, once', function (): void {
    $options = ['--name' => 'Owner', '--email' => 'owner@example.com', '--password' => 'Correct-horse-42', '--demo' => true, '--no-interaction' => true];

    $this->artisan('app:install', $options)->assertSuccessful();
    $count = User::query()->count();
    $this->artisan('app:install', $options)->assertSuccessful();

    expect(User::query()->count())->toBe($count)
        ->and(User::query()->where('email', 'admin@example.com')->sole()->hasRole('admin'))->toBeTrue()
        ->and(User::query()->where('email', 'support@example.com')->sole()->can('users.view'))->toBeTrue()
        ->and(User::query()->where('email', 'like', '%@demo.example.com')->count())->toBe(30);
});

it('refuses to run in production without --force', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('app:install', ['--demo' => true, '--no-interaction' => true])
        ->expectsOutputToContain('This is production')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('says why the database step failed, and that running again is safe', function (): void {
    config(['database.connections.broken' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'x', 'password' => 'x']]);
    $default = config('database.default');
    config(['database.default' => 'broken']);

    try {
        // Its own output: Artisan::output() would return the inner migrate call's.
        $buffer = new Symfony\Component\Console\Output\BufferedOutput;
        $exit = Artisan::call('app:install', ['--no-interaction' => true], $buffer);
        $output = $buffer->fetch();
    } finally {
        // The test's own database clean-up needs the real connection back.
        config(['database.default' => $default]);
        DB::purge('broken');
    }

    expect($exit)->toBe(1)
        ->and($output)->toContain('The database couldn\'t be set up')
        ->and($output)->toContain('safe to repeat');
});

// Runs inside `composer check:generator`, which makes the GeneratorCheck modules; skipped otherwise.
it('adds GeneratorCheck module samples on the first demo install only', function (): void {
    $model = 'App\\Models\\GeneratorCheckItem';

    if (! class_exists($model)) {
        $this->markTestSkipped('Only runs while composer check:generator has made the sample modules.');
    }

    $options = ['--name' => 'Owner', '--email' => 'owner@example.com', '--password' => 'Correct-horse-42', '--demo' => true, '--no-interaction' => true];

    $this->artisan('app:install', $options)->assertSuccessful();
    $records = $model::query()->count();
    $users = User::query()->count();
    $this->artisan('app:install', $options)->assertSuccessful();

    expect($records)->toBeGreaterThan(0)
        ->and($model::query()->count())->toBe($records)
        ->and(User::query()->count())->toBe($users);
});
