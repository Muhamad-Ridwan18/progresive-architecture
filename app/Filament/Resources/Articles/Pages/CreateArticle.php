<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Actions\Articles\CreateArticle as CreateArticleAction;
use App\Filament\Resources\Articles\ArticleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    /** Artikel dibuat sebagai Draft lewat Action, lalu admin langsung masuk ke editor untuk melengkapinya. */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateArticleAction::class)->handle($data, auth()->id());
    }

    protected function getRedirectUrl(): string
    {
        return ArticleResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
