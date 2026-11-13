<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\JobApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lamaran kerja (SRS 6.3). Struktur formulir disimpan sebagai snapshot agar perubahan formulir
 * di kemudian hari tidak merusak lamaran yang sudah masuk (FR-RC-023).
 */
#[Fillable([
    'reference_number', 'job_vacancy_id', 'applicant_name', 'applicant_email', 'applicant_phone',
    'status', 'answers', 'form_snapshot', 'submitted_at',
])]
class JobApplication extends Model
{
    /** @use HasFactory<JobApplicationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'answers' => 'array',
            'form_snapshot' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Nomor referensi memakai id agar pasti unik: APP-2026-000142 (SRS FR-FE-085).
        static::created(function (self $application): void {
            $application->forceFill([
                'reference_number' => sprintf('APP-%s-%06d', $application->submitted_at->format('Y'), $application->getKey()),
            ])->saveQuietly();
        });
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class, 'job_vacancy_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(JobApplicationFile::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(JobApplicationNote::class)->latest();
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(JobApplicationStatusChange::class)->latest('id');
    }
}
