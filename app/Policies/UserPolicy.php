<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\AccessCeiling;

/**
 * Super admins pass every check before this runs (AppServiceProvider), except
 * deleting their own account. Everyone else needs the permission, and can only
 * change people whose access theirs covers (AccessCeiling): otherwise resetting a
 * stronger account's password would hand over its access. Suspending yourself is
 * blocked in the user screens.
 */
final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('users.update') && AccessCeiling::covers($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can('users.delete') && AccessCeiling::covers($user, $target) && ! $user->is($target);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('users.delete');
    }

    public function restore(User $user, User $target): bool
    {
        return $user->can('users.restore') && AccessCeiling::covers($user, $target);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('users.restore');
    }

    /** Only super admins erase accounts for good, and never their own. */
    public function forceDelete(User $user, User $target): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }
}
