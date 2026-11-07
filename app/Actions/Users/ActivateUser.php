<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\User;

final class ActivateUser
{
    public function __invoke(User $user): void
    {
        $user->forceFill(['status' => UserStatus::Active])->save();

        activity('users')->performedOn($user)->event('activated')->log('Reactivated the account');
    }
}
