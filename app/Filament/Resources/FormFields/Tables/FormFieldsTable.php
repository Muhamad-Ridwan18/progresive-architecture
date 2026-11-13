<?php

namespace App\Filament\Resources\FormFields\Tables;

use App\Models\FormField;
use App\Models\JobVacancy;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FormFieldsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable()->weight('medium'),
                TextColumn::make('type')->badge(),
                TextColumn::make('key')->color('gray')->toggleable(),
                IconColumn::make('required_default')->label('Required by default')->boolean(),
                IconColumn::make('is_core')->label('Always included')->boolean()->trueIcon('heroicon-o-lock-closed')->falseIcon('heroicon-o-minus')->falseColor('gray'),
                TextColumn::make('used_by')
                    ->label('Used by')
                    ->state(fn (FormField $record): int => JobVacancy::query()->whereJsonContains('form_fields', ['key' => $record->key])->count())
                    ->suffix(' vacancies')
                    ->color('gray'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (FormField $record): bool => $record->is_core)
                    ->modalDescription('Isian dihapus dari pustaka. Lowongan dan lamaran yang sudah ada menyimpan salinannya sendiri dan tidak berubah.'),
            ]);
    }
}
