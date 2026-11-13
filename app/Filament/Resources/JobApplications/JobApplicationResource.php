<?php

namespace App\Filament\Resources\JobApplications;

use App\Filament\Resources\JobApplications\Pages\ListJobApplications;
use App\Filament\Resources\JobApplications\Pages\ViewJobApplication;
use App\Filament\Resources\JobApplications\Schemas\JobApplicationInfolist;
use App\Filament\Resources\JobApplications\Tables\JobApplicationsTable;
use App\Models\JobApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Daftar pelamar (SRS 6.3). Lamaran hanya masuk dari website, jadi tidak ada form tambah/ubah/hapus;
 * administrator hanya melihat, mengubah status, dan menambah catatan internal.
 */
class JobApplicationResource extends Resource
{
    protected static ?string $model = JobApplication::class;

    protected static ?string $navigationLabel = 'Applicants';

    protected static ?string $modelLabel = 'applicant';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'RECRUITMENT';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'applicant_name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return JobApplicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JobApplicationsTable::configure($table);
    }

    /**
     * Pencarian global dashboard (SRS FR-DB-020/021): hasil dikelompokkan per resource dan membuka halaman pengelolaannya.
     *
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['applicant_name', 'applicant_email', 'reference_number'];
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter(['Email' => $record->applicant_email, 'Reference' => $record->reference_number]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobApplications::route('/'),
            'view' => ViewJobApplication::route('/{record}'),
        ];
    }
}
