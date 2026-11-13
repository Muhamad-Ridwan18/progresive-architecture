<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Enums\CategoryType;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Category type')
                    ->options(CategoryType::class)
                    ->default(fn (): string => request()->query('type') === CategoryType::Article->value
                        ? CategoryType::Article->value
                        : CategoryType::Project->value)
                    ->required()
                    ->live()
                    // Jenis tidak boleh berubah setelah dibuat: konten sudah terikat pada jenisnya.
                    ->disabledOn('edit')
                    ->dehydratedWhenHidden(),
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(120),
                TextInput::make('slug')
                    ->label('Slug')
                    ->helperText('Dibuat otomatis dari nama bila dikosongkan. Dipakai di URL.')
                    ->maxLength(140)
                    ->alphaDash()
                    ->unique(
                        table: Category::class,
                        column: 'slug',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('type', $get('type')),
                    ),
            ]);
    }
}
