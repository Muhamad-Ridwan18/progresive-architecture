<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status artikel (SRS matriks status 11.2). Archived untuk artikel masih terbuka (SRS Bagian 15 butir 10).
 */
enum ArticleStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case UnpublishedChanges = 'unpublished_changes';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::UnpublishedChanges => 'Unpublished Changes',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'info',
            self::Published => 'success',
            self::UnpublishedChanges => 'warning',
        };
    }

    /** Apakah versi published artikel ini tampil di frontend publik. */
    public function isLive(): bool
    {
        return $this === self::Published || $this === self::UnpublishedChanges;
    }
}
