<?php

namespace App\Filament\Resources\JobApplications\Pages;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Filament\Resources\JobApplications\Widgets\ApplicantStats;
use App\Models\JobApplication;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListJobApplications extends ListRecords
{
    protected static string $resource = JobApplicationResource::class;

    /** Tautan jumlah pelamar dari Job Vacancies membuka daftar ini sudah terfilter (`?vacancy=id`). */
    public function mount(): void
    {
        parent::mount();

        if (filled($vacancy = request()->query('vacancy'))) {
            $this->tableFilters['job_vacancy_id']['value'] = (string) $vacancy;
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [ApplicantStats::class];
    }

    /**
     * Tab status dengan jumlahnya, supaya admin langsung melihat berapa lamaran yang menunggu tindakan.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = JobApplication::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $tabs = ['all' => Tab::make('All')->badge((int) $counts->sum())];

        foreach (ApplicationStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->badge((int) ($counts[$status->value] ?? 0))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status->value));
        }

        return $tabs;
    }
}
