<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Category;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['category', 'author']))
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->state(fn (Article $record): ?string => $record->cover(draft: true)?->file->url(400))
                    ->height(48)
                    ->width(72),
                // Admin melihat judul draft (yang sedang dikerjakan), bukan hanya versi published.
                TextColumn::make('title')
                    ->label('Title')
                    ->state(fn (Article $record): string => $record->draftFields()['title'] ?? $record->title)
                    ->searchable(query: fn ($query, string $search) => $query
                        ->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('draft_data->title', 'like', "%{$search}%")))
                    ->weight('medium')
                    ->wrap()
                    // Kategori satu sel dengan judul; filter Category tetap tersedia.
                    ->description(fn (Article $record): ?string => $record->category?->name),
                TextColumn::make('author.name')->label('Author')->placeholder('—'),
                TextColumn::make('published_at')
                    ->label('Publish Date')
                    ->state(fn (Article $record) => $record->status === ArticleStatus::Scheduled ? $record->scheduled_at : $record->published_at)
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('status')->badge(),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter()
                    // Bintang dapat diklik langsung di baris; hanya satu artikel featured (FR-FE-072).
                    ->action(function (Article $record): void {
                        if (! $record->status->isLive()) {
                            Notification::make()->title('Hanya artikel yang sudah tayang yang bisa featured')->warning()->send();

                            return;
                        }

                        $record->markFeatured(! $record->is_featured);
                    })
                    ->tooltip(fn (Article $record): string => $record->is_featured ? 'Remove featured' : 'Set featured'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(ArticleStatus::class),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn (): array => Category::query()->ofType(Article::categoryType())->orderBy('sort_order')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    // Hanya satu artikel featured; memilih yang lain otomatis mencabut yang sebelumnya (FR-FE-072).
                    Action::make('feature')
                        ->label('Set featured')
                        ->icon('heroicon-o-star')
                        ->color('gray')
                        ->visible(fn (Article $record): bool => $record->status->isLive() && ! $record->is_featured)
                        ->action(fn (Article $record) => $record->markFeatured(true)),
                    Action::make('unfeature')
                        ->label('Remove featured')
                        ->icon('heroicon-s-star')
                        ->color('warning')
                        ->visible(fn (Article $record): bool => $record->is_featured)
                        ->action(fn (Article $record) => $record->markFeatured(false)),
                    DeleteAction::make()
                        ->modalDescription('Artikel dan relasi gambarnya akan dihapus. Gambar masuk antrean penghapusan 7 hari.'),
                ]),
            ]);
    }
}
