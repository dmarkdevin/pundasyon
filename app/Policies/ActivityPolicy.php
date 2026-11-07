<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * The activity log can be read, never changed: no one edits or deletes history
 * from the app. Old entries are removed by `activitylog:clean` on a schedule.
 */
final class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('activity.view');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can('activity.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Activity $activity): bool
    {
        return false;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
