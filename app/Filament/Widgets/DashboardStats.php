<?php

namespace App\Filament\Widgets;

use App\Enums\JobStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Filament\Resources\JobVacancies\JobVacancyResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Article;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Project;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/** Empat metrik dashboard (SRS FR-DB-010). Setiap metrik menuju halaman pengelolaannya. */
class DashboardStats extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $activeVacancies = JobVacancy::query()
            ->where('status', JobStatus::Published->value)
            ->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>=', today()))
            ->count();

        return [
            Stat::make('Total Projects', Project::query()->count())
                ->description($this->recent(Project::query(), 'created_at').' bulan ini')
                ->descriptionColor('success')
                ->chart($this->weekly(Project::query(), 'created_at'))
                ->url(ProjectResource::getUrl('index')),
            Stat::make('Published Articles', Article::query()->live()->count())
                ->description($this->recent(Article::query()->live(), 'published_at').' bulan ini')
                ->descriptionColor('success')
                ->chart($this->weekly(Article::query()->live(), 'published_at'))
                ->url(ArticleResource::getUrl('index')),
            Stat::make('Active Job Vacancies', $activeVacancies)
                ->description('Published dan belum lewat deadline')
                ->chart($this->weekly(JobVacancy::query(), 'created_at'))
                ->url(JobVacancyResource::getUrl('index')),
            Stat::make('Total Applications', JobApplication::query()->count())
                ->description($this->recent(JobApplication::query(), 'submitted_at', 7).' minggu ini')
                ->descriptionColor('success')
                ->chart($this->weekly(JobApplication::query(), 'submitted_at'))
                ->color('primary')
                ->url(JobApplicationResource::getUrl('index')),
        ];
    }

    /** Jumlah baris baru dalam {@see $days} hari terakhir (bawaan 30 hari), diberi tanda "+". */
    private function recent(Builder $query, string $column, int $days = 30): string
    {
        return '+'.$query->where($column, '>=', now()->subDays($days))->count();
    }

    /**
     * Grafik mini: jumlah baris baru per minggu selama 7 minggu terakhir.
     *
     * @return list<int>
     */
    private function weekly(Builder $query, string $column): array
    {
        return collect(range(6, 0))
            ->map(fn (int $ago): int => (clone $query)
                ->whereBetween($column, [now()->subWeeks($ago)->startOfWeek(), now()->subWeeks($ago)->endOfWeek()])
                ->count())
            ->all();
    }
}
