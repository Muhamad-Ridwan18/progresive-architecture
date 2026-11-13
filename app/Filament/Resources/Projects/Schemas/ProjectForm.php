<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Livewire\ProjectMediaManager;
use App\Models\Category;
use App\Models\Project;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Create: hanya nama dan kategori, lalu langsung diarahkan ke editor.
 * Edit: seluruh field + pengelola gambar. Setiap perubahan disimpan otomatis sebagai draft.
 */
class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        // Satu kolom: setiap kartu selebar halaman (bawaan Filament membagi dua).
        return $schema->columns(1)->components([
            // Ringkasan perubahan yang belum di-publish (hanya di editor proyek yang sudah live).
            Callout::make(fn (?Project $record): string => self::changeSummary($record))
                ->description('Website masih menampilkan versi sebelumnya sampai Anda menekan Publish.')
                ->warning()
                ->visibleOn('edit')
                ->visible(fn (?Project $record): bool => $record !== null && self::changedLabels($record) !== []),

            Section::make('Project details')
                ->columns(2)
                ->schema([
                    self::autosave(TextInput::make('name')->label('Name')->required()->maxLength(255)),
                    self::autosave(
                        Select::make('category_id')
                            ->label('Category')
                            ->options(fn (): array => Category::query()
                                ->ofType(Project::categoryType())
                                ->orderBy('sort_order')
                                ->pluck('name', 'id')
                                ->all())
                            ->required()
                            ->searchable()
                            ->helperText('Kelola kategori di menu Categories.'),
                        debounce: false,
                    ),
                    self::autosave(
                        TextInput::make('slug')->label('Slug')->maxLength(255)
                            ->helperText('Dipakai di URL proyek.')
                            ->visibleOn('edit')
                    ),
                    self::autosave(TextInput::make('year')->label('Year')->numeric()->minValue(1900)->visibleOn('edit')),
                    self::autosave(TextInput::make('location')->label('Location')->maxLength(255)->visibleOn('edit')),
                    self::autosave(TextInput::make('client')->label('Client')->maxLength(255)->visibleOn('edit')),
                    self::autosave(
                        Textarea::make('description')->label('Description')->rows(8)->columnSpanFull()->visibleOn('edit')
                    ),
                ]),

            // Data teknis dipisah dan dapat dilipat agar gambar dan galeri tidak terdorong jauh ke bawah.
            Section::make('Technical data')
                ->description(fn (?Project $record): ?string => self::technicalSummary($record))
                ->collapsible()
                ->columns(2)
                ->visibleOn('edit')
                ->schema([
                    self::autosave(TextInput::make('typology')->label('Typology')->placeholder('Residential, Commercial, ...')->maxLength(80)),
                    self::autosave(TextInput::make('storeys')->label('Storeys')->numeric()->minValue(1)->maxValue(200)),
                    self::autosave(TextInput::make('land_area')->label('Land area')->numeric()->minValue(0)->step(0.1)->suffix('m²')),
                    self::autosave(TextInput::make('building_area')->label('Building area')->numeric()->minValue(0)->step(0.1)->suffix('m²')),
                    self::autosave(TextInput::make('stage')->label('Status')->placeholder('Complete, On going, ...')->maxLength(40)),
                ]),

            Section::make('Cover image')
                ->description('Gambar utama proyek. Klik gambar untuk menentukan titik fokus pemotongan.')
                ->visibleOn('edit')
                ->schema([
                    Livewire::make(ProjectMediaManager::class, fn (?Project $record): array => [
                        'projectId' => $record->id,
                        'role' => Project::ROLE_COVER,
                    ])->key('cover-manager'),
                ]),

            Section::make('Gallery')
                ->description('Urutan foto di sini menjadi urutan di halaman proyek. Pola tata letak ditentukan sistem.')
                ->visibleOn('edit')
                ->schema([
                    Livewire::make(ProjectMediaManager::class, fn (?Project $record): array => [
                        'projectId' => $record->id,
                        'role' => Project::ROLE_GALLERY,
                    ])->key('gallery-manager'),
                ]),
        ]);
    }

    /**
     * Kolom dikirim ke server ~1,5 detik setelah pengguna berhenti mengetik (SRS FR-WF-001).
     * Penyimpanannya dipicu EditProject::updatedData(), bukan callback per kolom, agar tidak ada
     * editan yang terlewat bila beberapa kolom berubah dalam satu batch. Di halaman Create tidak ada
     * pengait itu, jadi tidak terjadi penyimpanan apa pun.
     */
    private static function autosave(TextInput|Select|Textarea $field, bool $debounce = true): TextInput|Select|Textarea
    {
        $key = $field->getName();

        return $field
            ->live(onBlur: false, debounce: $debounce ? 1500 : 0)
            // Kolom yang isinya berbeda dari versi di website diberi penanda "Changed" di atas kolomnya.
            ->hint(fn (?Project $record): ?string => $record !== null && in_array($key, $record->changedFieldKeys(), true) ? 'Changed' : null)
            ->hintColor('warning')
            ->hintIcon(fn (?Project $record): ?string => $record !== null && in_array($key, $record->changedFieldKeys(), true) ? 'heroicon-m-pencil-square' : null);
    }

    /** Nama tampilan field yang berubah (plus "Images" bila daftar gambar berubah). */
    private static function changedLabels(Project $project): array
    {
        $labels = [
            'name' => 'Name', 'slug' => 'Slug', 'category_id' => 'Category', 'location' => 'Location', 'year' => 'Year',
            'description' => 'Description', 'client' => 'Client', 'typology' => 'Typology', 'land_area' => 'Land area',
            'building_area' => 'Building area', 'storeys' => 'Storeys', 'stage' => 'Status',
        ];

        $changed = array_map(fn (string $key): string => $labels[$key], $project->changedFieldKeys());

        if ($project->hasUnpublishedMediaChanges()) {
            $changed[] = 'Images';
        }

        return $changed;
    }

    private static function changeSummary(?Project $project): string
    {
        $labels = $project ? self::changedLabels($project) : [];

        return count($labels).' perubahan belum di-publish: '.implode(', ', $labels);
    }

    private static function technicalSummary(?Project $project): ?string
    {
        if ($project === null) {
            return null;
        }

        $fields = $project->draftFields();
        $format = fn (float|int|null $value): ?string => $value === null ? null : rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',');

        $parts = array_filter([
            $fields['typology'],
            $fields['storeys'] ? $fields['storeys'].' '.Str::plural('floor', (int) $fields['storeys']) : null,
            $fields['land_area'] !== null ? $format($fields['land_area']).' m² land' : null,
            $fields['building_area'] !== null ? $format($fields['building_area']).' m² building' : null,
            $fields['stage'],
        ]);

        return $parts === [] ? 'Typology, luas, jumlah lantai, dan status pembangunan.' : implode(' · ', $parts);
    }
}
