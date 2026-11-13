<?php

namespace App\Models;

use App\Enums\FormFieldType;
use App\Support\ApplicationForm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Satu isian di pustaka formulir lamaran (SRS 6.2). Admin tidak menyusun formulir dari nol: ia mencentang
 * isian pustaka untuk setiap lowongan. Lowongan menyimpan SALINAN isian saat disimpan, jadi mengubah atau
 * menghapus isian pustaka tidak mengubah lowongan atau lamaran yang sudah ada.
 */
#[Fillable([
    'key', 'type', 'label', 'description', 'placeholder', 'options', 'columns', 'accept', 'max_mb',
    'required_default', 'is_core', 'sort_order',
])]
class FormField extends Model
{
    use LogsActivity;

    /** Isian inti yang selalu ada di setiap formulir: dari sinilah nama, email, dan telepon pelamar diambil. */
    public const CORE_KEYS = ['full_name', 'email', 'phone'];

    protected function casts(): array
    {
        return [
            'type' => FormFieldType::class,
            'accept' => 'array',
            'max_mb' => 'integer',
            'required_default' => 'boolean',
            'is_core' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $field): void {
            // Kunci dibuat dari label dan tidak berubah lagi: jawaban lama tetap terbaca walau label diganti.
            if (blank($field->key)) {
                $field->key = static::uniqueKey((string) $field->label);
            }

            if (! $field->sort_order) {
                $field->sort_order = (int) static::query()->max('sort_order') + 1;
            }
        });

        // Isian inti tidak boleh dihapus; mengembalikan false membatalkan penghapusan.
        static::deleting(fn (self $field): bool => ! $field->is_core);

        // Kunci yang dihapus dikeluarkan dari daftar bawaan kategori agar tidak menggantung.
        static::deleted(function (self $field): void {
            JobCategory::query()->get()->each(function (JobCategory $category) use ($field): void {
                $category->update([
                    'default_field_keys' => array_values(array_diff($category->default_field_keys ?? [], [$field->key])),
                    'default_required_keys' => array_values(array_diff($category->default_required_keys ?? [], [$field->key])),
                ]);
            });
        });
    }

    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Bentuk field untuk formulir lowongan (lihat ApplicationForm). `$required` ditentukan per lowongan. */
    public function toApplicationField(bool $required): array
    {
        $type = $this->type;

        return [
            'key' => $this->key,
            'type' => $type->value,
            'label' => $this->label,
            'description' => $this->description,
            'placeholder' => $this->placeholder,
            'required' => $this->is_core || $required,
            'options' => $type->hasOptions() ? (string) $this->options : null,
            'columns' => $type === FormFieldType::Repeater ? (string) $this->columns : null,
            'accept' => $type === FormFieldType::File ? ApplicationForm::acceptedTypes(['accept' => $this->accept]) : null,
            'max_mb' => $type === FormFieldType::File ? ApplicationForm::maxMb(['max_mb' => $this->max_mb]) : null,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('form_field')
            ->logOnly(['key', 'type', 'label', 'required_default'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    private static function uniqueKey(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'field';
        $base = Str::limit($base, 40, '');
        $candidate = $base;
        $i = 2;

        while (static::query()->where('key', $candidate)->exists()) {
            $candidate = $base.'_'.$i++;
        }

        return $candidate;
    }
}
