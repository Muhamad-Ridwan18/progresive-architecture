<?php

namespace App\Models;

use App\Enums\JobStatus;
use Database\Factories\JobVacancyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Lowongan pekerjaan (SRS 6.1). Nama tabel `job_vacancies` karena `jobs` dipakai antrean Laravel.
 */
#[Fillable([
    'title', 'slug', 'job_category_id', 'department', 'location', 'employment_type',
    'description', 'qualifications', 'required_documents', 'deadline', 'status', 'form_fields', 'published_at',
    'deadline_h3_notified_at', 'deadline_h0_notified_at',
])]
class JobVacancy extends Model
{
    /** @use HasFactory<JobVacancyFactory> */
    use HasFactory, LogsActivity;

    /** Pilihan jenis kerja; disimpan sebagai teks agar mudah ditambah. */
    public const EMPLOYMENT_TYPES = [
        'Full-time' => 'Full-time',
        'Part-time' => 'Part-time',
        'Kontrak' => 'Kontrak',
        'Magang' => 'Magang',
        'Freelance' => 'Freelance',
    ];

    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
            'deadline' => 'date',
            'form_fields' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $vacancy): void {
            $base = Str::slug(filled($vacancy->slug) ? $vacancy->slug : $vacancy->title) ?: 'lowongan';
            $candidate = $base;
            $i = 2;

            while (static::query()->where('slug', $candidate)->exists()) {
                $candidate = $base.'-'.$i++;
            }

            $vacancy->slug = $candidate;
        });

        // Menghapus lowongan akan ikut menghapus data pelamar (cascade). Lowongan yang sudah punya pelamar
        // hanya boleh ditutup, tidak dihapus.
        static::deleting(fn (self $vacancy): bool => ! $vacancy->applications()->exists());

        // Deadline diubah: pengingat H-3 dan hari-H berlaku lagi untuk tanggal yang baru. Lewat query langsung
        // (bukan atribut model) agar tetap bekerja walau instansi model yang dipakai sudah usang.
        static::saved(function (self $vacancy): void {
            if ($vacancy->wasChanged('deadline')) {
                $vacancy->newQuery()->whereKey($vacancy->getKey())->update([
                    'deadline_h3_notified_at' => null,
                    'deadline_h0_notified_at' => null,
                ]);
            }
        });

        static::saving(function (self $vacancy): void {
            if ($vacancy->status === JobStatus::Published && $vacancy->published_at === null) {
                $vacancy->published_at = now();
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class, 'job_category_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /** Lowongan yang tampil di halaman Careers: published dan closed (draft tidak pernah tampil). */
    public function scopeVisibleToPublic(Builder $query): Builder
    {
        return $query->whereIn('status', [JobStatus::Published->value, JobStatus::Closed->value]);
    }

    /** Dapat menerima lamaran: published dan belum melewati deadline (SRS FR-FE-086). */
    public function isOpen(): bool
    {
        if ($this->status !== JobStatus::Published) {
            return false;
        }

        return $this->deadline === null || ! $this->deadline->isBefore(today());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('job_vacancy')
            ->logOnly(['title', 'status', 'location', 'employment_type', 'deadline', 'job_category_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
