<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Tables;

use App\Models\Role;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('users_count')->label('People')->sortable(),
                TextColumn::make('permissions_count')
                    ->label('Permissions')
                    ->formatStateUsing(fn (int $state, Role $record): string => $record->isSuperAdmin() ? 'All' : (string) $state),
                IconColumn::make('system')
                    ->label('Built in')
                    ->state(fn (Role $record): bool => $record->isSystem())
                    ->boolean()
                    ->falseColor('gray'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()->hidden(fn (Role $record): bool => $record->isSuperAdmin()),
                DeleteAction::make()->hidden(fn (Role $record): bool => $record->isSystem()),
            ])
            ->checkIfRecordIsSelectableUsing(fn (Role $record): bool => ! $record->isSystem())
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }
}
