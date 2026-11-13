<?php

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Enums\CategoryType;
use App\Models\Concerns\HasDraftMedia;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Artikel news (SRS 5.2).
 *
 * Kolom utama = versi PUBLISHED; hasil editan ada di `draft_data` dan baris `media_usages` bertanda
 * `is_draft` (SRS 11.2). Pola yang sama dengan Project.
 */
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasDraftMedia, HasFactory;

    /** Field konten yang bisa diedit. Dipakai bersama oleh draft dan versi published. */
    public const FIELDS = ['title', 'slug', 'category_id', 'excerpt', 'content', 'author_id'];

    protected $guarded = [];

    protected $attributes = [
        'draft_version' => 1,
        'status' => 'draft',
        'is_featured' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'draft_data' => 'array',
            'draft_version' => 'integer',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $article): void {
            $article->mediaUsages()->get()->each->delete();
        });

        static::deleted(function (self $article): void {
            activity('article')
                ->event('deleted')
                ->withProperties(['old' => ['title' => $article->title, 'slug' => $article->slug]])
                ->log('Article deleted');
        });
    }

    // ------------------------------------------------------------------ relasi

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Artikel yang versi published-nya tampil di website. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ArticleStatus::Published->value,
            ArticleStatus::UnpublishedChanges->value,
        ]);
    }

    /** Terbaru dipublikasikan dulu. Dipakai bersama oleh listing dan Related Articles. */
    public function scopeInSiteOrder(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    /** Artikel yang sudah waktunya terbit (SRS FR-WF-012). */
    public function scopeDueForPublishing(Builder $query): Builder
    {
        return $query
            ->where('status', ArticleStatus::Scheduled->value)
            ->where('scheduled_at', '<=', now());
    }

    // ------------------------------------------------------------------ draft vs published

    /** @return array<string, mixed> */
    public function draftFields(): array
    {
        return $this->normalize(array_merge($this->publishedFields(), $this->draft_data ?? []));
    }

    /** @return array<string, mixed> */
    public function publishedFields(): array
    {
        return $this->normalize($this->only(self::FIELDS));
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->draftFields() !== $this->publishedFields()
            || $this->mediaSignature(true) !== $this->mediaSignature(false);
    }

    /**
     * Field teks yang isi draft-nya berbeda dari versi yang sedang tampil di website. Kosong untuk artikel yang
     * belum live (Draft/Scheduled): semuanya baru, bukan berubah.
     *
     * @return list<string>
     */
    public function changedFieldKeys(): array
    {
        if (! $this->status->isLive()) {
            return [];
        }

        $draft = $this->draftFields();
        $published = $this->publishedFields();

        return array_values(array_filter(self::FIELDS, fn (string $key): bool => $draft[$key] !== $published[$key]));
    }

    public function hasUnpublishedMediaChanges(): bool
    {
        return $this->status->isLive() && $this->mediaSignature(true) !== $this->mediaSignature(false);
    }

    /**
     * Sinkronkan Published <-> Unpublished Changes dengan keadaan sebenarnya.
     * Draft dan Scheduled belum punya versi published, jadi tidak diubah.
     */
    public function syncStatus(): void
    {
        if (! $this->status->isLive()) {
            return;
        }

        $status = $this->hasUnpublishedChanges() ? ArticleStatus::UnpublishedChanges : ArticleStatus::Published;

        if ($status !== $this->status) {
            $this->forceFill(['status' => $status])->save();
        }
    }

    /**
     * Jadikan artikel ini featured dan cabut artikel lain (SRS FR-FE-072). Hanya satu pada satu waktu.
     */
    public function markFeatured(bool $featured): void
    {
        DB::transaction(function () use ($featured): void {
            if ($featured) {
                static::query()->whereKeyNot($this->getKey())->where('is_featured', true)->update(['is_featured' => false]);
            }

            $this->forceFill(['is_featured' => $featured])->save();
        });
    }

    public static function categoryType(): CategoryType
    {
        return CategoryType::Article;
    }

    // ------------------------------------------------------------------ internal

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function normalize(array $fields): array
    {
        $out = [];

        foreach (self::FIELDS as $key) {
            $value = $fields[$key] ?? null;

            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }

            if (in_array($key, ['category_id', 'author_id'], true) && $value !== null) {
                $value = (int) $value;
            }

            $out[$key] = $value;
        }

        return $out;
    }
}
