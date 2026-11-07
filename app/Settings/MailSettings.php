<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Overrides the .env mail settings when filled in. Empty fields keep the .env values.
 */
final class MailSettings extends Settings
{
    public ?string $from_address;

    public ?string $from_name;

    public ?string $smtp_host;

    public ?int $smtp_port;

    public ?string $smtp_username;

    /** Stored encrypted with APP_KEY. */
    public ?string $smtp_password;

    /** "tls", "ssl" or null for none. */
    public ?string $smtp_encryption;

    public static function group(): string
    {
        return 'mail';
    }

    /**
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return ['smtp_password'];
    }
}
