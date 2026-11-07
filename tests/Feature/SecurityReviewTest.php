<?php

declare(strict_types=1);

/*
| Regression tests for the findings of the OWASP Top 10 review (Phase 8).
*/

use App\Enums\SystemRole;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Role;
use App\Models\User;
use App\Support\Auth\AccountLockout;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Livewire\livewire;

use Spatie\Activitylog\Models\Activity;

function helpdesk(): User
{
    seedAccess();
    Role::query()->firstOrCreate(['name' => 'helpdesk', 'guard_name' => 'web'])->syncPermissions(['users.view', 'users.update', 'users.delete']);
    $user = User::factory()->create();
    $user->assignRole('helpdesk');

    return $user;
}

it('stops a weaker role taking over a stronger account through the API', function (): void {
    $admin = userWithRole(SystemRole::Admin);
    $plain = userWithRole(SystemRole::User);
    $helpdesk = helpdesk();

    asApiUser($helpdesk)->patchJson("/api/v1/users/{$admin->id}", ['password' => 'Taken-over-123'])->assertForbidden();
    asApiUser($helpdesk)->deleteJson("/api/v1/users/{$admin->id}")->assertForbidden();
    asApiUser($helpdesk)->patchJson("/api/v1/users/{$plain->id}", ['name' => 'Fine'])->assertOk();
});

it('lets peers with the same permissions manage each other', function (): void {
    $first = userWithRole(SystemRole::Admin);
    $second = userWithRole(SystemRole::Admin);

    asApiUser($first)->patchJson("/api/v1/users/{$second->id}", ['name' => 'Peer'])->assertOk();
});

it('only allows impersonating accounts the impersonator\'s access covers', function (): void {
    seedAccess();
    Role::query()->firstOrCreate(['name' => 'impersonator', 'guard_name' => 'web'])->syncPermissions(['users.view', 'users.impersonate']);
    $impersonator = User::factory()->create();
    $impersonator->assignRole('impersonator');
    $admin = userWithRole(SystemRole::Admin);
    $plain = userWithRole(SystemRole::User);
    $this->actingAs($impersonator);

    livewire(ListUsers::class)
        ->assertActionHidden(TestAction::make('impersonate')->table($admin))
        ->assertActionVisible(TestAction::make('impersonate')->table($plain));
});

it('never stores a profile photo path outside the avatars folder', function (): void {
    $user = User::factory()->create(['avatar_path' => 'avatars/me.png']);

    $user->update(['avatar_path' => '.gitignore']);

    expect($user->fresh()->avatar_path)->toBe('avatars/me.png');
});

it('locks an email per address, so an attacker elsewhere can\'t lock the owner out', function (): void {
    $lockout = app(AccountLockout::class);

    foreach (range(1, 5) as $attempt) {
        $lockout->recordFailure('ana@example.com', '203.0.113.9');
    }

    expect($lockout->isLocked('ana@example.com', '203.0.113.9'))->toBeTrue()
        ->and($lockout->isLocked('ana@example.com', '198.51.100.1'))->toBeFalse();
});

it('still locks an account guessed at from many addresses', function (): void {
    $lockout = app(AccountLockout::class);

    foreach (range(1, 50) as $attempt) {
        $lockout->recordFailure('ana@example.com', "203.0.113.{$attempt}");
    }

    expect($lockout->isLocked('ana@example.com', '198.51.100.1'))->toBeTrue();
});

it('lifts the lock when the password is reset', function (): void {
    $user = User::factory()->create();
    $lockout = app(AccountLockout::class);

    foreach (range(1, 5) as $attempt) {
        $lockout->recordFailure($user->email, '127.0.0.1');
    }

    event(new PasswordReset($user));

    expect($lockout->isLocked($user->email, '127.0.0.1'))->toBeFalse();
    livewire(Login::class)->fillForm(['email' => $user->email, 'password' => 'password'])->call('authenticate')->assertHasNoFormErrors();
});

it('limits API sign-in attempts per address across many emails', function (): void {
    foreach (range(1, 20) as $i) {
        $this->postJson('/api/v1/auth/login', ['email' => "person{$i}@example.com", 'password' => 'Guess-1', 'device_name' => 'x']);
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'another@example.com', 'password' => 'Guess-1', 'device_name' => 'x'])->assertTooManyRequests();
});

it('accepts each two-factor code only once on the API', function (): void {
    $user = User::factory()->create();
    $user->saveAppAuthenticationSecret(app(AppAuthentication::class)->generateSecret());
    $code = AppAuthentication::make()->getCurrentCode($user);
    $login = ['email' => $user->email, 'password' => 'password', 'device_name' => 'x', 'code' => $code];

    $this->postJson('/api/v1/auth/login', $login)->assertCreated();
    $this->postJson('/api/v1/auth/login', $login)->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('answers a password reset request the same way whether or not the email has an account', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    livewire(RequestPasswordReset::class)->fillForm(['email' => 'nobody@example.com'])->call('request')->assertNotified(__('passwords.sent'));
    livewire(RequestPasswordReset::class)->fillForm(['email' => $user->email])->call('request')->assertNotified(__('passwords.sent'));
});

it('revokes API tokens when the password changes or the account is deleted', function (): void {
    $user = User::factory()->create();
    $user->createToken('one');
    $user->update(['password' => 'Brand-new-pass-1']);
    expect($user->tokens()->count())->toBe(0);

    $user->createToken('two');
    $user->delete();
    $user->restore();
    expect($user->tokens()->count())->toBe(0);
});

it('makes API sign-in tokens expire after 90 days by default, and logs them', function (): void {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Script'])->assertCreated();

    expect(PersonalAccessToken::query()->sole()->expires_at?->isSameDay(now()->addDays(90)))->toBeTrue()
        ->and(Activity::query()->where('event', 'token-created')->sole()->properties->get('via'))->toBe('api');
});

it('logs direct permissions given in the user screen', function (): void {
    $this->actingAs(userWithRole(SystemRole::SuperAdmin));
    $target = User::factory()->create();

    livewire(App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $target->getRouteKey()])
        ->fillForm(['permissions' => [App\Models\Permission::findByName('users.view', 'web')->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Activity::query()->where('description', 'Changed direct permissions')->sole()->properties->get('added'))->toBe(['users.view']);
});
