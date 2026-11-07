<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\Hash;

use function Pest\Livewire\livewire;

function userWithTwoFactor(): User
{
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());
    $user->saveAppAuthenticationRecoveryCodes([Hash::make('recovery-code-1'), Hash::make('recovery-code-2')]);

    return $user;
}

function currentCode(User $user): string
{
    return AppAuthentication::make()->getCurrentCode($user);
}

it('asks for a code before signing in', function (): void {
    $user = userWithTwoFactor();

    livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate');

    $this->assertGuest();
});

it('signs in with a valid code', function (): void {
    $user = userWithTwoFactor();

    livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->set('data.multiFactor.app.code', currentCode($user))
        ->call('authenticate');

    $this->assertAuthenticatedAs($user);
});

it('refuses a wrong code', function (): void {
    $user = userWithTwoFactor();

    livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->set('data.multiFactor.app.code', '000000')
        ->call('authenticate');

    $this->assertGuest();
});

it('signs in with a recovery code, which then stops working', function (): void {
    $user = userWithTwoFactor();

    livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->set('data.multiFactor.app.useRecoveryCode', true)
        ->set('data.multiFactor.app.recoveryCode', 'recovery-code-1')
        ->call('authenticate');

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->getAppAuthenticationRecoveryCodes())->toHaveCount(1);
});

it('never puts secrets in arrays or JSON', function (): void {
    $user = userWithTwoFactor()->refresh();

    expect(array_keys($user->toArray()))
        ->not->toContain('password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes');
});

it('stores the two-factor secret encrypted', function (): void {
    $user = userWithTwoFactor();
    $raw = Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->value('app_authentication_secret');

    expect($raw)->not->toBe($user->getAppAuthenticationSecret());
});
