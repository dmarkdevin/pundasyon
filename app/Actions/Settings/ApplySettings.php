<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Copies database settings into the running config: the app name, and mail
 * settings when an administrator has filled them in. Runs at boot (and in queue
 * workers, so emails use them too) and again after the settings pages save.
 * Does nothing before the settings table exists, such as during the first migrate.
 */
final class ApplySettings
{
    public function __invoke(): void
    {
        // Settings load lazily, so a missing table or setting only shows up when a value is read.
        try {
            $this->apply(app(GeneralSettings::class), app(MailSettings::class));
        } catch (Throwable) {
            // Not migrated yet: keep the .env values.
        }
    }

    private function apply(GeneralSettings $general, MailSettings $mail): void
    {
        config(['app.name' => $general->app_name]);

        if (filled($mail->from_address)) {
            config(['mail.from.address' => $mail->from_address]);
        }

        config(['mail.from.name' => filled($mail->from_name) ? $mail->from_name : $general->app_name]);

        if (filled($mail->smtp_host)) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $mail->smtp_host,
                'mail.mailers.smtp.port' => $mail->smtp_port ?? 587,
                'mail.mailers.smtp.username' => $mail->smtp_username,
                'mail.mailers.smtp.password' => $mail->smtp_password,
                'mail.mailers.smtp.scheme' => $mail->smtp_encryption === 'ssl' ? 'smtps' : null,
            ]);

            // A mailer already made with the old settings would keep using them.
            Mail::forgetMailers();
        }
    }
}
