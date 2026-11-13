<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('usage')
                    ->label('Used by')
                    ->state(fn (Category $record): int => $record->usageCount())
                    ->suffix(' items')
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (Category $record): bool => $record->isInUse())
                    ->tooltip(fn (Category $record): ?string => $record->isInUse()
                        ? 'Dipakai oleh '.$record->usageCount().' konten. Pindahkan konten ke kategori lain dulu.'
                        : null),
            ]);
    }
}
