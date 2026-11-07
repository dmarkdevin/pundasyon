<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles the app relies on. They can't be renamed or deleted.
 */
enum SystemRole: string
{
    /** Passes every permission check (see AppServiceProvider). */
    case SuperAdmin = 'super-admin';

    /** Gets every permission except the ones in config/permissions.php "not_for_admins". */
    case Admin = 'admin';

    /** Given to people who sign up. No admin permissions. */
    case User = 'user';

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(fn (self $role): string => $role->value, self::cases());
    }
}
