<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SystemRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Auth;

/**
 * What the signed-in person may hand out. Nobody can give a permission they
 * don't have, so access can't be raised by assigning roles or permissions.
 * What a record already has stays visible, so saving never strips it silently.
 */
final class AssignableAccess
{
    /**
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public static function roles(Builder $query, ?Model $record = null): Builder
    {
        $ids = self::roleIds($record);

        return $query->whereIn('id', $ids)->orderBy('name');
    }

    /**
     * @param  Builder<Permission>  $query
     * @return Builder<Permission>
     */
    public static function permissions(Builder $query, ?Model $record = null): Builder
    {
        return $query->whereIn('id', self::permissionIds($record))->orderBy('name');
    }

    /**
     * @return list<int>
     */
    public static function roleIds(?Model $record = null): array
    {
        $actor = self::actor();
        $query = Role::query();

        if (! $actor?->isSuperAdmin()) {
            $mine = self::actorPermissionNames();
            $query->where('name', '!=', SystemRole::SuperAdmin->value)
                ->whereDoesntHave('permissions', fn (Builder $permissions) => $permissions->whereNotIn('name', $mine));
        }

        return self::withExisting($query->pluck('id')->all(), $record, 'roles');
    }

    /**
     * @return list<int>
     */
    public static function permissionIds(?Model $record = null): array
    {
        $query = Permission::query();

        if (! self::actor()?->isSuperAdmin()) {
            $query->whereIn('name', self::actorPermissionNames());
        }

        return self::withExisting($query->pluck('id')->all(), $record, 'permissions');
    }

    /**
     * The same rule by name, for the API.
     *
     * @return list<string>
     */
    public static function roleNames(?Model $record = null): array
    {
        return array_values(array_map(strval(...), Role::query()->whereIn('id', self::roleIds($record))->pluck('name')->all()));
    }

    /**
     * @return list<string>
     */
    public static function permissionNames(?Model $record = null): array
    {
        return array_values(array_map(strval(...), Permission::query()->whereIn('id', self::permissionIds($record))->pluck('name')->all()));
    }

    /**
     * @return list<string>
     */
    private static function actorPermissionNames(): array
    {
        return array_values(array_map(strval(...), self::actor()?->getAllPermissions()->pluck('name')->all() ?? []));
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    private static function withExisting(array $ids, ?Model $record, string $relation): array
    {
        if ($record !== null && $record->exists && method_exists($record, $relation)) {
            $related = $record->{$relation}();

            if ($related instanceof BelongsToMany) {
                $ids = [...$ids, ...$related->pluck($related->getRelated()->getQualifiedKeyName())->all()];
            }
        }

        return array_values(array_unique(array_map(intval(...), $ids)));
    }

    private static function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
