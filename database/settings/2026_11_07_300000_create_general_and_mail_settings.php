<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.app_name', config('app.name', 'Pundasyon'));
        $this->migrator->add('general.logo_path', null);
        $this->migrator->add('general.maintenance_message', null);

        $this->migrator->add('mail.from_address', null);
        $this->migrator->add('mail.from_name', null);
        $this->migrator->add('mail.smtp_host', null);
        $this->migrator->add('mail.smtp_port', null);
        $this->migrator->add('mail.smtp_username', null);
        $this->migrator->addEncrypted('mail.smtp_password', null);
        $this->migrator->add('mail.smtp_encryption', null);
    }
};
