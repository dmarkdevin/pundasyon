<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Actions\Users\ActivateUser;
use App\Actions\Users\SuspendUser;
use App\Enums\UserStatus;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Actions shared by the user list and the edit page.
 * Nobody can suspend themselves, even super admins.
 */
final class UserActions
{
    public static function suspend(): Action
    {
        return Action::make('suspend')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('They\'ll be signed out everywhere and can\'t sign in until reactivated.')
            ->visible(fn (User $record): bool => $record->status === UserStatus::Active
                && ! $record->trashed()
                && ! $record->is(Auth::user())
                && (Auth::user()?->can('update', $record) ?? false))
            ->action(fn (User $record) => app(SuspendUser::class)($record))
            ->successNotificationTitle('Suspended');
    }

    public static function activate(): Action
    {
        return Action::make('activate')
            ->label('Reactivate')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => $record->status === UserStatus::Suspended
                && ! $record->trashed()
                && (Auth::user()?->can('update', $record) ?? false))
            ->action(fn (User $record) => app(ActivateUser::class)($record))
            ->successNotificationTitle('Reactivated');
    }

    public static function isSelf(User $record): bool
    {
        return $record->is(Auth::user());
    }
}
