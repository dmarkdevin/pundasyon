<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('auth.registration_enabled', true);
        $this->migrator->add('auth.password_min_length', 12);
        $this->migrator->add('auth.password_requires_mixed_case', true);
        $this->migrator->add('auth.password_requires_numbers', true);
        $this->migrator->add('auth.password_requires_symbols', false);
        $this->migrator->add('auth.password_must_not_be_breached', true);
        $this->migrator->add('auth.lockout_attempts', 5);
        $this->migrator->add('auth.lockout_minutes', 15);
    }
};
