<?php

namespace App\Filament\Resources\JobApplications\Tables;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Models\JobVacancy;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class JobApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('vacancy'))
            ->columns([
                // Nama dan email satu sel; pencarian mencakup keduanya.
                TextColumn::make('applicant_name')
                    ->label('Applicant')
                    ->searchable(['applicant_name', 'applicant_email'])
                    ->weight('medium')
                    ->description(fn (JobApplication $record): string => $record->applicant_email),
                TextColumn::make('vacancy.title')->label('Position'),
                TextColumn::make('applicant_phone')->label('Phone')->placeholder('—'),
                TextColumn::make('submitted_at')->label('Applied')->date('d M Y')->sortable(),
                TextColumn::make('files_count')->counts('files')->label('Docs')->icon('heroicon-o-document-text'),
                TextColumn::make('status')->badge(),
                TextColumn::make('reference_number')->label('Reference')->searchable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('job_vacancy_id')
                    ->label('Job')
                    ->options(fn (): array => JobVacancy::query()->orderBy('title')->pluck('title', 'id')->all()),
                SelectFilter::make('category')
                    ->label('Category')
                    ->options(fn (): array => JobCategory::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('vacancy', fn (Builder $q) => $q->where('job_category_id', $data['value']))
                        : $query),
                SelectFilter::make('status')->options(ApplicationStatus::class),
                Filter::make('period')
                    ->label('Date')
                    ->schema([
                        Select::make('days')
                            ->label('Date')
                            ->options([7 => '7 hari terakhir', 30 => '30 hari terakhir', 90 => '90 hari terakhir']),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['days'] ?? null)
                        ? $query->where('submitted_at', '>=', now()->subDays((int) $data['days'])->startOfDay())
                        : $query)
                    ->indicateUsing(fn (array $data): ?string => filled($data['days'] ?? null) ? 'Last '.$data['days'].' days' : null),
            ])
            ->recordActions([
                ViewAction::make()->label('View'),
            ]);
    }
}
