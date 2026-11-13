<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\ArticleStatus;
use App\Enums\ProjectStatus;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Article;
use App\Models\JobApplication;
use App\Models\Project;

/**
 * Hal-hal yang menunggu tindakan admin hari ini. Dipakai kartu "Perlu perhatian" di dashboard dan kalimat
 * sapaan di bawah judulnya. Fokusnya konten (proyek dan artikel) karena itu pekerjaan harian; lamaran kerja
 * jarang masuk, jadi kartunya paling akhir.
 */
class DashboardAttention
{
    /** Batas "segera": artikel terjadwal dalam 7 hari. */
    public const SCHEDULED_WITHIN_DAYS = 7;

    /**
     * @return list<array{icon: string, color: string, count: int, label: string, url: string}>
     */
    public static function all(): array
    {
        return [
            [
                'icon' => 'heroicon-o-pencil-square',
                'color' => 'amber',
                'count' => Project::query()->where('status', ProjectStatus::UnpublishedChanges->value)->count(),
                'label' => 'proyek berubah, belum di-publish',
                'url' => ProjectResource::getUrl('index', ['tableFilters' => ['status' => ['value' => ProjectStatus::UnpublishedChanges->value]]]),
            ],
            [
                'icon' => 'heroicon-o-document-text',
                'color' => 'indigo',
                'count' => Article::query()->whereIn('status', [ArticleStatus::Draft->value, ArticleStatus::UnpublishedChanges->value])->count(),
                'label' => 'artikel draft atau berubah, belum tayang',
                'url' => ArticleResource::getUrl('index', ['activeTab' => ArticleStatus::Draft->value]),
            ],
            [
                'icon' => 'heroicon-o-calendar-days',
                'color' => 'blue',
                'count' => Article::query()
                    ->where('status', ArticleStatus::Scheduled->value)
                    ->whereBetween('scheduled_at', [now(), now()->addDays(self::SCHEDULED_WITHIN_DAYS)])
                    ->count(),
                'label' => 'artikel terjadwal minggu ini',
                'url' => ArticleResource::getUrl('index', ['activeTab' => ArticleStatus::Scheduled->value]),
            ],
            [
                'icon' => 'heroicon-o-users',
                'color' => 'slate',
                'count' => JobApplication::query()->whereIn('status', [ApplicationStatus::Submitted->value, ApplicationStatus::Reviewing->value])->count(),
                'label' => 'lamaran menunggu ditinjau',
                'url' => JobApplicationResource::getUrl('index', ['activeTab' => ApplicationStatus::Submitted->value]),
            ],
        ];
    }

    /** @return list<array{icon: string, color: string, count: int, label: string, url: string}> */
    public static function pending(): array
    {
        return array_values(array_filter(self::all(), fn (array $item): bool => $item['count'] > 0));
    }
}
