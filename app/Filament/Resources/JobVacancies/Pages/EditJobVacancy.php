<?php

namespace App\Filament\Resources\JobVacancies\Pages;

use App\Filament\Resources\JobVacancies\JobVacancyResource;
use App\Services\VacancyFormComposer;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJobVacancy extends EditRecord
{
    protected static string $resource = JobVacancyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (): bool => $this->record->applications()->exists()),
        ];
    }

    /** Centang dibaca dari salinan formulir lowongan. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $selection = app(VacancyFormComposer::class)->selectionFrom($data['form_fields'] ?? null);

        return [...$data, 'field_keys' => $selection['keys'], 'required_keys' => $selection['required']];
    }

    /** Menyimpan centang menyusun ulang salinan formulir; isian khusus di luar pustaka dipertahankan. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['form_fields'] = app(VacancyFormComposer::class)->compose(
            $data['field_keys'] ?? [],
            $data['required_keys'] ?? [],
            $this->record->form_fields,
        );
        unset($data['field_keys'], $data['required_keys']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return JobVacancyResource::getUrl('index');
    }
}
