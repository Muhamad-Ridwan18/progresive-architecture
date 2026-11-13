<?php

namespace App\Models;

use App\Services\VacancyFormComposer;
use Database\Factories\JobCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Kategori pekerjaan sekaligus pemilik template formulir lamaran default (SRS FR-RC-010).
 */
#[Fillable(['name', 'slug', 'sort_order', 'default_field_keys', 'default_required_keys'])]
class JobCategory extends Model
{
    /** @use HasFactory<JobCategoryFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'default_field_keys' => 'array',
            'default_required_keys' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            $base = Str::slug(filled($category->slug) ? $category->slug : $category->name) ?: 'category';
            $candidate = $base;
            $i = 2;

            while (static::query()->where('slug', $candidate)->exists()) {
                $candidate = $base.'-'.$i++;
            }

            $category->slug = $candidate;

            if (! $category->sort_order) {
                $category->sort_order = (int) static::query()->max('sort_order') + 1;
            }

            // Kategori baru memakai isian standar: inti, CV (wajib), dan surat lamaran.
            if (blank($category->default_field_keys)) {
                $category->default_field_keys = VacancyFormComposer::DEFAULT_KEYS;
                $category->default_required_keys ??= ['cv'];
            }
        });

        // Kategori yang masih punya lowongan tidak boleh dihapus; foreign key database menjaga hal yang sama.
        static::deleting(fn (self $category): bool => ! $category->vacancies()->exists());
    }

    public function vacancies(): HasMany
    {
        return $this->hasMany(JobVacancy::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('job_category')
            ->logOnly(['name', 'slug', 'default_field_keys', 'default_required_keys'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
