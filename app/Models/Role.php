<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SystemRole;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property string $name
 */
final class Role extends SpatieRole
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('roles')->logOnly(['name'])->logOnlyDirty()->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event): string => ucfirst($event).' role');
    }

    public function isSystem(): bool
    {
        return in_array($this->name, SystemRole::names(), true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->name === SystemRole::SuperAdmin->value;
    }

    protected static function booted(): void
    {
        // A last line of defence: the app breaks without these roles, whoever asks.
        self::deleting(function (self $role): void {
            if ($role->isSystem()) {
                throw new LogicException("The {$role->name} role is built in and can't be deleted.");
            }
        });

        self::updating(function (self $role): void {
            if (in_array($role->getOriginal('name'), SystemRole::names(), true) && $role->isDirty('name')) {
                throw new LogicException("The {$role->getOriginal('name')} role is built in and can't be renamed.");
            }
        });
    }
}
