<?php

namespace App\Filament\Resources\FormFields\Schemas;

use App\Models\FormField;
use App\Services\VacancyFormComposer;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Dua daftar centang isian pustaka formulir lamaran (SRS 6.2): isian yang dipakai, dan di antaranya yang wajib.
 * Dipakai di form kategori pekerjaan (pilihan bawaan) dan form lowongan. Isian inti (nama, email, telepon) selalu
 * tercentang dan terkunci.
 */
class FormFieldChecklist
{
    /**
     * @return list<CheckboxList>
     */
    public static function make(string $keysName, string $requiredName): array
    {
        return [
            CheckboxList::make($keysName)
                ->label('Form fields')
                ->helperText('Nama lengkap, email, dan nomor telepon selalu ada di setiap formulir. Centang isian tambahan untuk posisi ini; isian baru dibuat di menu Form Builder.')
                ->options(fn (): array => FormField::query()->where('is_core', false)->inOrder()->pluck('label', 'key')->all())
                ->descriptions(fn (): array => FormField::query()->where('is_core', false)->inOrder()->get()
                    ->mapWithKeys(fn (FormField $f): array => [$f->key => $f->type->getLabel()])
                    ->all())
                ->columns(['default' => 1, 'md' => 2])
                // Awal untuk form baru: isian inti, CV, dan surat lamaran (form edit mengisinya dari data yang ada).
                ->default(VacancyFormComposer::DEFAULT_KEYS)
                ->live()
                ->dehydrateStateUsing(fn (?array $state): array => array_values(array_diff($state ?? [], FormField::CORE_KEYS))),
            CheckboxList::make($requiredName)
                ->label('Required')
                ->helperText('Dari isian tambahan yang dicentang di atas, mana yang wajib diisi. Nama, email, dan telepon selalu wajib.')
                ->options(fn (Get $get): array => FormField::query()->inOrder()
                    ->whereIn('key', (array) $get($keysName))
                    ->pluck('label', 'key')
                    ->all())
                ->default(['cv'])
                ->columns(['default' => 1, 'md' => 2])
                ->visible(fn (Get $get): bool => (array) $get($keysName) !== [])
                // Kunci yang tidak lagi dicentang tidak boleh tersisa sebagai "wajib".
                ->dehydrateStateUsing(fn (?array $state, Get $get): array => array_values(array_intersect($state ?? [], (array) $get($keysName)))),
        ];
    }
}
