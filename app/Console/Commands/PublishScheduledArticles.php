<?php

namespace App\Console\Commands;

use App\Actions\Articles\PublishArticle;
use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Menerbitkan artikel terjadwal yang waktunya sudah tiba (SRS FR-WF-012). Dijadwalkan tiap menit
 * (routes/console.php). Artikel yang gagal divalidasi dikembalikan ke Draft agar tidak diulang terus.
 */
class PublishScheduledArticles extends Command
{
    protected $signature = 'articles:publish-scheduled';

    protected $description = 'Terbitkan artikel terjadwal yang sudah waktunya';

    public function handle(PublishArticle $publish): int
    {
        $published = 0;

        Article::query()->dueForPublishing()->orderBy('scheduled_at')->each(function (Article $article) use ($publish, &$published): void {
            try {
                // Waktu terbit = waktu jadwal, bukan waktu perintah ini berjalan, agar urutan listing sesuai rencana.
                $publish->handle($article, $article->updated_by, null, $article->scheduled_at);
                $published++;
            } catch (ValidationException $e) {
                $article->forceFill(['status' => ArticleStatus::Draft, 'scheduled_at' => null])->save();

                activity('article')
                    ->performedOn($article)
                    ->event('schedule_failed')
                    ->withProperties(['errors' => $e->errors()])
                    ->log('Scheduled publishing failed');

                $this->warn("Artikel #{$article->id} gagal diterbitkan: ".collect($e->errors())->flatten()->first());
            }
        });

        $this->info("{$published} artikel diterbitkan.");

        return self::SUCCESS;
    }
}
