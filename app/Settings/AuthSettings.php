<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Sign-in rules an administrator can change without a deploy.
 */
final class AuthSettings extends Settings
{
    public bool $registration_enabled;

    public int $password_min_length;

    public bool $password_requires_mixed_case;

    public bool $password_requires_numbers;

    public bool $password_requires_symbols;

    /** Refuse passwords found in public data breaches (checked with Have I Been Pwned, without sending the password). */
    public bool $password_must_not_be_breached;

    /** Failed sign-ins on one account before it's locked. */
    public int $lockout_attempts;

    public int $lockout_minutes;

    public static function group(): string
    {
        return 'auth';
    }
}
