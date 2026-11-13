<?php

namespace App\Actions\About;

use App\Actions\Projects\StaleDraftException;
use App\Models\AboutPage;
use App\Support\AboutContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Simpan hasil editan About Us sebagai draft (auto-save, SRS 11.1). Versi published tidak disentuh.
 * Nomor versi draft mencegah dua tab/administrator saling menimpa (SRS FR-WF-004).
 */
class SaveAboutDraft
{
    /**
     * @param  array<string, mixed>  $content
     *
     * @throws StaleDraftException
     * @throws ValidationException
     */
    public function handle(AboutPage $page, array $content, int $expectedVersion, ?int $userId = null, ?string $editorId = null): AboutPage
    {
        $memberIds = collect($content['team'] ?? [])
            ->filter(fn ($member) => is_array($member) && filled($member['id'] ?? null))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $content = AboutContent::validated($content);

        return DB::transaction(function () use ($page, $content, $memberIds, $expectedVersion, $userId, $editorId): AboutPage {
            $locked = AboutPage::query()->lockForUpdate()->findOrFail($page->getKey());

            // Versi usang ditolak, KECUALI penyimpanan terakhir dilakukan editor yang sama: permintaan
            // simpan dari satu tab bisa tumpang tindih dan yang kedua membawa versi lama. Isinya tetap
            // lebih baru (kumulatif), jadi aman diterima. Tab lain / admin lain tetap ditolak.
            $sameEditor = $editorId !== null && $locked->draft_editor === $editorId;

            if ($locked->draft_version !== $expectedVersion && ! $sameEditor) {
                throw new StaleDraftException;
            }

            $locked->forceFill([
                'draft' => $content,
                'draft_version' => $locked->draft_version + 1,
                'draft_editor' => $editorId,
                'updated_by' => $userId,
            ])->save();

            $locked->pruneTeamPhotos($memberIds);
            $locked->syncStatus();

            return $locked->refresh();
        });
    }
}
