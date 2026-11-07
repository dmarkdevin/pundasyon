<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Activity\LogAccessChange;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\PermissionRegistrar;

final class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * An administrator vouches for the address, so no confirmation email is sent.
     */
    protected function afterCreate(): void
    {
        $user = $this->getRecord();

        if ($user instanceof User) {
            $user->markEmailAsVerified();
            LogAccessChange::between($user, 'users', [], array_values(array_map(strval(...), $user->roles()->pluck('name')->all())));
            LogAccessChange::between($user, 'users', [], array_values(array_map(strval(...), $user->permissions()->pluck('name')->all())), 'Changed direct permissions');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
