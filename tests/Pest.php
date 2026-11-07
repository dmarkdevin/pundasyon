<?php

declare(strict_types=1);

use App\Actions\Permissions\SyncPermissions;
use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests boot the app and get a fresh database for every test.
| Unit and architecture tests stay plain and fast.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Creates the permissions and built-in roles, as `php artisan db:seed` does.
 */
function seedAccess(): void
{
    app(SyncPermissions::class)();
}

/**
 * A signed-in-ready user with one of the built-in roles.
 */
function userWithRole(SystemRole $role, array $attributes = []): User
{
    seedAccess();

    $user = User::factory()->create($attributes);
    $user->assignRole($role->value);

    return $user;
}

/**
 * Sends the next requests with a real API token for this user, limited to these abilities.
 * Null means every permission the user has, as the sign-in endpoint does by default.
 *
 * @param  list<string>|null  $abilities
 */
function asApiUser(User $user, ?array $abilities = null): TestCase
{
    $abilities ??= $user->isSuperAdmin() ? App\Support\Permissions::all() : $user->getAllPermissions()->pluck('name')->all();
    $token = $user->createToken('test', $abilities)->plainTextToken;

    // A guard remembers the last user it saw, which would leak between requests in one test.
    app('auth')->forgetGuards();

    return test()->withToken($token);
}
