<?php

namespace App\Filament\Resources\JobVacancies\Tables;

use App\Enums\JobStatus;
use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Models\JobCategory;
use App\Models\JobVacancy;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JobVacanciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category')->withCount('applications'))
            ->columns([
                TextColumn::make('title')->label('Position')->searchable()->weight('medium'),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('location')->searchable(),
                TextColumn::make('employment_type')->label('Employment type'),
                // Jumlah pelamar membuka daftar pelamar yang sudah difilter (SRS FR-RC-002).
                TextColumn::make('applications_count')
                    ->label('Applicants')
                    ->numeric()
                    ->alignEnd()
                    ->url(fn (JobVacancy $record): string => JobApplicationResource::getUrl('index', ['vacancy' => $record->getKey()])),
                TextColumn::make('deadline')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->color(fn (JobVacancy $record): ?string => $record->deadline?->isBefore(today()) ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('job_category_id')
                    ->label('Category')
                    ->options(fn (): array => JobCategory::query()->orderBy('sort_order')->pluck('name', 'id')->all()),
                SelectFilter::make('status')->options(JobStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                // Lowongan yang sudah punya pelamar tidak dihapus: data pelamar ikut hilang. Gunakan status Closed.
                DeleteAction::make()
                    ->disabled(fn (JobVacancy $record): bool => $record->applications_count > 0)
                    ->tooltip(fn (JobVacancy $record): ?string => $record->applications_count > 0
                        ? 'Sudah punya pelamar. Ubah status menjadi Closed.'
                        : null),
            ]);
    }
}
