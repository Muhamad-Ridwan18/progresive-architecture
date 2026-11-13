<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Tahapan lamaran (SRS FR-RC-032). Perubahan status dilakukan manual oleh administrator. */
enum ApplicationStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case Reviewing = 'reviewing';
    case Shortlisted = 'shortlisted';
    case Interview = 'interview';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Reviewing => 'Reviewing',
            self::Shortlisted => 'Shortlisted',
            self::Interview => 'Interview',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'gray',
            self::Reviewing => 'info',
            self::Shortlisted => 'warning',
            self::Interview => 'primary',
            self::Accepted => 'success',
            self::Rejected => 'danger',
        };
    }
}
