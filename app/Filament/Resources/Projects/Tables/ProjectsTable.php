<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Actions\Projects\ArchiveProject;
use App\Enums\ProjectStatus;
use App\Models\Category;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category'))
            ->columns([
                ImageColumn::make('cover')
                    ->label('')
                    ->state(fn (Project $record): ?string => $record->cover(draft: true)?->file->url(400))
                    ->height(48)
                    ->width(72),
                // Admin melihat nama draft (yang sedang dikerjakan), bukan hanya versi published.
                TextColumn::make('name')
                    ->label('Name')
                    ->state(fn (Project $record): string => $record->draftFields()['name'] ?? $record->name)
                    ->searchable(),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('year')->label('Year')->placeholder('—'),
                TextColumn::make('status')->badge(),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(ProjectStatus::class),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn (): array => Category::query()->ofType(Project::categoryType())->orderBy('sort_order')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->visible(fn (Project $record): bool => $record->status !== ProjectStatus::Archived)
                    ->requiresConfirmation()
                    ->modalDescription('Proyek tidak akan tampil di website sampai dipulihkan.')
                    ->action(fn (Project $record) => app(ArchiveProject::class)->archive($record)),
                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (Project $record): bool => $record->status === ProjectStatus::Archived)
                    ->action(fn (Project $record) => app(ArchiveProject::class)->restore($record)),
                DeleteAction::make()
                    ->modalDescription('Proyek dan relasi gambarnya akan dihapus. Gambar masuk antrean penghapusan 7 hari.'),
            ]);
    }
}
