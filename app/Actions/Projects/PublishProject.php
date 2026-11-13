<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\MediaUsage;
use App\Models\Project;
use App\Services\AdminNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publikasikan draft: versi published digantikan oleh draft (SRS 11.2 langkah 6).
 * Teks disalin ke kolom utama dan gambar draft disalin menjadi gambar published.
 */
class PublishProject
{
    use ValidatesProjectFields;

    /**
     * @throws ValidationException
     */
    public function handle(Project $project, ?int $userId = null, ?string $editorId = null): Project
    {
        $wasPublished = $project->published_at !== null;

        $published = DB::transaction(function () use ($project, $userId, $editorId): Project {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->getKey());
            $draft = $locked->draftFields();

            $this->validateFields($draft, $locked, checkDraftSlugs: false);
            $this->assertImagesReady($locked);

            $before = $locked->published_at ? $locked->publishedFields() : null;

            $locked->forceFill([
                ...$draft,
                'draft_data' => $draft,
                // Versi naik agar tab lain yang masih membawa versi lama ditolak saat menyimpan.
                'draft_version' => $locked->draft_version + 1,
                'draft_editor' => $editorId,
                'status' => ProjectStatus::Published,
                'published_at' => $locked->published_at ?? now(),
                'updated_by' => $userId,
            ])->save();

            $locked->copyMedia(fromDraft: true, toDraft: false);

            activity('project')
                ->performedOn($locked)
                ->event('published')
                ->withProperties(['old' => $before, 'attributes' => $draft])
                ->log('Project published');

            return $locked->refresh();
        });

        app(AdminNotifier::class)->projectPublished($published, $wasPublished);

        return $published;
    }

    /**
     * Gambar yang belum selesai diproses atau gagal tidak punya varian, sehingga akan tampil kosong
     * di website. Publikasi ditahan sampai semua gambar draft siap (atau gambar bermasalah dihapus).
     *
     * @throws ValidationException
     */
    private function assertImagesReady(Project $project): void
    {
        $notReady = $project->usages(draft: true)->filter(fn (MediaUsage $u): bool => ! $u->file->isReady());

        if ($notReady->isNotEmpty()) {
            throw ValidationException::withMessages([
                'media' => "{$notReady->count()} gambar belum siap (masih diproses atau gagal). "
                    .'Tunggu hingga selesai, atau hapus gambar yang gagal, lalu publish lagi.',
            ]);
        }
    }
}
