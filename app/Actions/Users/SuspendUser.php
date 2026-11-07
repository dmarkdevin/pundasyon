<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Stops someone signing in, ends their sessions everywhere and revokes their API tokens.
 */
final class SuspendUser
{
    public function __invoke(User $user): void
    {
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        }

        // API tokens stop working too.
        $user->tokens()->delete();

        activity('users')->performedOn($user)->event('suspended')->log('Suspended the account');
    }
}
