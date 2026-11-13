<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\Project;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/** Proyek dan artikel yang dipublikasikan per bulan selama 6 bulan terakhir. */
class ContentChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = ['default' => 1, 'lg' => 2];

    protected ?string $heading = 'Konten dipublikasikan';

    protected ?string $description = 'Proyek dan artikel, per bulan, 6 bulan terakhir';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $months = collect(range(5, 0))->map(fn (int $ago): Carbon => now()->startOfMonth()->subMonths($ago));

        $count = fn (string $model, Carbon $start): int => $model::query()
            ->whereBetween('published_at', [$start, $start->copy()->endOfMonth()])
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Proyek',
                    'data' => $months->map(fn (Carbon $m): int => $count(Project::class, $m))->all(),
                    'backgroundColor' => '#334155',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Artikel',
                    'data' => $months->map(fn (Carbon $m): int => $count(Article::class, $m))->all(),
                    'backgroundColor' => '#6366f1',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $months->map(fn (Carbon $m): string => $m->locale('id')->translatedFormat('M Y'))->all(),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['borderDash' => [3, 4]]],
                'x' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
