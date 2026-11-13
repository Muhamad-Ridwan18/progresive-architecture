<?php

namespace App\Filament\Resources\FormFields\Pages;

use App\Filament\Resources\FormFields\FormFieldResource;
use App\Support\ApplicationForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFormField extends EditRecord
{
    protected static string $resource = FormFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->hidden(fn (): bool => $this->record->is_core),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Jenis isian inti terkunci di form (disabled), jadi tidak ikut terkirim; nilainya tetap yang lama.
        return ApplicationForm::tidyLibraryField($data + ['type' => $this->record->type]);
    }

    protected function getRedirectUrl(): string
    {
        return FormFieldResource::getUrl('index');
    }
}
