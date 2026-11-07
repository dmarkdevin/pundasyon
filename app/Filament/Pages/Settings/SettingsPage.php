<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Actions\Settings\ApplySettings;
use Filament\Pages\SettingsPage as BaseSettingsPage;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Shared by the settings pages: who may open them, and what happens after saving.
 */
abstract class SettingsPage extends BaseSettingsPage
{
    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    /** @var array<string, mixed> */
    private array $before = [];

    public static function settingsLabel(): string
    {
        return static::$title ?? class_basename(static::class);
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('settings.update') ?? false;
    }

    protected function beforeSave(): void
    {
        $this->before = app(static::getSettings())->toArray();
    }

    /**
     * Applies the new values at once, and records which settings changed. Values
     * themselves aren't logged, so the SMTP password never reaches the log.
     */
    protected function afterSave(): void
    {
        app(ApplySettings::class)();

        $after = app(static::getSettings())->toArray();
        $changed = array_keys(array_filter($after, fn (mixed $value, string $key): bool => ($this->before[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));

        if ($changed !== []) {
            activity('settings')
                ->event('updated')
                ->withProperties(['group' => app(static::getSettings())::group(), 'changed' => $changed])
                ->log('Changed '.static::settingsLabel().' settings');
        }
    }
}
