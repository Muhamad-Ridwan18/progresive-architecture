<?php

namespace App\Actions\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Validation\ValidationException;

/** Buat proyek baru berstatus Draft. Hanya nama dan kategori yang wajib; sisanya diisi di editor. */
class CreateProject
{
    use ValidatesProjectFields;

    /**
     * @param  array{name:string, category_id:int|string}  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data, ?int $userId = null): Project
    {
        $fields = [
            'name' => $data['name'] ?? null,
            'slug' => $this->uniqueSlug((string) ($data['name'] ?? '')),
            'category_id' => $data['category_id'] ?? null,
            'location' => null,
            'year' => null,
            'description' => null,
        ];

        $this->validateFields($fields, null, checkDraftSlugs: true);

        $project = Project::create([
            ...$fields,
            'draft_data' => $fields,
            'status' => ProjectStatus::Draft,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        activity('project')
            ->performedOn($project)
            ->event('created')
            ->withProperties(['attributes' => ['name' => $project->name, 'slug' => $project->slug]])
            ->log('Project created');

        return $project->refresh();
    }
}
