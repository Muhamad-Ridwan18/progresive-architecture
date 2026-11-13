<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Activity Logs (SRS Bagian 9). Hanya baca: log tidak boleh dibuat, diubah, atau dihapus dari dashboard.
 */
class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'SYSTEM';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Activity Logs';

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'Activity Logs';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('causer'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('User')
                    ->placeholder('System'),
                TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => str($state ?? '-')->replace('_', ' ')->headline()->toString()),
                TextColumn::make('description')
                    ->label('Description')
                    ->wrap(),
                TextColumn::make('subject_type')
                    ->label('Affected Object')
                    ->formatStateUsing(fn (?string $state, Activity $record): string => $state
                        ? class_basename($state).' #'.$record->subject_id
                        : str($record->log_name ?? '-')->headline()->toString()),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Module')
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('log_name')
                        ->distinct()
                        ->pluck('log_name', 'log_name')
                        ->all()),
                SelectFilter::make('event')
                    ->label('Action')
                    ->options(fn (): array => Activity::query()
                        ->whereNotNull('event')
                        ->distinct()
                        ->pluck('event', 'event')
                        ->all()),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Activity details')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->fillForm(fn (Activity $record): array => [
                        'before' => self::pretty($record->properties['old'] ?? null),
                        'after' => self::pretty($record->properties['attributes'] ?? null),
                    ])
                    ->schema([
                        Textarea::make('before')->label('Before')->disabled()->rows(8),
                        Textarea::make('after')->label('After')->disabled()->rows(8),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
        ];
    }

    private static function pretty(mixed $value): string
    {
        if ($value === null || $value === [] || (is_object($value) && count((array) $value) === 0)) {
            return '—';
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
    }
}
