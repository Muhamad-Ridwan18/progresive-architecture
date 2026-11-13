<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Enums\CategoryType;
use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Membuka form pembuatan dengan jenis sesuai tab yang sedang aktif.
            CreateAction::make()
                ->url(fn (): string => CategoryResource::getUrl('create', ['type' => $this->activeTab])),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [];

        foreach (CategoryType::cases() as $type) {
            $tabs[$type->value] = Tab::make($type->getLabel())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', $type->value));
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return CategoryType::Project->value;
    }
}
