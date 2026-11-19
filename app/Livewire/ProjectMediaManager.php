<?php

namespace App\Livewire;

use App\Actions\Media\AttachMedia;
use App\Actions\Media\DetachMedia;
use App\Actions\Media\UploadMedia;
use App\Enums\MediaStatus;
use App\Jobs\ProcessMediaImage;
use App\Models\AboutPage;
use App\Models\Article;
use App\Models\MediaUsage;
use App\Models\Project;
use App\Models\User;
use App\Services\Media\InvalidMediaException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Pengelola gambar untuk satu proyek: satu instance untuk cover, satu untuk galeri (SRS 5.1, 12.2).
 * Semua perubahan mengenai draft; versi published baru berubah saat proyek dipublikasikan.
 */
class ProjectMediaManager extends Component
{
    use WithFileUploads;

    /** Id pemilik gambar: proyek, atau artikel bila `kind` = article. Namanya dipertahankan agar komponen yang ada tetap sama. */
    public int $projectId;

    /** project | article. Dikunci agar tidak dapat diganti dari browser. */
    #[Locked]
    public string $kind = 'project';

    public string $role = Project::ROLE_GALLERY;

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    /** @var list<string> */
    public array $uploadErrors = [];

    public function mount(int $projectId, string $role, string $kind = 'project'): void
    {
        $this->projectId = $projectId;
        $this->kind = in_array($kind, ['article', 'about'], true) ? $kind : 'project';

        // Foto anggota tim About Us memakai peran `team:{id}`; id dibatasi agar cocok dengan kolom role.
        $isTeamRole = $this->kind === 'about' && preg_match('/^team:[a-z0-9]{6,20}$/', $role) === 1;

        $this->role = $isTeamRole || in_array($role, [Project::ROLE_COVER, Project::ROLE_GALLERY], true) ? $role : Project::ROLE_GALLERY;
    }

    /** Satu gambar saja (menggantikan yang lama) dengan titik fokus: cover dan foto anggota tim. */
    public function isCover(): bool
    {
        return $this->role !== Project::ROLE_GALLERY;
    }

    /** Galeri proyek (bukan artikel, About, atau cover): satu-satunya yang mendukung grup foto dan teks deskripsi. */
    public function isProjectGallery(): bool
    {
        return $this->kind === 'project' && $this->role === Project::ROLE_GALLERY;
    }

    public function isTeamPhoto(): bool
    {
        return str_starts_with($this->role, 'team:');
    }

    /** Dipicu halaman editor setelah draft dikembalikan ke versi published. */
    #[On('project-media-reload')]
    public function reload(): void
    {
        unset($this->items);
    }

    // ------------------------------------------------------------------ unggah

    public function updatedUploads(): void
    {
        $this->authorizeAdmin();

        $project = $this->project();
        $files = array_values($this->uploads);
        $this->uploads = [];
        $this->uploadErrors = [];

        $max = $this->isCover() ? 1 : (int) config('media.max_files_per_batch');

        if (count($files) > $max) {
            $this->uploadErrors[] = "Maksimal {$max} gambar sekali unggah. Sisanya diabaikan.";
            $files = array_slice($files, 0, $max);
        }

        foreach ($files as $upload) {
            $name = $upload->getClientOriginalName();

            try {
                $file = app(UploadMedia::class)->handle($upload->getRealPath(), $name, auth()->id());

                if (! $this->isCover() && $this->project()->usages(true, $this->role)->contains('media_file_id', $file->id)) {
                    $this->uploadErrors[] = "{$name}: gambar ini sudah ada di galeri.";

                    continue;
                }

                if ($this->isCover()) {
                    $this->project()->usages(true, $this->role)->each(fn (MediaUsage $u) => app(DetachMedia::class)->handle($u));
                }

                app(AttachMedia::class)->handle($file, $project, $this->role, [
                    'sort_order' => (int) $this->project()->usages(true, $this->role)->max('sort_order') + 1,
                ]);
            } catch (InvalidMediaException $e) {
                $this->uploadErrors[] = "{$name}: {$e->getMessage()}";
            } finally {
                $upload->delete();
            }
        }

        $this->changed();
    }

    // ------------------------------------------------------------------ aksi per gambar

    public function remove(int $usageId): void
    {
        $this->authorizeAdmin();

        app(DetachMedia::class)->handle($this->usage($usageId));

        $this->renumber();
        $this->changed();
    }

    /** @param 'up'|'down' $direction */
    public function move(int $usageId, string $direction): void
    {
        $this->authorizeAdmin();

        $ids = $this->project()->usages(true, $this->role)->pluck('id')->values()->all();
        $i = array_search($usageId, $ids, true);
        $j = $direction === 'up' ? $i - 1 : $i + 1;

        if ($i === false || ! isset($ids[$j])) {
            return;
        }

        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];

        foreach ($ids as $order => $id) {
            $this->usage($id)->update(['sort_order' => $order]);
        }

        $this->changed();
    }

    /**
     * Gabungkan foto dengan foto di atasnya (satu grup, satu deskripsi), atau pisahkan lagi. Foto pertama galeri
     * selalu memulai grup, jadi tidak dapat digabung.
     */
    public function setJoin(int $usageId, bool $join): void
    {
        $this->authorizeAdmin();

        abort_unless($this->isProjectGallery(), 403);

        $first = $this->project()->usages(true, $this->role)->first();

        $this->usage($usageId)->update(['join_previous' => $join && $first?->id !== $usageId]);

        $this->changed();
    }

    /** Disimpan otomatis saat mengetik (debounce di browser); tidak perlu merender ulang agar ketikan tidak tertimpa. */
    #[Renderless]
    public function updateMeta(int $usageId, string $field, ?string $value): void
    {
        $this->authorizeAdmin();

        if (! in_array($field, ['alt_text', 'caption', 'note', 'note_by'], true)) {
            return;
        }

        $limit = ['alt_text' => 255, 'caption' => 1000, 'note' => 3000, 'note_by' => 255][$field];
        $value = $value === null ? null : mb_substr(trim($value), 0, $limit);

        $this->usage($usageId)->update([$field => $value === '' ? null : $value]);

        $this->changed();
    }

    /** Titik fokus cover (persen dari kiri/atas). Dipakai frontend lewat object-position (SRS 12.5). */
    public function setFocal(int $usageId, float $x, float $y): void
    {
        $this->authorizeAdmin();

        $clamp = fn (float $v): float => round(max(0, min(100, $v)), 1);

        $this->usage($usageId)->update(['focal' => ['x' => $clamp($x), 'y' => $clamp($y)]]);

        $this->changed();
    }

    public function retry(int $usageId): void
    {
        $this->authorizeAdmin();

        $file = $this->usage($usageId)->file;

        if ($file->status === MediaStatus::Failed) {
            $file->forceFill(['status' => MediaStatus::Pending, 'error' => null])->save();
            ProcessMediaImage::dispatch($file->id)->onQueue(config('media.queue'));
        }
    }

    // ------------------------------------------------------------------ tampilan

    /** @return Collection<int, MediaUsage> */
    #[Computed]
    public function items(): Collection
    {
        return $this->project()->usages(true, $this->role);
    }

    public function render()
    {
        $hasPending = $this->items->contains(
            fn (MediaUsage $u): bool => in_array($u->file->status, [MediaStatus::Pending, MediaStatus::Processing], true)
        );

        return view('livewire.project-media-manager', ['hasPending' => $hasPending]);
    }

    // ------------------------------------------------------------------ internal

    private function project(): Project|Article|AboutPage
    {
        return match ($this->kind) {
            'article' => Article::query()->findOrFail($this->projectId),
            'about' => AboutPage::query()->findOrFail($this->projectId),
            default => Project::query()->findOrFail($this->projectId),
        };
    }

    /** Selalu dicari dalam lingkup proyek, draft, dan peran ini, sehingga ID milik proyek lain ditolak. */
    private function usage(int $id): MediaUsage
    {
        return $this->project()->mediaUsages()
            ->where('is_draft', true)
            ->where('role', $this->role)
            ->with('file')
            ->findOrFail($id);
    }

    private function renumber(): void
    {
        $this->project()->usages(true, $this->role)->values()->each(
            fn (MediaUsage $u, int $order) => $u->update(['sort_order' => $order])
        );
    }

    private function changed(): void
    {
        unset($this->items);

        $this->project()->syncStatus();
        $this->dispatch('project-media-changed');
    }

    /** Endpoint Livewire bersifat umum, jadi setiap aksi memastikan pemanggilnya administrator aktif. */
    private function authorizeAdmin(): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $user->is_active, 403);
    }
}
