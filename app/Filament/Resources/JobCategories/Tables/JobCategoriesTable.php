<?php

namespace App\Filament\Resources\JobCategories\Tables;

use App\Models\JobCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class JobCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('vacancies'))
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('fields')
                    ->label('Default form fields')
                    ->state(fn (JobCategory $record): int => count($record->default_field_keys ?? []))
                    ->suffix(' fields')
                    ->color('gray'),
                TextColumn::make('vacancies_count')
                    ->label('Used by')
                    ->suffix(' vacancies')
                    ->color('gray'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (JobCategory $record): bool => $record->vacancies_count > 0)
                    ->tooltip(fn (JobCategory $record): ?string => $record->vacancies_count > 0
                        ? 'Dipakai oleh '.$record->vacancies_count.' lowongan. Pindahkan lowongan ke kategori lain dulu.'
                        : null),
            ]);
    }
}
