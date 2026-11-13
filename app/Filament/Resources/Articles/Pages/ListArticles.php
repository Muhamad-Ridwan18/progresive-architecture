<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New article')];
    }

    /**
     * Tab status dengan jumlahnya, supaya admin langsung melihat berapa artikel yang masih draft atau terjadwal.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = Article::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $tabs = ['all' => Tab::make('All')->badge((int) $counts->sum())];

        foreach (ArticleStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->badge((int) ($counts[$status->value] ?? 0))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status->value));
        }

        return $tabs;
    }
}
