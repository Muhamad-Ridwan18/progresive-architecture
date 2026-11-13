<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Category;
use App\Models\MediaUsage;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Forms\Components\RichEditor\RichContentRenderer;

/**
 * Data artikel seperti yang dilihat pengunjung. Dibangun dari versi published (website) atau dari
 * draft (Preview admin), sehingga template yang sama dipakai untuk keduanya. Pola sama dengan PublicProject.
 */
final class PublicArticle
{
    private function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly ?Category $category,
        public readonly ?string $excerpt,
        public readonly ?string $content,
        public readonly ?string $authorName,
        public readonly ?MediaUsage $cover,
        public readonly ?CarbonInterface $publishedAt,
        public readonly bool $isDraft,
    ) {}

    /** Versi published. Relasi `category`, `author`, dan `publishedCoverUsage.file` sebaiknya sudah dimuat. */
    public static function published(Article $article): self
    {
        return new self(
            id: $article->id,
            title: $article->title,
            slug: $article->slug,
            category: $article->category,
            excerpt: $article->excerpt,
            content: $article->content,
            authorName: $article->author?->name,
            cover: self::ready($article->publishedCoverUsage),
            publishedAt: $article->published_at,
            isDraft: false,
        );
    }

    /** Versi draft, untuk Preview admin. */
    public static function draft(Article $article): self
    {
        $d = $article->draftFields();

        return new self(
            id: $article->id,
            title: $d['title'] ?? $article->title,
            slug: $d['slug'] ?? $article->slug,
            category: Category::query()->find($d['category_id'] ?? null),
            excerpt: $d['excerpt'],
            content: $d['content'],
            authorName: User::query()->find($d['author_id'] ?? null)?->name,
            cover: self::ready($article->cover(draft: true)),
            publishedAt: $article->published_at ?? $article->scheduled_at ?? now(),
            isDraft: true,
        );
    }

    public function url(): string
    {
        return route('news.show', $this->slug);
    }

    /**
     * Isi artikel sebagai HTML yang sudah disanitasi. Teks berasal dari editor admin, tetapi tetap
     * dibersihkan (skrip dan atribut berbahaya dibuang) sebelum ditampilkan.
     */
    public function bodyHtml(): string
    {
        return filled($this->content)
            ? RichContentRenderer::make($this->content)->fileAttachmentsDisk('public')->toHtml()
            : '';
    }

    /** Gambar yang belum selesai diproses tidak ditampilkan; halaman tetap utuh tanpanya. */
    private static function ready(?MediaUsage $usage): ?MediaUsage
    {
        return $usage?->file->isReady() ? $usage : null;
    }
}
