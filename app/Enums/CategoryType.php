<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CategoryType: string implements HasLabel
{
    case Project = 'project';
    case Article = 'article';

    public function getLabel(): string
    {
        return match ($this) {
            self::Project => 'Project Categories',
            self::Article => 'Article Categories',
        };
    }

    /** Tabel konten yang memakai kategori jenis ini (kolom `category_id`). */
    public function contentTable(): string
    {
        return match ($this) {
            self::Project => 'projects',
            self::Article => 'articles',
        };
    }
}
