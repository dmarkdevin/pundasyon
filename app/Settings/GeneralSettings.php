<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class GeneralSettings extends Settings
{
    public string $app_name;

    /** On the public disk, because the sign-in page shows it. */
    public ?string $logo_path;

    /** Shown on the page visitors see during `php artisan down`. */
    public ?string $maintenance_message;

    public static function group(): string
    {
        return 'general';
    }
}
