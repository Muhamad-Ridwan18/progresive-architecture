<?php

namespace App\Models;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Kategori untuk Projects dan Articles (SRS 5.3).
 * Satu tabel dengan kolom `type`; slug unik per jenis.
 */
#[Fillable(['type', 'name', 'slug', 'sort_order'])]
class Category extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            if (blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }

            $category->slug = static::uniqueSlug($category->type, $category->slug);

            if (! $category->sort_order) {
                $category->sort_order = (int) static::query()->where('type', $category->type)->max('sort_order') + 1;
            }
        });

        // SRS FR-CM-004 / FR-CM-042: kategori yang sedang dipakai tidak boleh dihapus.
        // Mengembalikan false membatalkan penghapusan.
        static::deleting(fn (self $category): bool => ! $category->isInUse());
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function scopeOfType(Builder $query, CategoryType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    /** Jumlah konten yang memakai kategori ini. Bernilai 0 selama tabel konten belum ada. */
    public function usageCount(): int
    {
        $table = $this->type->contentTable();

        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->where('category_id', $this->getKey())->count();
    }

    public function isInUse(): bool
    {
        return $this->usageCount() > 0;
    }

    private static function uniqueSlug(CategoryType $type, string $slug): string
    {
        $base = $slug !== '' ? $slug : 'category';
        $candidate = $base;
        $i = 2;

        while (static::query()->where('type', $type->value)->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }

    /** Urutan tidak dicatat agar reorder tidak membanjiri Activity Logs. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('category')
            ->logOnly(['type', 'name', 'slug'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
