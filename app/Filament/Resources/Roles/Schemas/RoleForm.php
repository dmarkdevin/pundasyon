<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Schemas;

use App\Models\Permission;
use App\Models\Role;
use App\Support\AssignableAccess;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(100)
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->disabled(fn (?Role $record): bool => $record?->isSystem() ?? false)
                        ->helperText(fn (?Role $record): string => $record?->isSystem() ? 'Built-in roles can\'t be renamed.' : 'Lowercase with dashes, for example "support-agent".'),
                    Text::make('Super admins can do everything, so they don\'t need permissions.')
                        ->visible(fn (?Role $record): bool => $record?->isSuperAdmin() ?? false),
                    CheckboxList::make('permissions')
                        ->relationship('permissions', 'name', modifyQueryUsing: fn (Builder $query, ?Model $record) => AssignableAccess::permissions($query, $record))
                        ->getOptionLabelFromRecordUsing(fn (Permission $permission): string => $permission->label)
                        ->in(fn (?Model $record): array => AssignableAccess::permissionIds($record))
                        ->helperText('You can only give out permissions you have yourself.')
                        ->columns(3)
                        ->searchable()
                        ->bulkToggleable()
                        ->hidden(fn (?Role $record): bool => $record?->isSuperAdmin() ?? false),
                ]),
        ]);
    }
}
