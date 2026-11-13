<?php

namespace App\Services;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Filament\Resources\JobVacancies\JobVacancyResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Article;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Notifikasi internal dashboard (SRS Bagian 10): dikirim ke seluruh administrator aktif, tersimpan di
 * database sehingga riwayatnya tetap ada setelah logout, dan tampil pada ikon lonceng di topbar.
 *
 * Kegagalan mengirim notifikasi tidak boleh menggagalkan pekerjaan utamanya (publish, lamaran masuk),
 * jadi setiap pengiriman dibungkus dan hanya dilaporkan.
 */
class AdminNotifier
{
    /** Lamaran baru masuk dari website (FR-NT-002). */
    public function newApplication(JobApplication $application): void
    {
        $application->loadMissing('vacancy');

        $this->send(
            title: 'New application',
            body: "{$application->applicant_name} melamar posisi {$application->vacancy->title}.",
            icon: 'heroicon-o-user-plus',
            color: 'info',
            url: JobApplicationResource::getUrl('view', ['record' => $application]),
        );
    }

    /** Proyek berhasil dipublikasikan, termasuk pembaruan atas proyek yang sudah terbit. */
    public function projectPublished(Project $project, bool $isUpdate): void
    {
        $this->send(
            title: $isUpdate ? 'Project updated' : 'Project published',
            body: "{$project->name} ".($isUpdate ? 'diperbarui di website.' : 'kini tampil di website.'),
            icon: 'heroicon-o-building-office-2',
            color: 'success',
            url: ProjectResource::getUrl('edit', ['record' => $project]),
        );
    }

    /** Artikel berhasil dipublikasikan, termasuk oleh jadwal (FR-WF-012). */
    public function articlePublished(Article $article, bool $isUpdate): void
    {
        $this->send(
            title: $isUpdate ? 'Article updated' : 'Article published',
            body: "{$article->title} ".($isUpdate ? 'diperbarui di website.' : 'kini tampil di website.'),
            icon: 'heroicon-o-newspaper',
            color: 'success',
            url: ArticleResource::getUrl('edit', ['record' => $article]),
        );
    }

    /** Pengingat batas waktu lowongan (FR-NT-006): `$daysLeft` 0 = hari-H. */
    public function jobDeadline(JobVacancy $vacancy, int $daysLeft): void
    {
        $when = $daysLeft === 0 ? 'berakhir hari ini' : "berakhir {$daysLeft} hari lagi";

        $this->send(
            title: 'Job deadline',
            body: "Lowongan {$vacancy->title} {$when} (".$vacancy->deadline->translatedFormat('d M Y').').',
            icon: 'heroicon-o-clock',
            color: $daysLeft === 0 ? 'danger' : 'warning',
            url: JobVacancyResource::getUrl('edit', ['record' => $vacancy]),
        );
    }

    /** @return Collection<int, User> Seluruh administrator aktif (SRS FR-NT-003). */
    public function recipients(): Collection
    {
        return User::query()->where('is_active', true)->get();
    }

    private function send(string $title, string $body, string $icon, string $color, string $url): void
    {
        try {
            $recipients = $this->recipients();

            if ($recipients->isEmpty()) {
                return;
            }

            $notification = Notification::make()
                ->title($title)
                ->body($body)
                ->icon($icon)
                ->iconColor($color)
                // Klik membuka halaman terkait dan menandai notifikasi sudah dibaca (FR-NT-004).
                ->actions([Action::make('open')->label('Open')->url($url)->markAsRead()]);

            // Langsung disimpan (bukan antrean): notifikasi Filament bawaan masuk antrean dan baru muncul
            // setelah worker berjalan, padahal lonceng harus andal tanpa worker.
            NotificationFacade::sendNow($recipients, $notification->toDatabase());
        } catch (Throwable $e) {
            report($e);
        }
    }
}
