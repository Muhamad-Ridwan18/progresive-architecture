<?php

namespace App\Support;

use App\Models\Category;
use App\Models\MediaUsage;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Data proyek seperti yang dilihat pengunjung. Bisa dibangun dari versi published (website)
 * atau dari draft (Preview untuk admin), sehingga template yang sama dipakai untuk keduanya.
 */
final class PublicProject
{
    /**
     * @param  Collection<int, MediaUsage>  $gallery
     */
    private function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?Category $category,
        public readonly ?string $location,
        public readonly ?int $year,
        public readonly ?string $description,
        public readonly array $facts,
        public readonly ?MediaUsage $cover,
        public readonly Collection $gallery,
        public readonly bool $isDraft,
    ) {}

    /** Versi published. Relasi `category`, `publishedCoverUsage.file`, `publishedGalleryUsages.file` sebaiknya sudah dimuat. */
    public static function published(Project $project): self
    {
        return new self(
            id: $project->id,
            name: $project->name,
            slug: $project->slug,
            category: $project->category,
            location: $project->location,
            year: $project->year,
            description: $project->description,
            facts: self::facts($project->only(Project::FIELDS)),
            cover: self::ready($project->publishedCoverUsage),
            gallery: $project->publishedGalleryUsages->filter(fn (MediaUsage $u) => $u->file->isReady())->values(),
            isDraft: false,
        );
    }

    /** Versi draft, untuk Preview admin. */
    public static function draft(Project $project): self
    {
        $d = $project->draftFields();

        return new self(
            id: $project->id,
            name: $d['name'] ?? $project->name,
            slug: $d['slug'] ?? $project->slug,
            category: Category::query()->find($d['category_id'] ?? null),
            location: $d['location'],
            year: $d['year'],
            description: $d['description'],
            facts: self::facts($d),
            cover: self::ready($project->cover(draft: true)),
            gallery: $project->usages(draft: true, role: Project::ROLE_GALLERY)->filter(fn (MediaUsage $u) => $u->file->isReady())->values(),
            isDraft: true,
        );
    }

    /**
     * Data teknis untuk daftar di halaman detail (hanya yang terisi): klien, tipologi, luas, lantai, status.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, string>
     */
    private static function facts(array $fields): array
    {
        $area = fn (mixed $v): ?string => $v === null || $v === '' ? null : rtrim(rtrim(number_format((float) $v, 1, ',', '.'), '0'), ',').' m²';

        return array_filter([
            'Client' => filled($fields['client'] ?? null) ? (string) $fields['client'] : null,
            'Typology' => filled($fields['typology'] ?? null) ? (string) $fields['typology'] : null,
            'Land area' => $area($fields['land_area'] ?? null),
            'Building area' => $area($fields['building_area'] ?? null),
            'Storey' => filled($fields['storeys'] ?? null) ? ((int) $fields['storeys']).' '.((int) $fields['storeys'] === 1 ? 'floor' : 'floors') : null,
            'Status' => filled($fields['stage'] ?? null) ? (string) $fields['stage'] : null,
        ]);
    }

    public function url(): string
    {
        return route('projects.show', $this->slug);
    }

    /** Gambar yang belum selesai diproses tidak ditampilkan; halaman tetap utuh tanpanya. */
    private static function ready(?MediaUsage $usage): ?MediaUsage
    {
        return $usage?->file->isReady() ? $usage : null;
    }
}
