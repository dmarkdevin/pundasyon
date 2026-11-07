<?php

declare(strict_types=1);

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * The audit log: who did what, to what, and when. Read-only.
 */
final class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'activity log';

    protected static ?string $slug = 'activity';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['causer', 'subject']))
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable()->description(fn (Activity $record): string => $record->created_at?->diffForHumans() ?? ''),
                TextColumn::make('causer.name')->label('Who')->placeholder('System or visitor')->searchable(),
                TextColumn::make('description')->label('What')->searchable()->wrap(),
                TextColumn::make('subject_type')->label('On')->formatStateUsing(fn (Activity $record): string => self::subjectLabel($record))->placeholder('-'),
                TextColumn::make('log_name')->label('Area')->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('log_name')->label('Area')->options(fn (): array => self::distinct('log_name')),
                SelectFilter::make('event')->options(fn (): array => self::distinct('event')),
                SelectFilter::make('causer_id')
                    ->label('Who')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => User::query()->withTrashed()->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->limit(20)->pluck('name', 'id')->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => User::query()->withTrashed()->whereKey($value)->first()?->name)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', $data['value'])
                        : $query),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextEntry::make('description')->label('What'),
                TextEntry::make('created_at')->label('When')->dateTime(),
                TextEntry::make('causer.name')->label('Who')->placeholder('System or visitor'),
                TextEntry::make('subject_type')->label('On')->formatStateUsing(fn (Activity $record): string => self::subjectLabel($record))->placeholder('-'),
                TextEntry::make('log_name')->label('Area')->badge(),
                TextEntry::make('event')->placeholder('-'),
            ]),
            Section::make('Changes')
                ->visible(fn (Activity $record): bool => filled($record->attribute_changes?->get('attributes')))
                ->schema([
                    ViewEntry::make('attribute_changes')->hiddenLabel()->view('filament.infolists.activity-changes'),
                ]),
            Section::make('Details')
                ->visible(fn (Activity $record): bool => filled($record->properties) && $record->properties->isNotEmpty())
                ->schema([
                    KeyValueEntry::make('properties')
                        ->hiddenLabel()
                        ->state(fn (Activity $record): array => $record->properties?->map(fn (mixed $value): string => is_scalar($value) || $value === null ? (string) $value : (string) json_encode($value))->all() ?? []),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    private static function subjectLabel(Activity $record): string
    {
        if ($record->subject_type === null) {
            return '-';
        }

        $type = Str::headline(class_basename($record->subject_type));
        $subject = $record->subject;
        $name = $subject instanceof Model ? ($subject->getAttribute('name') ?? null) : null;

        return is_string($name) && $name !== '' ? "{$type}: {$name}" : "{$type} #{$record->subject_id}";
    }

    /**
     * @return array<string, string>
     */
    private static function distinct(string $column): array
    {
        return Activity::query()->whereNotNull($column)->distinct()->orderBy($column)->pluck($column, $column)
            ->map(fn (mixed $value): string => Str::headline((string) $value))
            ->all();
    }
}
