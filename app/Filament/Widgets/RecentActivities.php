<?php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/** Aktivitas terbaru: siapa, melakukan apa, objek yang terdampak, dan kapan (SRS FR-DB-011). */
class RecentActivities extends TableWidget
{
    protected static ?int $sort = 9;

    protected static ?string $heading = 'Recent Activities';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Activity::query()->with(['causer', 'subject'])->latest('id')->limit(8))
            ->paginated(false)
            ->columns([
                TextColumn::make('causer.name')->label('User')->placeholder('System'),
                TextColumn::make('description')->label('Activity')->wrap(),
                TextColumn::make('subject_type')
                    ->label('Object')
                    ->state(fn (Activity $record): string => self::objectLabel($record)),
                TextColumn::make('created_at')->label('Time')->since()->tooltip(fn (Activity $record): string => $record->created_at->format('d M Y H:i:s')),
            ])
            ->emptyStateHeading('Belum ada aktivitas');
    }

    /** Nama objek yang terdampak; jatuh ke nama yang tersimpan di log bila objeknya sudah dihapus. */
    private static function objectLabel(Activity $record): string
    {
        $subject = $record->subject;

        if ($subject instanceof Model) {
            $name = $subject->name ?? $subject->title ?? $subject->applicant_name ?? null;

            return trim(class_basename($subject).($name ? ': '.$name : ''));
        }

        $old = $record->properties['old'] ?? [];
        $name = $old['name'] ?? $old['title'] ?? null;

        return $record->subject_type
            ? trim(class_basename($record->subject_type).($name ? ': '.$name : ' (dihapus)'))
            : str($record->log_name ?? '—')->headline()->toString();
    }
}
