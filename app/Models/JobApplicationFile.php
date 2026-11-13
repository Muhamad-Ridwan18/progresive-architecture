<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Dokumen lamaran. Disimpan di disk `local` (privat) dan hanya diakses lewat rute admin. */
#[Fillable(['job_application_id', 'field_key', 'original_name', 'path', 'mime_type', 'size'])]
class JobApplicationFile extends Model
{
    protected static function booted(): void
    {
        static::deleted(fn (self $file) => Storage::disk('local')->delete($file->path));
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function humanSize(): string
    {
        $kb = $this->size / 1024;

        return $kb >= 1024 ? number_format($kb / 1024, 1, ',', '.').' MB' : number_format($kb, 0, ',', '.').' KB';
    }
}
