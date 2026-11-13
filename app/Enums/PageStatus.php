<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Status halaman bersifat dokumen tunggal seperti About Us (SRS 11.2, matriks status). */
enum PageStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case UnpublishedChanges = 'unpublished_changes';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::UnpublishedChanges => 'Unpublished Changes',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::UnpublishedChanges => 'warning',
        };
    }

    public function isLive(): bool
    {
        return $this !== self::Draft;
    }
}
