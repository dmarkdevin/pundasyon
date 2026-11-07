<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Permission;
use App\Models\User;
use App\Support\AssignableAccess;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('email')->label('Email address')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->rule(Password::default())
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->same('passwordConfirmation')
                        ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave empty to keep the current password.' : null),
                    TextInput::make('passwordConfirmation')
                        ->label('Confirm password')
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation, callable $get): bool => $operation === 'create' || filled($get('password')))
                        ->dehydrated(false),
                ]),

            Section::make('Access')
                ->description(fn (?User $record): string => $record?->is(Auth::user())
                    ? 'You can\'t change your own roles or permissions. Ask another administrator.'
                    : 'You can only give out roles and permissions you have yourself.')
                ->schema([
                    CheckboxList::make('roles')
                        ->relationship('roles', 'name', modifyQueryUsing: fn (Builder $query, ?Model $record) => AssignableAccess::roles($query, $record))
                        ->in(fn (?Model $record): array => AssignableAccess::roleIds($record))
                        ->columns(3)
                        ->disabled(fn (?User $record): bool => $record?->is(Auth::user()) ?? false),
                    CheckboxList::make('permissions')
                        ->label('Extra permissions')
                        ->helperText('On top of what their roles give them. Usually not needed.')
                        ->relationship('permissions', 'name', modifyQueryUsing: fn (Builder $query, ?Model $record) => AssignableAccess::permissions($query, $record))
                        ->getOptionLabelFromRecordUsing(fn (Permission $permission): string => $permission->label)
                        ->in(fn (?Model $record): array => AssignableAccess::permissionIds($record))
                        ->columns(3)
                        ->searchable()
                        ->bulkToggleable()
                        ->disabled(fn (?User $record): bool => $record?->is(Auth::user()) ?? false),
                ]),
        ]);
    }
}
