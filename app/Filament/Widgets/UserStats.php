<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\LoginEventType;
use App\Enums\UserStatus;
use App\Models\LoginEvent;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

final class UserStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->can('users.view') ?? false;
    }

    protected function getStats(): array
    {
        // "Today" and "this week" as the people reading the dashboard see them.
        $local = now((string) config('app.display_timezone'));
        $today = $local->copy()->startOfDay()->utc();

        return [
            Stat::make('Active users', number_format(User::query()->where('status', UserStatus::Active)->count())),
            Stat::make('New this week', number_format(User::query()->where('created_at', '>=', $local->copy()->startOfWeek()->utc())->count())),
            Stat::make('Sign-ins today', number_format(LoginEvent::query()->where('type', LoginEventType::Succeeded)->where('created_at', '>=', $today)->count()))
                ->description(LoginEvent::query()->where('type', '!=', LoginEventType::Succeeded)->where('created_at', '>=', $today)->count().' failed or blocked'),
        ];
    }
}
