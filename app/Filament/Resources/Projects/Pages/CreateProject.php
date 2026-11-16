<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Actions\Projects\CreateProject as CreateProjectAction;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Isi nama dan kategori dulu. Detail, cover, dan galeri diisi di editor setelah proyek dibuat.';
    }

    /** Proyek dibuat sebagai Draft lewat Action, lalu admin langsung masuk ke editor untuk melengkapinya. */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateProjectAction::class)->handle($data, auth()->id());
    }

    protected function getRedirectUrl(): string
    {
        return ProjectResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
