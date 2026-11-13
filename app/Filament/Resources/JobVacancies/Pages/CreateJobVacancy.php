<?php

namespace App\Filament\Resources\JobVacancies\Pages;

use App\Filament\Resources\JobVacancies\JobVacancyResource;
use App\Services\VacancyFormComposer;
use Filament\Resources\Pages\CreateRecord;

class CreateJobVacancy extends CreateRecord
{
    protected static string $resource = JobVacancyResource::class;

    /** Awal: pilihan bawaan kategori yang dipilih belum ada, jadi isian standar. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['form_fields'] = app(VacancyFormComposer::class)->compose($data['field_keys'] ?? [], $data['required_keys'] ?? []);
        unset($data['field_keys'], $data['required_keys']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return JobVacancyResource::getUrl('index');
    }
}
