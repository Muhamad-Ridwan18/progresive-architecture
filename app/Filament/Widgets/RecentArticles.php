<?php

namespace App\Filament\Widgets;

use App\Enums\ArticleStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Lima artikel yang terakhir diubah; judul menuju editor. */
class RecentArticles extends TableWidget
{
    protected static ?int $sort = 6;

    protected static ?string $heading = 'Artikel terbaru';

    protected int|string|array $columnSpan = ['default' => 1, 'lg' => 2];

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Article::query()->with('category')->latest('updated_at')->latest('id')->limit(5))
            ->paginated(false)
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->state(fn (Article $record): ?string => $record->cover(draft: true)?->file->url(400))
                    ->height(40)
                    ->width(60),
                TextColumn::make('title')
                    ->label('Article')
                    ->state(fn (Article $record): string => $record->draftFields()['title'] ?? $record->title)
                    ->weight('medium')
                    ->wrap()
                    ->description(fn (Article $record): ?string => $record->category?->name)
                    ->url(fn (Article $record): string => ArticleResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('status')->badge(),
                TextColumn::make('published_at')
                    ->label('Publish date')
                    ->state(fn (Article $record) => $record->status === ArticleStatus::Scheduled ? $record->scheduled_at : $record->published_at)
                    ->dateTime('d M Y')
                    ->placeholder('—'),
            ])
            ->emptyStateHeading('Belum ada artikel');
    }
}
