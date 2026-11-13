<?php

namespace App\Filament\Resources\FormFields\Pages;

use App\Filament\Resources\FormFields\FormFieldResource;
use App\Support\ApplicationForm;
use Filament\Resources\Pages\CreateRecord;

class CreateFormField extends CreateRecord
{
    protected static string $resource = FormFieldResource::class;

    /** Opsi, kolom, dan batas berkas dirapikan sesuai jenis isian. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ApplicationForm::tidyLibraryField($data);
    }

    protected function getRedirectUrl(): string
    {
        return FormFieldResource::getUrl('index');
    }
}
