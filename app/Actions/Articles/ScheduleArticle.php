<?php

namespace App\Actions\Articles;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\MediaUsage;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Jadwalkan penerbitan (SRS FR-CM-031). Hanya artikel yang belum pernah terbit (Draft) yang dapat
 * dijadwalkan; artikel yang sudah live memakai alur Publish biasa. Eksekusinya oleh
 * `articles:publish-scheduled` (FR-WF-012).
 */
class ScheduleArticle
{
    use ValidatesArticleFields;

    /** @throws ValidationException */
    public function schedule(Article $article, Carbon $at, ?int $userId = null): Article
    {
        if ($article->status !== ArticleStatus::Draft) {
            throw ValidationException::withMessages(['scheduled_at' => 'Hanya artikel berstatus Draft yang dapat dijadwalkan.']);
        }

        if ($at->isPast()) {
            throw ValidationException::withMessages(['scheduled_at' => 'Waktu jadwal harus di masa depan.']);
        }

        // Validasi penuh sekarang, supaya jadwal tidak gagal diam-diam saat waktunya tiba.
        $this->validateFields($article->draftFields(), $article, checkDraftSlugs: false);

        if ($article->usages(draft: true)->contains(fn (MediaUsage $u): bool => ! $u->file->isReady())) {
            throw ValidationException::withMessages(['media' => 'Ada gambar yang belum siap. Tunggu hingga selesai atau hapus gambar yang gagal.']);
        }

        $article->forceFill(['status' => ArticleStatus::Scheduled, 'scheduled_at' => $at, 'updated_by' => $userId])->save();

        activity('article')
            ->performedOn($article)
            ->event('scheduled')
            ->withProperties(['attributes' => ['scheduled_at' => $at->toIso8601String()]])
            ->log('Article scheduled');

        return $article->refresh();
    }

    public function cancel(Article $article, ?int $userId = null): Article
    {
        if ($article->status === ArticleStatus::Scheduled) {
            $article->forceFill(['status' => ArticleStatus::Draft, 'scheduled_at' => null, 'updated_by' => $userId])->save();

            activity('article')->performedOn($article)->event('schedule_cancelled')->log('Article schedule cancelled');
        }

        return $article->refresh();
    }
}
