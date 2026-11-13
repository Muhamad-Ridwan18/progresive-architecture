<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Models\JobApplication;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Lima pelamar terbaru; nama menuju detail lamaran (SRS FR-DB-012). */
class RecentApplications extends TableWidget
{
    protected static ?int $sort = 8;

    protected static ?string $heading = 'Recent Applications';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => JobApplication::query()->with('vacancy')->latest('submitted_at')->latest('id')->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('applicant_name')
                    ->label('Applicant')
                    ->weight('medium')
                    ->url(fn (JobApplication $record): string => JobApplicationResource::getUrl('view', ['record' => $record])),
                TextColumn::make('vacancy.title')->label('Position'),
                TextColumn::make('submitted_at')->label('Date')->date('d M Y'),
                TextColumn::make('status')->badge(),
            ])
            ->emptyStateHeading('Belum ada lamaran');
    }
}
