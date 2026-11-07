<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Filament\Actions\Testing\TestAction;

use function Pest\Livewire\livewire;

it('keeps people without roles.view out', function (): void {
    $this->actingAs(userWithRole(SystemRole::User));

    $this->get('/app/roles')->assertForbidden();
});

it('never lets built-in roles be deleted or renamed', function (string $name): void {
    seedAccess();
    $role = Role::findByName($name);

    expect(fn () => $role->delete())->toThrow(LogicException::class)
        ->and(fn () => $role->update(['name' => 'renamed']))->toThrow(LogicException::class);
})->with(['super-admin', 'admin', 'user']);

it('hides delete for built-in roles, even from super admins', function (): void {
    $this->actingAs(userWithRole(SystemRole::SuperAdmin));

    livewire(ListRoles::class)->assertActionHidden(TestAction::make('delete')->table(Role::findByName('admin')));
});

it('lets admins create a role from permissions they have', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));

    livewire(CreateRole::class)
        ->fillForm(['name' => 'support-agent', 'permissions' => [Permission::findByName('users.view')->id]])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Role::findByName('support-agent')->hasPermissionTo('users.view'))->toBeTrue();
});

it('does not let admins put a permission they lack into a role', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));

    livewire(CreateRole::class)
        ->fillForm(['name' => 'sneaky', 'permissions' => [Permission::findByName('users.impersonate')->id]])
        ->call('create')
        ->assertHasFormErrors(['permissions.0']);
});

it('applies a role change to its people straight away', function (): void {
    $this->actingAs(userWithRole(SystemRole::SuperAdmin));
    $role = Role::create(['name' => 'support-agent', 'guard_name' => 'web']);
    $member = User::factory()->create();
    $member->assignRole($role);
    expect($member->can('users.view'))->toBeFalse();

    livewire(EditRole::class, ['record' => $role->getRouteKey()])
        ->fillForm(['permissions' => [Permission::findByName('users.view')->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(User::query()->find($member->id)?->can('users.view'))->toBeTrue();
});

it('does not let admins edit the super-admin role', function (): void {
    $this->actingAs(userWithRole(SystemRole::Admin));

    $this->get('/app/roles/'.Role::findByName('super-admin')->id.'/edit')->assertForbidden();
});
