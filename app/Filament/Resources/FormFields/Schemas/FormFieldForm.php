<?php

namespace App\Filament\Resources\FormFields\Schemas;

use App\Enums\FormFieldType;
use App\Models\FormField;
use App\Support\ApplicationForm;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class FormFieldForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Field')
                ->columns(2)
                ->schema([
                    TextInput::make('label')
                        ->label('Label')
                        ->helperText('Teks yang dilihat pelamar.')
                        ->required()
                        ->maxLength(120),
                    Select::make('type')
                        ->label('Type')
                        // Section hanya pemisah tampilan, bukan isian yang dijawab, jadi tidak ada di pustaka.
                        ->options(collect(FormFieldType::cases())
                            ->reject(fn (FormFieldType $type): bool => $type === FormFieldType::Section)
                            ->mapWithKeys(fn (FormFieldType $type): array => [$type->value => $type->getLabel()])
                            ->all())
                        ->required()
                        ->live()
                        // Jenis isian inti tidak boleh diganti: dari sinilah nama, email, dan telepon pelamar dibaca.
                        ->disabled(fn (?FormField $record): bool => (bool) $record?->is_core)
                        ->dehydrated(),
                    TextInput::make('description')->label('Description')->maxLength(255)->columnSpanFull(),
                    TextInput::make('placeholder')
                        ->label('Placeholder')
                        ->maxLength(120)
                        ->visible(fn (Get $get): bool => ! in_array(self::type($get), [FormFieldType::File, FormFieldType::Repeater], true)),
                    Toggle::make('required_default')
                        ->label('Required by default')
                        ->helperText('Awal centang "wajib" pada lowongan; tetap bisa diubah per lowongan.')
                        ->hidden(fn (?FormField $record): bool => (bool) $record?->is_core),
                    Textarea::make('options')
                        ->label('Options')
                        ->helperText('Satu opsi per baris. Untuk Checkbox, kosongkan bila hanya satu kotak persetujuan.')
                        ->rows(4)
                        ->required(fn (Get $get): bool => in_array(self::type($get), [FormFieldType::Dropdown, FormFieldType::Radio], true))
                        ->visible(fn (Get $get): bool => self::type($get)?->hasOptions() ?? false)
                        ->columnSpanFull(),
                    Textarea::make('columns')
                        ->label('Columns')
                        ->helperText('Satu nama kolom per baris, mis. Perusahaan, Jabatan, Tahun. Pelamar mengisi satu baris per entri.')
                        ->rows(3)
                        ->required()
                        ->visible(fn (Get $get): bool => self::type($get) === FormFieldType::Repeater)
                        ->columnSpanFull(),
                    CheckboxList::make('accept')
                        ->label('Allowed file types')
                        ->options(ApplicationForm::FILE_TYPES)
                        ->default(['pdf'])
                        ->columns(5)
                        ->required()
                        ->visible(fn (Get $get): bool => self::type($get) === FormFieldType::File)
                        ->columnSpanFull(),
                    TextInput::make('max_mb')
                        ->label('Max file size (MB)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(ApplicationForm::MAX_MB_LIMIT)
                        ->default(ApplicationForm::DEFAULT_MAX_MB)
                        ->visible(fn (Get $get): bool => self::type($get) === FormFieldType::File),
                ]),
        ]);
    }

    /** Select dengan opsi enum dapat mengirim string maupun enum. */
    private static function type(Get $get): ?FormFieldType
    {
        return ApplicationForm::typeOf($get('type'));
    }
}
