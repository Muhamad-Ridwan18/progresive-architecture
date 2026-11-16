<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kelola proyek yang tampil di website. Perubahan disimpan sebagai draft sampai di-publish.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New project')];
    }
}
