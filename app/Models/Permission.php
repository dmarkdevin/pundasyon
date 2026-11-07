<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Permissions;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permissions are defined in code (config/permissions.php and modules.json) and
 * synced with `php artisan permissions:sync`, so there's no screen to create them.
 *
 * @property string $name
 */
final class Permission extends SpatiePermission
{
    public function getLabelAttribute(): string
    {
        return Permissions::label($this->name);
    }
}
