<?php

namespace App\Actions\Projects;

use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Simpan hasil editan sebagai draft (auto-save, SRS 11.1). Versi published tidak disentuh.
 *
 * Pengaman tabrakan (SRS FR-WF-004): editor membawa nomor versi draft yang dimuatnya.
 * Bila sudah berubah, simpanan ditolak sehingga permintaan lama tidak menimpa yang lebih baru.
 */
class SaveProjectDraft
{
    use ValidatesProjectFields;

    /**
     * @param  array<string, mixed>  $fields
     *
     * @throws StaleDraftException
     * @throws ValidationException
     */
    public function handle(Project $project, array $fields, int $expectedVersion, ?int $userId = null, ?string $editorId = null): Project
    {
        $fields = collect($fields)->only(Project::FIELDS)->all();

        return DB::transaction(function () use ($project, $fields, $expectedVersion, $userId, $editorId): Project {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->getKey());

            // Versi usang ditolak, KECUALI penyimpanan terakhir dilakukan editor yang sama: permintaan
            // simpan dari satu tab bisa tumpang tindih dan yang kedua membawa versi lama. Isinya tetap
            // lebih baru (kumulatif), jadi aman diterima. Tab lain / admin lain tetap ditolak.
            $sameEditor = $editorId !== null && $locked->draft_editor === $editorId;

            if ($locked->draft_version !== $expectedVersion && ! $sameEditor) {
                throw new StaleDraftException;
            }

            // Field yang tidak dikirim tetap memakai nilai draft saat ini.
            $merged = array_merge($locked->draftFields(), $fields);
            $this->validateFields($merged, $locked, checkDraftSlugs: true);

            $locked->forceFill([
                'draft_data' => $merged,
                'draft_version' => $locked->draft_version + 1,
                'draft_editor' => $editorId,
                'updated_by' => $userId,
            ])->save();

            // Status mengikuti keadaan nyata: kembali ke Published bila editan dikembalikan seperti semula.
            $locked->syncStatus();

            return $locked->refresh();
        });
    }
}
