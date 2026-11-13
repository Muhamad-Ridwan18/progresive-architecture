<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Buang editan yang belum dipublikasikan dan kembali ke versi published. */
class DiscardProjectDraft
{
    public function handle(Project $project, ?int $userId = null, ?string $editorId = null): Project
    {
        if ($project->published_at === null) {
            throw new RuntimeException('Proyek belum pernah dipublikasikan, tidak ada versi untuk dikembalikan.');
        }

        return DB::transaction(function () use ($project, $userId, $editorId): Project {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->getKey());

            $locked->forceFill([
                'draft_data' => $locked->publishedFields(),
                'draft_version' => $locked->draft_version + 1,
                'draft_editor' => $editorId,
                'updated_by' => $userId,
            ])->save();

            $locked->copyMedia(fromDraft: false, toDraft: true);

            if ($locked->status !== ProjectStatus::Archived) {
                $locked->forceFill(['status' => ProjectStatus::Published])->save();
            }

            activity('project')->performedOn($locked)->event('draft_discarded')->log('Project draft discarded');

            return $locked->refresh();
        });
    }
}
