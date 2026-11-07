<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Actions\Activity\LogAccessChange;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

final class SaveRole
{
    /**
     * @param  array{name?: string, permissions?: list<string>}  $data
     */
    public function __invoke(array $data, ?Role $role = null): Role
    {
        return DB::transaction(function () use ($data, $role): Role {
            $role ??= new Role(['guard_name' => 'web']);

            if (isset($data['name'])) {
                $role->name = $data['name'];
            }

            $role->save();

            if (array_key_exists('permissions', $data)) {
                $before = array_values(array_map(strval(...), $role->permissions()->pluck('name')->all()));
                $role->syncPermissions($data['permissions']);
                LogAccessChange::between($role, 'roles', $before, $data['permissions']);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role->load('permissions')->loadCount('users');
        });
    }
}
