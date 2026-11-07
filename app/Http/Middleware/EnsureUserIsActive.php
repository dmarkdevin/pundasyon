<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Filament's sign-in check, except that someone suspended or deleted while signed
 * in is signed out and told why on their next click, instead of seeing a 403.
 */
final class EnsureUserIsActive extends Authenticate
{
    public const SUSPENDED_FLASH = 'account_suspended';

    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();

        if ($user instanceof User && ! $user->isActive()) {
            Filament::auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // The sign-in page shows the message. Filament's notifications are published
            // later in the request than this redirect gets to.
            $request->session()->flash(self::SUSPENDED_FLASH, true);

            throw new HttpResponseException(redirect()->to((string) Filament::getLoginUrl()));
        }

        parent::authenticate($request, $guards);
    }
}
