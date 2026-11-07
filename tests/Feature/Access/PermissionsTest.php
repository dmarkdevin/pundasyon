<?php

declare(strict_types=1);

use App\Actions\Permissions\SyncPermissions;
use App\Enums\SystemRole;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;

it('creates every permission and the built-in roles', function (): void {
    seedAccess();

    expect(Permission::query()->pluck('name')->sort()->values()->all())->toEqual(collect(Permissions::all())->sort()->values()->all())
        ->and(Role::query()->pluck('name')->all())->toContain('super-admin', 'admin', 'user');
});

it('names permissions {module}.{action}', function (): void {
    expect(Permissions::all())->each->toMatch('/^[a-z0-9_-]+\.[a-z_]+$/')
        ->and(Permissions::label('users.update'))->toBe('Users: update');
});

it('gives admins everything except impersonation', function (): void {
    seedAccess();
    $admin = Role::findByName('admin');

    expect($admin->hasPermissionTo('users.delete'))->toBeTrue()
        ->and($admin->hasPermissionTo('users.impersonate'))->toBeFalse()
        ->and(Role::findByName('user')->permissions)->toBeEmpty();
});

it('does not give back a permission taken away from admins', function (): void {
    seedAccess();
    Role::findByName('admin')->revokePermissionTo('users.delete');

    app(SyncPermissions::class)();

    expect(Role::findByName('admin')->fresh()->hasPermissionTo('users.delete'))->toBeFalse();
});

it('lets super admins do anything, including checks that do not exist yet', function (): void {
    $superAdmin = userWithRole(SystemRole::SuperAdmin);

    expect($superAdmin->can('anything.at-all'))->toBeTrue()
        ->and(userWithRole(SystemRole::Admin)->can('anything.at-all'))->toBeFalse();
});

it('syncs from the command line', function (): void {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->count())->toBe(count(Permissions::all()));
});
