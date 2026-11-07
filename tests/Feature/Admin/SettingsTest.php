<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Filament\Pages\Settings\ManageGeneral;
use App\Filament\Pages\Settings\ManageMail;
use App\Filament\Pages\Settings\ManageSecurity;
use App\Settings\AuthSettings;
use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

use Spatie\Activitylog\Models\Activity;

it('keeps people without settings.update out', function (string $path): void {
    $this->actingAs(userWithRole(SystemRole::User));

    $this->get($path)->assertForbidden();
})->with(['/app/settings/general', '/app/settings/security', '/app/settings/email']);

it('lets admins rename the app, and the change shows at once', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));

    livewire(ManageGeneral::class)
        ->fillForm(['app_name' => 'Bayan CRM'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(GeneralSettings::class)->app_name)->toBe('Bayan CRM')
        ->and(config('app.name'))->toBe('Bayan CRM');
    $this->get('/app')->assertSee('Bayan CRM');
});

it('stores the logo publicly and refuses SVG', function (): void {
    Storage::fake('public');
    $this->actingAs(userWithRole(SystemRole::Admin));

    livewire(ManageGeneral::class)
        ->fillForm(['logo_path' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml')])
        ->call('save')
        ->assertHasFormErrors(['logo_path']);

    livewire(ManageGeneral::class)
        ->fillForm(['logo_path' => UploadedFile::fake()->image('logo.png', 300, 80)])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = (string) app(GeneralSettings::class)->logo_path;
    expect($path)->toStartWith('branding/')
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

it('changes sign-in rules from the screen', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));

    livewire(ManageSecurity::class)
        ->fillForm(['registration_enabled' => false, 'password_min_length' => 16, 'lockout_attempts' => 3])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(AuthSettings::class);
    expect($settings->registration_enabled)->toBeFalse()
        ->and($settings->password_min_length)->toBe(16)
        ->and($settings->lockout_attempts)->toBe(3);
    $this->get('/app/register')->assertNotFound();
});

it('refuses unsafe sign-in rules', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));

    livewire(ManageSecurity::class)
        ->fillForm(['password_min_length' => 4, 'lockout_attempts' => 0])
        ->call('save')
        ->assertHasFormErrors(['password_min_length', 'lockout_attempts']);
});

it('keeps the SMTP password encrypted, hidden and unchanged when left empty', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));

    livewire(ManageMail::class)
        ->fillForm(['smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_username' => 'mailer', 'smtp_password' => 'secret-pass', 'smtp_encryption' => 'tls'])
        ->call('save')
        ->assertHasNoFormErrors();

    $raw = (string) DB::table('settings')->where('group', 'mail')->where('name', 'smtp_password')->value('payload');
    expect($raw)->not->toContain('secret-pass')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.com');

    livewire(ManageMail::class)
        ->assertFormSet(['smtp_password' => null])
        ->fillForm(['smtp_host' => 'smtp2.example.com'])
        ->call('save');

    expect(app(MailSettings::class)->smtp_password)->toBe('secret-pass');
});

it('never puts setting values in the activity log', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));

    livewire(ManageMail::class)->fillForm(['smtp_host' => 'smtp.example.com', 'smtp_password' => 'secret-pass'])->call('save');

    $entry = Activity::query()->where('log_name', 'settings')->sole();
    expect($entry->properties->get('changed'))->toContain('smtp_password')
        ->and(json_encode($entry->toArray()))->not->toContain('secret-pass');
});

it('sends a test email', function (): void {
    Mail::fake();
    $admin = userWithRole(SystemRole::Admin);
    $this->actingAs($admin);

    livewire(ManageMail::class)->callAction('sendTest')->assertNotified("Sent to {$admin->email}.");
});

it('shows the maintenance message while the app is down', function (): void {
    $settings = app(GeneralSettings::class);
    $settings->maintenance_message = 'Back at 6 PM after the upgrade.';
    $settings->save();

    $this->artisan('down');
    try {
        $this->get('/app/login')->assertStatus(503)->assertSee('Back at 6 PM after the upgrade.');
    } finally {
        $this->artisan('up');
    }
});
