<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Settings\GeneralSettings;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class ManageGeneral extends SettingsPage
{
    protected static string $settings = GeneralSettings::class;

    protected static ?string $title = 'General';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'settings/general';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Brand')->schema([
                TextInput::make('app_name')->label('App name')->required()->maxLength(60),
                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->helperText('PNG, JPG or WebP, up to 1 MB. Shown on the sign-in page and in the top bar. SVG isn\'t accepted because it can carry scripts.')
                    ->image()
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(1024)
                    ->disk('public')
                    ->directory('branding')
                    ->visibility('public')
                    // Refuse any path the browser sends that this upload didn't create.
                    ->preventFilePathTampering(),
            ]),
            Section::make('Maintenance')->schema([
                Textarea::make('maintenance_message')
                    ->label('Maintenance message')
                    ->helperText('Shown to visitors while the app is down for maintenance (`php artisan down`).')
                    ->rows(3)
                    ->maxLength(500),
            ]),
        ]);
    }
}
