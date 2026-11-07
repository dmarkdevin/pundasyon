<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Filament\Auth\Events\Registered as FilamentRegistered;
use Illuminate\Auth\Events\Registered;

/**
 * People who sign up themselves get the plain user role.
 */
final class AssignDefaultRole
{
    /** Laravel's event, for sign-ups outside Filament (for example the API). */
    public function handle(Registered $event): void
    {
        $this->assign($event->user);
    }

    /** Filament's sign-up page fires its own event instead. */
    public function handleFilament(FilamentRegistered $event): void
    {
        $this->assign($event->getUser());
    }

    private function assign(mixed $user): void
    {
        if ($user instanceof User && Role::query()->where('name', SystemRole::User->value)->exists()) {
            $user->assignRole(SystemRole::User->value);
        }
    }
}
