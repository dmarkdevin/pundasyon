<?php

declare(strict_types=1);

namespace App\Actions\Permissions;

use App\Enums\SystemRole;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates any missing permissions and the built-in roles. Safe to run again.
 * Only permissions created in this run go to the admin role, so a permission
 * someone deliberately took away from admins isn't given back.
 */
final class SyncPermissions
{
    /**
     * @return list<string> the permissions that were created
     */
    public function __invoke(): array
    {
        $created = [];

        foreach (Permissions::all() as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            if ($permission->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        foreach (SystemRole::cases() as $role) {
            Role::query()->firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $forAdmins = array_values(array_intersect($created, Permissions::forAdmins()));

        if ($forAdmins !== []) {
            Role::findByName(SystemRole::Admin->value, 'web')->givePermissionTo($forAdmins);
        }

        return $created;
    }
}
