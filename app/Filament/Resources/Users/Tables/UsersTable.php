<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserActions;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use STS\FilamentImpersonate\Actions\Impersonate;

final class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->state(fn (User $record): string => filament()->getUserAvatarUrl($record))
                    ->circular()
                    ->imageSize(32),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->label('Email address')->searchable()->sortable()->copyable(),
                TextColumn::make('roles.name')->label('Roles')->badge(),
                TextColumn::make('status')->badge()->sortable(),
                IconColumn::make('email_verified_at')
                    ->label('Confirmed')
                    ->state(fn (User $record): bool => $record->email_verified_at !== null)
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('created_at')->label('Joined')->since()->sortable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('roles')->relationship('roles', 'name')->multiple()->preload(),
                SelectFilter::make('status')->options(UserStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Impersonate::make(),
                    UserActions::suspend(),
                    UserActions::activate(),
                    DeleteAction::make()->hidden(fn (User $record): bool => UserActions::isSelf($record)),
                    RestoreAction::make(),
                    ForceDeleteAction::make()->hidden(fn (User $record): bool => UserActions::isSelf($record)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Each record is checked against the policy, so yourself and super admins are skipped.
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                    RestoreBulkAction::make()->authorizeIndividualRecords('restore'),
                ]),
            ]);
    }
}
