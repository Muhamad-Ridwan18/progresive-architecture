<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Catatan internal administrator pada lamaran (SRS FR-RC-035). Tidak pernah terlihat pelamar. */
#[Fillable(['job_application_id', 'user_id', 'body'])]
class JobApplicationNote extends Model
{
    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
