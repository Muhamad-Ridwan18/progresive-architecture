<?php

namespace App\Filament\Resources\JobCategories\Schemas;

use App\Filament\Resources\FormFields\Schemas\FormFieldChecklist;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class JobCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        [$keys, $required] = FormFieldChecklist::make('default_field_keys', 'default_required_keys');

        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('Category name')
                            ->required()
                            ->maxLength(120),
                    ]),
                Section::make('Default application form')
                    ->description('Pilihan awal isian untuk lowongan baru dalam kategori ini. Tiap lowongan tetap bisa mengubah centangnya. Perubahan di sini tidak mengubah lowongan yang sudah dibuat.')
                    ->schema([$keys, $required]),
            ]);
    }
}
