<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Filament\Pages\ApiTokens;

use function Pest\Livewire\livewire;

use Spatie\Activitylog\Models\Activity;

it('makes a token limited to permissions the person has, shown once', function (): void {
    $admin = userWithRole(SystemRole::Admin);
    $this->actingAs($admin);

    $page = livewire(ApiTokens::class)
        ->callAction('createToken', ['name' => 'Accounting sync', 'abilities' => ['users.view'], 'expires_in_days' => '30'])
        ->assertHasNoActionErrors();

    $token = $admin->tokens()->sole();
    expect($token->abilities)->toBe(['users.view'])
        ->and($token->expires_at?->isFuture())->toBeTrue()
        ->and($page->get('newToken'))->toStartWith($token->getKey().'|')
        ->and(Activity::query()->where('event', 'token-created')->exists())->toBeTrue();

    $page->call('forgetNewToken')->assertSet('newToken', null);
});

it('refuses permissions the person doesn\'t have', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));

    livewire(ApiTokens::class)
        ->callAction('createToken', ['name' => 'Sneaky', 'abilities' => ['users.impersonate'], 'expires_in_days' => 'never'])
        ->assertHasActionErrors(['abilities.0']);
});

it('revokes only the person\'s own tokens', function (): void {
    $admin = userWithRole(SystemRole::Admin);
    $other = userWithRole(SystemRole::Admin);
    $mine = $admin->createToken('mine')->accessToken;
    $theirs = $other->createToken('theirs')->accessToken;
    $this->actingAs($admin);

    livewire(ApiTokens::class)->callAction('revokeToken', arguments: ['token' => $theirs->getKey()]);
    livewire(ApiTokens::class)->callAction('revokeToken', arguments: ['token' => $mine->getKey()]);

    expect($other->tokens()->count())->toBe(1)
        ->and($admin->tokens()->count())->toBe(0);
});
