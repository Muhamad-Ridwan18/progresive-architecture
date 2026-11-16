<?php

namespace App\Models;

use App\Enums\CategoryType;
use App\Enums\ProjectStatus;
use App\Models\Concerns\HasDraftMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proyek portfolio (SRS 5.1).
 *
 * Kolom utama = versi PUBLISHED. Hasil editan ada di `draft_data` dan baris `media_usages`
 * bertanda `is_draft` (SRS 11.2). Publish menyalin draft ke versi published.
 */
class Project extends Model
{
    use HasDraftMedia;

    /** Field konten yang bisa diedit. Dipakai bersama oleh draft dan versi published. */
    public const FIELDS = [
        'name', 'slug', 'category_id', 'location', 'latitude', 'longitude', 'year', 'description',
        'client', 'typology', 'land_area', 'building_area', 'storeys', 'stage', 'credits',
    ];

    protected $guarded = [];

    /** Sama dengan default kolom di database, agar model yang baru dibuat langsung konsisten. */
    protected $attributes = [
        'draft_version' => 1,
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'draft_data' => 'array',
            'draft_version' => 'integer',
            'published_at' => 'datetime',
            'year' => 'integer',
            'land_area' => 'float',
            'building_area' => 'float',
            'storeys' => 'integer',
            'credits' => 'array',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    protected static function booted(): void
    {
        // Lepas semua penggunaan gambar lewat model, agar file masuk antrean hapus (SRS 12.9).
        static::deleting(function (self $project): void {
            $project->mediaUsages()->get()->each->delete();
        });

        static::deleted(function (self $project): void {
            activity('project')
                ->event('deleted')
                ->withProperties(['old' => ['name' => $project->name, 'slug' => $project->slug]])
                ->log('Project deleted');
        });
    }

    // ------------------------------------------------------------------ relasi

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Proyek yang versi published-nya tampil di website (Published atau Unpublished Changes). */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ProjectStatus::Published->value,
            ProjectStatus::UnpublishedChanges->value,
        ]);
    }

    /** Urutan tampil di website: terbaru dulu. Dipakai bersama oleh listing dan Next/Previous. */
    public function scopeInSiteOrder(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    // ------------------------------------------------------------------ draft vs published

    /** Nilai field untuk draft: draft_data bila ada, jika tidak kolom utama. */
    public function draftFields(): array
    {
        return $this->normalize(array_merge($this->publishedFields(), $this->draft_data ?? []));
    }

    public function publishedFields(): array
    {
        return $this->normalize($this->only(self::FIELDS));
    }

    /**
     * Apakah draft berbeda dari versi published? Membandingkan field teks dan daftar gambar
     * (file, urutan, alt text, caption, focal point).
     */
    public function hasUnpublishedChanges(): bool
    {
        return $this->draftFields() !== $this->publishedFields()
            || $this->mediaSignature(true) !== $this->mediaSignature(false);
    }

    /**
     * Field teks yang isi draft-nya berbeda dari versi yang sedang tampil di website. Kosong untuk proyek yang
     * belum pernah dipublikasikan (semuanya "baru", bukan "berubah") dan yang diarsipkan.
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

        return array_values(array_filter(Project::FIELDS, fn (string $key): bool => $draft[$key] !== $published[$key]));
    }

    /** Apakah daftar gambar (cover/galeri) draft berbeda dari yang sedang tampil? Hanya untuk proyek yang live. */
    public function hasUnpublishedMediaChanges(): bool
    {
        return $this->status->isLive() && $this->mediaSignature(true) !== $this->mediaSignature(false);
    }

    /**
     * Sinkronkan status Published <-> Unpublished Changes dengan keadaan sebenarnya.
     * Draft yang belum pernah dipublikasikan dan Archived tidak diubah.
     */
    public function syncStatus(): void
    {
        if (! $this->status->isLive()) {
            return;
        }

        $status = $this->hasUnpublishedChanges() ? ProjectStatus::UnpublishedChanges : ProjectStatus::Published;

        if ($status !== $this->status) {
            $this->forceFill(['status' => $status])->save();
        }
    }

    /** Jenis kategori yang boleh dipakai proyek. */
    public static function categoryType(): CategoryType
    {
        return CategoryType::Project;
    }

    // ------------------------------------------------------------------ internal

    /**
     * Kredit dirapikan: judul dan nama dipangkas, baris kosong dibuang, kelompok tanpa nama dihapus. Kosong = null,
     * agar draft yang hanya berisi baris kosong tidak dianggap berbeda dari versi published.
     *
     * @return list<array{title: string, names: string}>|null
     */
    public static function normalizeCredits(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $groups = [];

        foreach ($value as $group) {
            if (! is_array($group)) {
                continue;
            }

            $names = collect(preg_split('/\R/', (string) ($group['names'] ?? '')) ?: [])
                ->map(fn (string $line): string => trim($line))
                ->filter()
                ->implode("\n");

            if ($names === '') {
                continue;
            }

            $groups[] = ['title' => trim((string) ($group['title'] ?? '')), 'names' => $names];
        }

        return $groups === [] ? null : $groups;
    }

    /** @param array<string, mixed> $fields */
    private function normalize(array $fields): array
    {
        $out = [];

        foreach (self::FIELDS as $key) {
            $value = $fields[$key] ?? null;

            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }

            if (in_array($key, ['year', 'category_id', 'storeys'], true) && $value !== null) {
                $value = (int) $value;
            }

            if (in_array($key, ['land_area', 'building_area'], true) && $value !== null) {
                $value = round((float) $value, 1);
            }

            if (in_array($key, ['latitude', 'longitude'], true) && $value !== null) {
                $value = round((float) $value, 6);
            }

            if ($key === 'credits') {
                $value = self::normalizeCredits($value);
            }

            $out[$key] = $value;
        }

        return $out;
    }
}
