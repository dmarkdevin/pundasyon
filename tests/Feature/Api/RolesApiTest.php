<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Models\Role;

it('lists roles with their permissions', function (): void {
    asApiUser(userWithRole(SystemRole::Admin))->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'admin')
        ->assertJsonPath('data.0.built_in', true)
        ->assertJsonStructure(['data' => [['id', 'name', 'permissions', 'users_count']]]);
});

it('creates a role from permissions the caller has', function (): void {
    asApiUser(userWithRole(SystemRole::Admin))->postJson('/api/v1/roles', ['name' => 'support-agent', 'permissions' => ['users.view']])
        ->assertCreated()
        ->assertJsonPath('data.permissions', ['users.view']);
});

it('refuses permissions the caller lacks', function (): void {
    asApiUser(userWithRole(SystemRole::Admin))->postJson('/api/v1/roles', ['name' => 'sneaky', 'permissions' => ['users.impersonate']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('permissions.0');
});

it('protects built-in roles', function (): void {
    $superAdmin = userWithRole(SystemRole::SuperAdmin);
    $api = fn () => asApiUser($superAdmin);
    $admin = Role::findByName('admin', 'web');

    $api()->patchJson("/api/v1/roles/{$admin->id}", ['name' => 'renamed'])->assertUnprocessable()->assertJsonValidationErrors('name');
    $api()->deleteJson("/api/v1/roles/{$admin->id}")->assertForbidden();
    $api()->patchJson('/api/v1/roles/'.Role::findByName('super-admin', 'web')->id, ['permissions' => []])->assertForbidden();
});

it('deletes a custom role', function (): void {
    $api = asApiUser(userWithRole(SystemRole::Admin));
    $role = Role::create(['name' => 'temporary', 'guard_name' => 'web']);

    $api->deleteJson("/api/v1/roles/{$role->id}")->assertNoContent();
    expect(Role::query()->where('name', 'temporary')->exists())->toBeFalse();
});
