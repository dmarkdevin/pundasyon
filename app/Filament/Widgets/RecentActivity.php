<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\Activities\ActivityResource;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

final class RecentActivity extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Recent activity';

    public static function canView(): bool
    {
        return Auth::user()?->can('activity.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Activity::query()->with('causer')->latest('created_at')->latest('id')->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')->label('When')->since(),
                TextColumn::make('causer.name')->label('Who')->placeholder('System or visitor'),
                TextColumn::make('description')->label('What')->wrap(),
            ])
            ->headerActions([
                Action::make('all')->label('See all')->link()->url(ActivityResource::getUrl()),
            ]);
    }
}
