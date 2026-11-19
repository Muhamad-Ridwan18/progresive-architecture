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
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?int $year,
        public readonly ?string $description,
        public readonly array $facts,
        public readonly array $credits,
        public readonly ?MediaUsage $cover,
        public readonly Collection $gallery,
        public readonly bool $isDraft,
    ) {}

    /**
     * Galeri dikelompokkan: foto yang digabung dengan foto di atasnya masuk ke grup yang sama dan berbagi satu
     * deskripsi (milik foto pertama grup). Foto pertama galeri selalu memulai grup.
     *
     * `caption` adalah teks grup yang tampil di bawah foto-fotonya: isian grup (`note`), atau, bila kosong, caption
     * per foto yang sudah ada sebelumnya (digabung) agar data lama tidak hilang.
     *
     * @return list<array{photos: Collection<int, MediaUsage>, note: ?string, note_by: ?string, caption: ?string}>
     */
    public function galleryGroups(): array
    {
        $groups = [];

        foreach ($this->gallery as $index => $usage) {
            if ($index === 0 || ! $usage->join_previous) {
                $groups[] = [
                    'photos' => collect([$usage]),
                    'note' => filled($usage->note) ? $usage->note : null,
                    'note_by' => filled($usage->note_by) ? $usage->note_by : null,
                ];

                continue;
            }

            $groups[array_key_last($groups)]['photos']->push($usage);
        }

        foreach ($groups as &$group) {
            $legacy = $group['photos']->pluck('caption')->filter(fn ($c) => filled($c))->implode(' · ');
            $group['caption'] = $group['note'] ?? ($legacy !== '' ? $legacy : null);
        }

        unset($group);

        return self::mergePlainPhotos($groups);
    }

    /**
     * Foto lepas tanpa deskripsi yang berurutan digabung otomatis menjadi satu grup supaya tersusun rapi dalam baris
     * (tanpa admin perlu menggabungkannya satu per satu). Grup yang digabung admin atau yang punya deskripsi tidak diubah.
     *
     * @param  list<array{photos: Collection<int, MediaUsage>, note: ?string, note_by: ?string, caption: ?string}>  $groups
     * @return list<array{photos: Collection<int, MediaUsage>, note: ?string, note_by: ?string, caption: ?string}>
     */
    private static function mergePlainPhotos(array $groups): array
    {
        $out = [];

        foreach ($groups as $group) {
            $plain = $group['photos']->count() === 1 && $group['caption'] === null;
            $last = array_key_last($out);

            if ($plain && $last !== null && ($out[$last]['auto'] ?? false)) {
                $out[$last]['photos']->push($group['photos']->first());

                continue;
            }

            $out[] = $group + ['auto' => $plain];
        }

        return array_map(fn (array $g): array => ['photos' => $g['photos'], 'note' => $g['note'], 'note_by' => $g['note_by'], 'caption' => $g['caption']], $out);
    }

    /**
     * Susun foto sebuah grup menjadi baris simetris: tiap baris berisi 1 sampai 3 foto berkolom sama lebar. Baris berisi
     * 3 foto; sisa 1 foto tidak dibiarkan sendirian (4 = 2 + 2, 7 = 3 + 2 + 2) kecuali memang hanya ada satu foto.
     *
     * @param  Collection<int, MediaUsage>  $photos
     * @return list<Collection<int, MediaUsage>>
     */
    public static function photoRows(Collection $photos): array
    {
        $sizes = [];
        $left = $photos->count();

        while ($left > 0) {
            $take = match (true) {
                $left === 4 => 2,
                default => min(3, $left),
            };

            $sizes[] = $take;
            $left -= $take;
        }

        $rows = [];
        $offset = 0;

        foreach ($sizes as $size) {
            $rows[] = $photos->slice($offset, $size)->values();
            $offset += $size;
        }

        return $rows;
    }

    /**
     * Rasio tampilan seragam untuk seluruh foto dalam satu baris (dipotong rapi mengikuti titik fokus): 3:2, atau 4:5
     * bila baris berisi 2 foto atau lebih dan semuanya potret.
     *
     * @param  Collection<int, MediaUsage>  $row
     */
    public static function rowAspect(Collection $row): string
    {
        $allPortrait = $row->count() > 1 && $row->every(fn (MediaUsage $u): bool => $u->file->height > $u->file->width);

        return $allPortrait ? '4 / 5' : '3 / 2';
    }

    /** Peta hanya tampil bila kedua koordinat terisi. */
    public function hasMap(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** Alamat peta OpenStreetMap yang disematkan (dapat digeser dan diperbesar), berpenanda di titik proyek. */
    public function mapEmbedUrl(): ?string
    {
        if (! $this->hasMap()) {
            return null;
        }

        $dLon = 0.03;
        $dLat = 0.02;

        return 'https://www.openstreetmap.org/export/embed.html?'.http_build_query([
            'bbox' => implode(',', [round($this->longitude - $dLon, 6), round($this->latitude - $dLat, 6), round($this->longitude + $dLon, 6), round($this->latitude + $dLat, 6)]),
            'layer' => 'mapnik',
            'marker' => $this->latitude.','.$this->longitude,
        ]);
    }

    /** Tautan untuk membuka titik yang sama di aplikasi peta penuh. */
    public function mapLinkUrl(): ?string
    {
        return $this->hasMap() ? 'https://www.openstreetmap.org/?mlat='.$this->latitude.'&mlon='.$this->longitude.'#map=14/'.$this->latitude.'/'.$this->longitude : null;
    }

    /** Versi published. Relasi `category`, `publishedCoverUsage.file`, `publishedGalleryUsages.file` sebaiknya sudah dimuat. */
    public static function published(Project $project): self
    {
        return new self(
            id: $project->id,
            name: $project->name,
            slug: $project->slug,
            category: $project->category,
            location: $project->location,
            latitude: $project->latitude,
            longitude: $project->longitude,
            year: $project->year,
            description: $project->description,
            facts: self::facts($project->only(Project::FIELDS)),
            credits: self::creditGroups($project->credits),
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
            latitude: $d['latitude'],
            longitude: $d['longitude'],
            year: $d['year'],
            description: $d['description'],
            facts: self::facts($d),
            credits: self::creditGroups($d['credits'] ?? null),
            cover: self::ready($project->cover(draft: true)),
            gallery: $project->usages(draft: true, role: Project::ROLE_GALLERY)->filter(fn (MediaUsage $u) => $u->file->isReady())->values(),
            isDraft: true,
        );
    }

    /**
     * Kelompok kredit untuk tampilan: judul dan daftar nama (satu per baris).
     *
     * @return list<array{title: string, names: list<string>}>
     */
    private static function creditGroups(mixed $credits): array
    {
        return array_map(
            fn (array $group): array => ['title' => $group['title'], 'names' => preg_split('/\R/', $group['names']) ?: []],
            Project::normalizeCredits($credits) ?? [],
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
