<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use Illuminate\Database\Eloquent\Model;

/**
 * Records roles or permissions being added or removed. These live in pivot
 * tables, which don't fire model events, so the activity log can't see them alone.
 */
final class LogAccessChange
{
    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     */
    public static function between(Model $subject, string $log, array $before, array $after, ?string $description = null): void
    {
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        if ($added === [] && $removed === []) {
            return;
        }

        activity($log)
            ->performedOn($subject)
            ->event('access-changed')
            ->withProperties(['added' => $added, 'removed' => $removed])
            ->log($description ?? 'Changed '.($log === 'users' ? 'roles' : 'permissions'));
    }
}
