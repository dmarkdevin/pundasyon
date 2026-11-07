<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use App\Settings\AuthSettings;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class ManageSecurity extends SettingsPage
{
    protected static string $settings = AuthSettings::class;

    protected static ?string $title = 'Sign-in and passwords';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'settings/security';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Sign-up')->schema([
                Toggle::make('registration_enabled')
                    ->label('Anyone can sign up')
                    ->helperText('When off, only people an administrator adds can get in.'),
            ]),
            Section::make('Passwords')
                ->description('Applies to sign-up, password resets and profile changes. Existing passwords keep working.')
                ->columns(2)
                ->schema([
                    TextInput::make('password_min_length')->label('Minimum length')->integer()->required()->minValue(8)->maxValue(128),
                    Toggle::make('password_requires_mixed_case')->label('Upper and lower case letters'),
                    Toggle::make('password_requires_numbers')->label('At least one number'),
                    Toggle::make('password_requires_symbols')->label('At least one symbol'),
                    Toggle::make('password_must_not_be_breached')
                        ->label('Refuse passwords found in data breaches')
                        ->helperText('Checked with Have I Been Pwned. Only the first 5 characters of the password\'s hash leave the server.')
                        ->columnSpanFull(),
                ]),
            Section::make('Account lockout')
                ->description('After this many failed sign-ins, from anywhere, the account is locked for a while.')
                ->columns(2)
                ->schema([
                    TextInput::make('lockout_attempts')->label('Failed attempts')->integer()->required()->minValue(3)->maxValue(50),
                    TextInput::make('lockout_minutes')->label('Locked for (minutes)')->integer()->required()->minValue(1)->maxValue(1440),
                ]),
        ]);
    }
}
