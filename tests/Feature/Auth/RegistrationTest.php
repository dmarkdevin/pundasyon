<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Models\User;
use App\Settings\AuthSettings;
use Filament\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

use function Pest\Livewire\livewire;

function closeRegistration(): void
{
    $settings = app(AuthSettings::class);
    $settings->registration_enabled = false;
    $settings->save();
}

it('lets people sign up and asks them to confirm their email', function (): void {
    Notification::fake();

    livewire(Register::class)
        ->fillForm([
            'name' => 'Ana Reyes',
            'email' => 'ana@example.com',
            'password' => 'Correct-horse-42',
            'passwordConfirmation' => 'Correct-horse-42',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'ana@example.com')->sole();

    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('keeps unconfirmed accounts out of the app', function (): void {
    $this->actingAs(User::factory()->unverified()->create())
        ->get('/app')
        ->assertRedirect('/app/email-verification/prompt');
});

it('closes the sign-up page when sign-up is turned off', function (): void {
    closeRegistration();

    $this->get('/app/register')->assertNotFound();
});

it('refuses a sign-up already in progress when sign-up is turned off', function (): void {
    $page = livewire(Register::class)->fillForm([
        'name' => 'Ana Reyes',
        'email' => 'ana@example.com',
        'password' => 'Correct-horse-42',
        'passwordConfirmation' => 'Correct-horse-42',
    ]);

    closeRegistration();

    $page->call('register')->assertStatus(404);
    expect(User::query()->count())->toBe(0);
});

it('only offers a sign-up link while sign-up is open', function (): void {
    livewire(Login::class)->assertSee('sign up');

    closeRegistration();

    livewire(Login::class)->assertDontSee('sign up');
});
