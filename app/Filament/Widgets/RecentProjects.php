<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Lima proyek yang terakhir diubah; nama menuju editor. */
class RecentProjects extends TableWidget
{
    protected static ?int $sort = 4;

    protected static ?string $heading = 'Proyek terbaru';

    protected int|string|array $columnSpan = ['default' => 1, 'lg' => 2];

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Project::query()->with('category')->latest('updated_at')->latest('id')->limit(5))
            ->paginated(false)
            ->columns([
                ImageColumn::make('cover')
                    ->label('')
                    ->state(fn (Project $record): ?string => $record->cover(draft: true)?->file->url(400))
                    ->height(40)
                    ->width(60),
                TextColumn::make('name')
                    ->label('Project')
                    ->state(fn (Project $record): string => $record->draftFields()['name'] ?? $record->name)
                    ->weight('medium')
                    ->description(fn (Project $record): ?string => $record->category?->name)
                    ->url(fn (Project $record): string => ProjectResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('status')->badge(),
                TextColumn::make('updated_at')->label('Updated')->since(),
            ])
            ->emptyStateHeading('Belum ada proyek');
    }
}
