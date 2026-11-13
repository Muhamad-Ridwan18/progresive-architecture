<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status proyek (SRS 11.2, matriks status). `Scheduled` belum dipakai untuk proyek:
 * keputusan ada di SRS Bagian 15 butir 9.
 */
enum ProjectStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case UnpublishedChanges = 'unpublished_changes';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::UnpublishedChanges => 'Unpublished Changes',
            self::Archived => 'Archived',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::UnpublishedChanges => 'warning',
            self::Archived => 'gray',
        };
    }

    /** Apakah versi published proyek ini tampil di frontend publik. */
    public function isLive(): bool
    {
        return $this === self::Published || $this === self::UnpublishedChanges;
    }
}
