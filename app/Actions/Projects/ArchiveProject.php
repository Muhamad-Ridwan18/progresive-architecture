<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;

/**
 * Arsipkan atau pulihkan proyek. Proyek Archived tidak tampil di frontend.
 * Detail pengelolaan Archived masih terbuka di SRS Bagian 15 butir 5; ini asumsi sementara.
 */
class ArchiveProject
{
    public function archive(Project $project): Project
    {
        $project->forceFill(['status' => ProjectStatus::Archived])->save();

        activity('project')->performedOn($project)->event('archived')->log('Project archived');

        return $project;
    }

    /** Dikembalikan ke status sesuai keadaannya: Draft bila belum pernah dipublikasikan. */
    public function restore(Project $project): Project
    {
        $project->forceFill([
            'status' => $project->published_at ? ProjectStatus::Published : ProjectStatus::Draft,
        ])->save();

        $project->syncStatus();

        activity('project')->performedOn($project)->event('restored')->log('Project restored from archive');

        return $project->refresh();
    }
}
