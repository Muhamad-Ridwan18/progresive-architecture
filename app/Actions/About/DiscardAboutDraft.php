<?php

namespace App\Actions\About;

use App\Enums\PageStatus;
use App\Models\AboutPage;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Buang editan yang belum dipublikasikan dan kembali ke versi yang tampil di website. */
class DiscardAboutDraft
{
    public function handle(AboutPage $page, ?int $userId = null, ?string $editorId = null): AboutPage
    {
        if ($page->published === null) {
            throw new RuntimeException('Halaman belum pernah dipublikasikan, tidak ada versi untuk dikembalikan.');
        }

        return DB::transaction(function () use ($page, $userId, $editorId): AboutPage {
            $locked = AboutPage::query()->lockForUpdate()->findOrFail($page->getKey());

            $locked->forceFill([
                'draft' => $locked->publishedContent(),
                'draft_version' => $locked->draft_version + 1,
                'draft_editor' => $editorId,
                'status' => PageStatus::Published,
                'updated_by' => $userId,
            ])->save();

            $locked->copyMedia(fromDraft: false, toDraft: true);

            activity('about')->performedOn($locked)->event('draft_discarded')->log('About Us draft discarded');

            return $locked->refresh();
        });
    }
}
