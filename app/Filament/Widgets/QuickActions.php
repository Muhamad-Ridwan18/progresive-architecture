<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\JobVacancies\JobVacancyResource;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Widgets\Widget;

/** Pintasan ke pekerjaan yang paling sering dilakukan admin. */
class QuickActions extends Widget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.quick-actions';

    /** @return list<array{icon: string, label: string, url: string}> */
    public function actions(): array
    {
        return [
            ['icon' => 'heroicon-o-folder-plus', 'label' => 'New project', 'url' => ProjectResource::getUrl('create')],
            ['icon' => 'heroicon-o-newspaper', 'label' => 'New article', 'url' => ArticleResource::getUrl('create')],
            ['icon' => 'heroicon-o-briefcase', 'label' => 'New vacancy', 'url' => JobVacancyResource::getUrl('create')],
            ['icon' => 'heroicon-o-cog-6-tooth', 'label' => 'Settings', 'url' => ManageSettings::getUrl()],
        ];
    }
}
