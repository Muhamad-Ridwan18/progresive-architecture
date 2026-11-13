<?php

namespace App\Filament\Widgets;

use App\Enums\JobStatus;
use App\Filament\Resources\JobVacancies\JobVacancyResource;
use App\Models\JobVacancy;
use Filament\Widgets\Widget;

/** Rekrutmen (jarang dipakai, jadi kecil): lowongan terbuka dengan deadline terdekat dan jumlah pelamarnya. */
class VacancyDeadlines extends Widget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.vacancy-deadlines';

    /** @return list<array{title: string, applicants: int, days: int, tone: string, url: string}> */
    public function vacancies(): array
    {
        return JobVacancy::query()
            ->where('status', JobStatus::Published->value)
            ->whereDate('deadline', '>=', today())
            ->withCount('applications')
            ->orderBy('deadline')
            ->limit(4)
            ->get()
            ->map(function (JobVacancy $vacancy): array {
                $days = (int) today()->diffInDays($vacancy->deadline, false);

                return [
                    'title' => $vacancy->title,
                    'applicants' => $vacancy->applications_count,
                    'days' => $days,
                    'tone' => $days <= 3 ? 'red' : ($days <= 14 ? 'amber' : 'slate'),
                    'url' => JobVacancyResource::getUrl('edit', ['record' => $vacancy]),
                ];
            })
            ->all();
    }
}
