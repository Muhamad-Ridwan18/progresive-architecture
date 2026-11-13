<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\Project;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/** Status seluruh konten (proyek dan artikel) dalam grafik donat. */
class ContentStatus extends ChartWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Status konten';

    protected ?string $description = 'Proyek dan artikel';

    protected ?string $maxHeight = '200px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $count = fn (string $status): int => Project::query()->where('status', $status)->count() + Article::query()->where('status', $status)->count();

        return [
            'datasets' => [[
                'data' => [$count('published'), $count('draft'), $count('unpublished_changes'), $count('scheduled')],
                'backgroundColor' => ['#22c55e', '#94a3b8', '#f59e0b', '#3b82f6'],
                'borderWidth' => 0,
            ]],
            'labels' => ['Published', 'Draft', 'Unpublished changes', 'Scheduled'],
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return [
            'cutout' => '68%',
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
