<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Settings\AuthSettings;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;

/**
 * Filament's sign-up page, closed when an administrator turns sign-up off.
 * Checked on every request, so the setting works without clearing caches.
 */
final class Register extends BaseRegister
{
    public function mount(): void
    {
        abort_unless(app(AuthSettings::class)->registration_enabled, 404);

        parent::mount();
    }

    public function register(): ?RegistrationResponse
    {
        abort_unless(app(AuthSettings::class)->registration_enabled, 404);

        return parent::register();
    }
}
