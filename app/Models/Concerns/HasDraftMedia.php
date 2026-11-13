<?php

namespace App\Models\Concerns;

use App\Models\MediaUsage;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;

/**
 * Gambar berversi draft/published untuk konten yang punya alur Publish (Project, Article; SRS 11.2).
 * Baris `media_usages` bertanda `is_draft` milik draft; baris lain milik versi published.
 */
trait HasDraftMedia
{
    public const ROLE_COVER = 'cover';

    public const ROLE_GALLERY = 'gallery';

    public function mediaUsages(): MorphMany
    {
        return $this->morphMany(MediaUsage::class, 'usable');
    }

    /** Cover versi published. Untuk halaman publik; eager-load dengan `publishedCoverUsage.file`. */
    public function publishedCoverUsage(): MorphOne
    {
        return $this->morphOne(MediaUsage::class, 'usable')
            ->where('is_draft', false)
            ->where('role', self::ROLE_COVER);
    }

    /** Galeri versi published, berurutan. Eager-load dengan `publishedGalleryUsages.file`. */
    public function publishedGalleryUsages(): MorphMany
    {
        return $this->morphMany(MediaUsage::class, 'usable')
            ->where('is_draft', false)
            ->where('role', self::ROLE_GALLERY)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** @return Collection<int, MediaUsage> */
    public function usages(bool $draft, ?string $role = null): Collection
    {
        return $this->mediaUsages()
            ->where('is_draft', $draft)
            ->when($role, fn ($q) => $q->where('role', $role))
            ->with('file')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function cover(bool $draft): ?MediaUsage
    {
        return $this->usages($draft, self::ROLE_COVER)->first();
    }

    /** Salin seluruh baris gambar dari satu versi ke versi lain (true = draft, false = published). */
    public function copyMedia(bool $fromDraft, bool $toDraft): void
    {
        $existing = $this->mediaUsages()->where('is_draft', $toDraft)->get();

        foreach ($this->usages($fromDraft) as $usage) {
            $this->mediaUsages()->create([
                'media_file_id' => $usage->media_file_id,
                'role' => $usage->role,
                'is_draft' => $toDraft,
                'sort_order' => $usage->sort_order,
                'alt_text' => $usage->alt_text,
                'caption' => $usage->caption,
                'focal' => $usage->focal,
            ]);
        }

        // Baris lama dihapus SETELAH salinan dibuat agar file tidak sempat dianggap tanpa referensi.
        $existing->each->delete();
    }

    protected function mediaSignature(bool $draft): string
    {
        return $this->usages($draft)
            ->sortBy([['role', 'asc'], ['sort_order', 'asc'], ['id', 'asc']])
            ->map(fn (MediaUsage $u): array => [
                $u->role, $u->media_file_id, $u->sort_order, $u->alt_text, $u->caption, $u->focal,
            ])
            ->values()
            ->toJson();
    }
}
