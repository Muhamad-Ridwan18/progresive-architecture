<?php

namespace App\Filament\Resources\JobCategories;

use App\Filament\Resources\JobCategories\Pages\CreateJobCategory;
use App\Filament\Resources\JobCategories\Pages\EditJobCategory;
use App\Filament\Resources\JobCategories\Pages\ListJobCategories;
use App\Filament\Resources\JobCategories\Schemas\JobCategoryForm;
use App\Filament\Resources\JobCategories\Tables\JobCategoriesTable;
use App\Models\JobCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Kategori pekerjaan beserta pilihan awal isian formulir lamarannya (SRS FR-RC-010).
 */
class JobCategoryResource extends Resource
{
    protected static ?string $model = JobCategory::class;

    protected static ?string $navigationLabel = 'Job Categories';

    protected static ?string $modelLabel = 'job category';

    protected static ?string $pluralModelLabel = 'Job Categories';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'RECRUITMENT';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return JobCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobCategoriesTable::configure($table);
    }

    /** Template formulir dikelola lewat Form Builder; tidak termasuk pencarian global (SRS FR-DB-020). */
    public static function canGloballySearch(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobCategories::route('/'),
            'create' => CreateJobCategory::route('/create'),
            'edit' => EditJobCategory::route('/{record}/edit'),
        ];
    }
}
