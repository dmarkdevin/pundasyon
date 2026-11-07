<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Actions\Activity\LogAccessChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates or updates a user from validated input. Roles are checked by the
 * request before this runs. Users made here are marked confirmed: whoever
 * creates them vouches for the address, as in the admin screens.
 */
final class SaveUser
{
    /**
     * @param  array{name?: string, email?: string, password?: string, roles?: list<string>}  $data
     */
    public function __invoke(array $data, ?User $user = null): User
    {
        return DB::transaction(function () use ($data, $user): User {
            $isNew = $user === null;
            $user ??= new User;
            $user->fill(array_intersect_key($data, array_flip(['name', 'email', 'password'])));

            if ($isNew) {
                $user->email_verified_at = now();
            }

            $user->save();

            if (array_key_exists('roles', $data)) {
                $before = array_values(array_map(strval(...), $user->roles()->pluck('name')->all()));
                $user->syncRoles($data['roles']);
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                LogAccessChange::between($user, 'users', $before, $data['roles']);
            }

            return $user->load('roles');
        });
    }
}
