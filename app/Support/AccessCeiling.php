<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Whether one person's access covers another's. Changing someone's password,
 * email or account status, or impersonating them, effectively hands over their
 * access, so it's only allowed when the actor already has every permission the
 * target has. People with the same permissions (peers) can manage each other.
 */
final class AccessCeiling
{
    public static function covers(User $actor, User $target): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ($target->isSuperAdmin()) {
            return false;
        }

        $mine = $actor->getAllPermissions()->pluck('name')->all();
        $theirs = $target->getAllPermissions()->pluck('name')->all();

        return array_diff($theirs, $mine) === [];
    }
}
