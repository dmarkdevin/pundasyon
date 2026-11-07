<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Super admins pass every check before this runs. Built-in roles can't be
 * deleted by anyone; the Role model refuses that too.
 */
final class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.update') && ! $role->isSuperAdmin();
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.delete') && ! $role->isSystem();
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('roles.delete');
    }
}
