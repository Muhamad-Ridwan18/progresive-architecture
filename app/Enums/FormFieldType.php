<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Jenis field formulir lamaran (SRS FR-RC-020). */
enum FormFieldType: string implements HasLabel
{
    case Text = 'text';
    case Email = 'email';
    case Phone = 'phone';
    case Number = 'number';
    case Textarea = 'textarea';
    case Dropdown = 'dropdown';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Date = 'date';
    case File = 'file';
    case Section = 'section';
    case Repeater = 'repeater';

    public function getLabel(): string
    {
        return match ($this) {
            self::File => 'File Upload',
            default => ucfirst($this->value),
        };
    }

    /** Field yang menerima jawaban. Section hanya pemisah tampilan. */
    public function isInput(): bool
    {
        return $this !== self::Section;
    }

    public function hasOptions(): bool
    {
        return in_array($this, [self::Dropdown, self::Radio, self::Checkbox], true);
    }
}
