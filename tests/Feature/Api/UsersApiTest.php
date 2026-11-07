<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

it('lists users with pages, filters and sorting', function (): void {
    $admin = userWithRole(SystemRole::Admin, ['name' => 'Zed Admin']);
    User::factory()->create(['name' => 'Ana Reyes', 'email' => 'ana@example.com']);
    User::factory()->suspended()->create(['name' => 'Ben Cruz']);

    asApiUser($admin)->getJson('/api/v1/users?per_page=2&sort=name')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Ana Reyes')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3)
        ->assertJsonStructure(['data', 'links' => ['first', 'next'], 'meta' => ['current_page', 'last_page']]);

    asApiUser($admin)->getJson('/api/v1/users?filter[search]=ana')->assertJsonCount(1, 'data');
    asApiUser($admin)->getJson('/api/v1/users?filter[status]=suspended')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ben Cruz');
    asApiUser($admin)->getJson('/api/v1/users?filter[role]=admin')->assertJsonCount(1, 'data');
});

it('caps page size at 100 and refuses unknown sorts', function (): void {
    $admin = userWithRole(SystemRole::Admin);

    asApiUser($admin)->getJson('/api/v1/users?per_page=1000')->assertJsonPath('meta.per_page', 100);
    asApiUser($admin)->getJson('/api/v1/users?sort=password')->assertStatus(400)->assertJsonPath('code', 'invalid_query');
});

it('never returns secrets in lists', function (): void {
    $admin = userWithRole(SystemRole::Admin);

    $body = asApiUser($admin)->getJson('/api/v1/users')->getContent();

    expect($body)->not->toContain('password')->not->toContain('remember_token')->not->toContain('app_authentication');
});

it('keeps people without users.view out', function (): void {
    asApiUser(userWithRole(SystemRole::User))->getJson('/api/v1/users')->assertForbidden()->assertJsonPath('code', 'forbidden');
});

it('holds a token to its abilities, even for admins', function (): void {
    $admin = userWithRole(SystemRole::Admin);

    asApiUser($admin, ['users.view'])->getJson('/api/v1/users')->assertOk();
    asApiUser($admin, ['users.view'])->postJson('/api/v1/users', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'Correct-horse-42'])->assertForbidden();
});

it('holds a super admin\'s token to its abilities too', function (): void {
    $superAdmin = userWithRole(SystemRole::SuperAdmin);
    $target = User::factory()->create();

    asApiUser($superAdmin, ['users.view'])->patchJson("/api/v1/users/{$target->id}", ['name' => 'Changed'])->assertForbidden();
    expect($target->fresh()->name)->not->toBe('Changed');

    asApiUser($superAdmin)->patchJson("/api/v1/users/{$target->id}", ['name' => 'Changed'])->assertOk();
});

it('creates a confirmed user with a role', function (): void {
    $admin = userWithRole(SystemRole::Admin);

    asApiUser($admin)->postJson('/api/v1/users', ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'Correct-horse-42', 'roles' => ['user']])
        ->assertCreated()
        ->assertJsonPath('data.roles', ['user']);

    expect(User::query()->where('email', 'ana@example.com')->sole()->hasVerifiedEmail())->toBeTrue();
});

it('refuses weak passwords and roles the caller can\'t give', function (): void {
    $admin = userWithRole(SystemRole::Admin);

    asApiUser($admin)->postJson('/api/v1/users', ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'short', 'roles' => ['super-admin']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password', 'roles.0']);
});

it('updates only the fields sent, and logs role changes', function (): void {
    $admin = userWithRole(SystemRole::Admin);
    $target = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.com']);

    asApiUser($admin)->patchJson("/api/v1/users/{$target->id}", ['name' => 'Ana Reyes', 'roles' => ['user']])
        ->assertOk()
        ->assertJsonPath('data.name', 'Ana Reyes')
        ->assertJsonPath('data.email', 'ana@example.com');

    expect(Activity::query()->where('event', 'access-changed')->sole()->properties->get('added'))->toBe(['user']);
});

it('does not let people change their own roles', function (): void {
    $admin = userWithRole(SystemRole::Admin);

    asApiUser($admin)->patchJson("/api/v1/users/{$admin->id}", ['roles' => ['user']])->assertUnprocessable()->assertJsonValidationErrors('roles');
});

it('keeps admins away from super admins', function (): void {
    $superAdmin = userWithRole(SystemRole::SuperAdmin);

    asApiUser(userWithRole(SystemRole::Admin))->patchJson("/api/v1/users/{$superAdmin->id}", ['name' => 'Hacked'])->assertForbidden();
});

it('deletes and restores, but never your own account', function (): void {
    $admin = userWithRole(SystemRole::Admin);
    $target = User::factory()->create();

    asApiUser($admin)->deleteJson("/api/v1/users/{$target->id}")->assertNoContent();
    expect($target->fresh()->trashed())->toBeTrue();

    asApiUser($admin)->postJson("/api/v1/users/{$target->id}/restore")->assertOk();
    expect($target->fresh()->trashed())->toBeFalse();

    asApiUser($admin)->deleteJson("/api/v1/users/{$admin->id}")->assertForbidden();
});

it('answers 404 and 405 in the standard shape', function (): void {
    $admin = userWithRole(SystemRole::Admin);

    asApiUser($admin)->getJson('/api/v1/users/999999')->assertNotFound()->assertExactJson(['message' => 'Not found.', 'code' => 'not_found']);
    asApiUser($admin)->putJson('/api/v1/auth/me')->assertStatus(405)->assertJsonPath('code', 'method_not_allowed');
});

it('limits requests per token', function (): void {
    config(['security.api_rate_limit' => 2]);
    $user = User::factory()->create();
    $api = asApiUser($user);

    $api->getJson('/api/v1/auth/me')->assertOk();
    $api->getJson('/api/v1/auth/me')->assertOk();
    $api->getJson('/api/v1/auth/me')->assertTooManyRequests()->assertJsonPath('code', 'too_many_requests');
});

it('does not run more queries for a longer list', function (): void {
    $admin = userWithRole(SystemRole::Admin);
    $count = function () use ($admin): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        asApiUser($admin)->getJson('/api/v1/users?per_page=100')->assertOk();

        return count(DB::getQueryLog());
    };

    User::factory()->count(2)->create()->each->assignRole('user');
    $count();
    $few = $count();
    User::factory()->count(10)->create()->each->assignRole('user');

    expect($count())->toBe($few);
});
