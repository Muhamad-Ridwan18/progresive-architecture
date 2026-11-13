<?php

namespace App\Models;

use App\Enums\PageStatus;
use App\Models\Concerns\HasDraftMedia;
use App\Support\AboutContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Halaman About Us (SRS 3.5 dan 5.4): satu baris. `draft` adalah hasil editan,
 * `published` adalah versi yang tampil di website. Publish menyalin draft ke published (SRS 11.2).
 */
class AboutPage extends Model
{
    use HasDraftMedia;

    protected $guarded = [];

    protected $attributes = [
        'draft_version' => 1,
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'draft' => 'array',
            'published' => 'array',
            'draft_version' => 'integer',
            'status' => PageStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /** Satu-satunya halaman About Us; dibuat bila belum ada. */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], ['draft' => AboutContent::empty()]);
    }

    /** @return array<string, mixed> */
    public function draftContent(): array
    {
        return AboutContent::normalize($this->draft ?? []);
    }

    /** Null bila belum pernah dipublikasikan. */
    public function publishedContent(): ?array
    {
        return $this->published === null ? null : AboutContent::normalize($this->published);
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->draftContent() !== $this->publishedContent()
            || $this->mediaSignature(true) !== $this->mediaSignature(false);
    }

    /** Peran gambar untuk foto satu anggota tim (kolom `role` maksimal 30 karakter; id maksimal 20). */
    public static function teamRole(string $memberId): string
    {
        return 'team:'.$memberId;
    }

    /**
     * Foto anggota tim yang siap tampil, dengan kunci id anggota. Versi published untuk website,
     * draft untuk Preview admin.
     *
     * @return Collection<string, MediaUsage>
     */
    public function teamPhotos(bool $draft): Collection
    {
        return $this->mediaUsages()
            ->where('is_draft', $draft)
            ->where('role', 'like', 'team:%')
            ->with('file')
            ->orderBy('id')
            ->get()
            ->filter(fn (MediaUsage $usage): bool => $usage->file->isReady())
            ->keyBy(fn (MediaUsage $usage): string => substr($usage->role, 5));
    }

    /**
     * Buang foto draft milik anggota yang sudah tidak ada di formulir. Lewat model agar file masuk
     * antrean hapus (SRS 12.9). `$keepIds` berasal dari state formulir mentah, termasuk anggota yang
     * belum diberi nama, supaya foto yang baru diunggah tidak terhapus sebelum nama diketik.
     *
     * @param  list<string>  $keepIds
     */
    public function pruneTeamPhotos(array $keepIds): void
    {
        $keep = array_map(self::teamRole(...), $keepIds);

        $this->mediaUsages()
            ->where('is_draft', true)
            ->where('role', 'like', 'team:%')
            ->whereNotIn('role', $keep)
            ->get()
            ->each->delete();
    }

    /** Status mengikuti keadaan nyata: kembali ke Published bila editan dikembalikan seperti semula. */
    public function syncStatus(): void
    {
        if (! $this->status->isLive()) {
            return;
        }

        $status = $this->hasUnpublishedChanges() ? PageStatus::UnpublishedChanges : PageStatus::Published;

        if ($status !== $this->status) {
            $this->forceFill(['status' => $status])->save();
        }
    }
}
