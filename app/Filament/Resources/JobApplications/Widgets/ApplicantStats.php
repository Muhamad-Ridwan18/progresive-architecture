<?php

namespace App\Filament\Resources\JobApplications\Widgets;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ringkasan di atas daftar pelamar. Hanya tampil di halaman ini, bukan di dashboard utama. */
class ApplicantStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $count = fn (array $statuses) => JobApplication::query()->whereIn('status', array_map(fn (ApplicationStatus $s): string => $s->value, $statuses))->count();
        $thisWeek = JobApplication::query()->where('submitted_at', '>=', now()->startOfWeek())->count();

        return [
            Stat::make('Total applicants', JobApplication::query()->count())
                ->description($thisWeek.' minggu ini')
                ->descriptionColor('success'),
            Stat::make('Perlu ditinjau', $count([ApplicationStatus::Submitted, ApplicationStatus::Reviewing]))
                ->description('Submitted dan Reviewing')
                ->descriptionColor('warning'),
            Stat::make('Interview', $count([ApplicationStatus::Interview]))
                ->description('Sedang dalam tahap wawancara'),
            Stat::make('Diterima', $count([ApplicationStatus::Accepted]))
                ->description('Total keseluruhan'),
        ];
    }
}
