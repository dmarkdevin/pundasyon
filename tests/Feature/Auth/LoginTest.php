<?php

declare(strict_types=1);

use App\Enums\LoginEventType;
use App\Filament\Pages\Auth\Login;
use App\Models\LoginEvent;
use App\Models\User;

use function Pest\Livewire\livewire;

function signIn(string $email, string $password = 'password'): Livewire\Features\SupportTesting\Testable
{
    return livewire(Login::class)
        ->fillForm(['email' => $email, 'password' => $password])
        ->call('authenticate');
}

it('signs people in and records it', function (): void {
    $user = User::factory()->create();

    signIn($user->email)->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
    expect(LoginEvent::query()->sole())
        ->type->toBe(LoginEventType::Succeeded)
        ->user_id->toBe($user->id);
});

it('records a wrong password without keeping the password', function (): void {
    $user = User::factory()->create();

    signIn($user->email, 'not-the-password')->assertHasFormErrors(['email']);

    $this->assertGuest();
    $event = LoginEvent::query()->sole();
    expect($event->type)->toBe(LoginEventType::Failed)
        ->and($event->user_id)->toBe($user->id)
        ->and(json_encode($event->getAttributes()))->not->toContain('not-the-password');
});

it('records attempts on emails that have no account', function (): void {
    signIn('nobody@example.com', 'guess');

    expect(LoginEvent::query()->sole())
        ->type->toBe(LoginEventType::Failed)
        ->user_id->toBeNull()
        ->email->toBe('nobody@example.com');
});

it('locks an account after 5 failed attempts, even with the right password', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        signIn($user->email, "wrong-{$attempt}");
    }

    signIn($user->email)->assertHasFormErrors(['email']);

    $this->assertGuest();
    expect(LoginEvent::query()->latest('id')->first()?->type)->toBe(LoginEventType::LockedOut);
});

it('locks unknown emails the same way, so nobody learns which accounts exist', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        signIn($user->email, 'wrong');
        signIn('nobody@example.com', 'wrong');
    }

    $known = signIn($user->email)->errors()->get('data.email');
    $unknown = signIn('nobody@example.com')->errors()->get('data.email');

    expect($known)->toEqual($unknown);
});

it('lets the account in again once the lock runs out', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        signIn($user->email, 'wrong');
    }

    $this->travel(16)->minutes();

    signIn($user->email)->assertHasNoFormErrors();
    $this->assertAuthenticatedAs($user);
});

it('forgets earlier failures after a successful sign-in', function (): void {
    $user = User::factory()->create();

    foreach (range(1, 4) as $attempt) {
        signIn($user->email, 'wrong');
    }

    signIn($user->email);
    auth()->logout();

    foreach (range(1, 4) as $attempt) {
        signIn($user->email, 'wrong');
    }

    signIn($user->email)->assertHasNoFormErrors();
});

it('deletes sign-in history older than the configured number of days', function (): void {
    $user = User::factory()->create();
    $old = LoginEvent::query()->create(['user_id' => $user->id, 'email' => $user->email, 'type' => LoginEventType::Succeeded]);
    $old->forceFill(['created_at' => now()->subDays(181)])->save();
    LoginEvent::query()->create(['user_id' => $user->id, 'email' => $user->email, 'type' => LoginEventType::Succeeded]);

    $this->artisan('model:prune', ['--model' => [LoginEvent::class]]);

    expect(LoginEvent::query()->count())->toBe(1);
});
