<?php

namespace App\Filament\Resources\JobApplications\Pages;

use App\Actions\Recruitment\ChangeApplicationStatus;
use App\Enums\ApplicationStatus;
use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Models\JobApplication;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Detail pelamar (SRS FR-RC-034/035): status selalu terlihat di atas, ubah status dan catatan internal
 * lewat aksi header, dokumen privat lewat rute admin.
 */
class ViewJobApplication extends ViewRecord
{
    protected static string $resource = JobApplicationResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var JobApplication $application */
        $application = $this->getRecord();

        return $application->applicant_name;
    }

    /** Judul dengan avatar inisial (mockup); `getTitle()` tetap teks biasa untuk judul tab browser. */
    public function getHeading(): string|Htmlable
    {
        /** @var JobApplication $application */
        $application = $this->getRecord();

        $initials = collect(preg_split('/\s+/', trim($application->applicant_name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return new HtmlString('<span class="pa-head"><span class="pa-avatar" aria-hidden="true">'.e($initials).'</span><span>'.e($application->applicant_name).'</span></span>');
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var JobApplication $application */
        $application = $this->getRecord();

        $badge = Blade::render(
            '<x-filament::badge :color="$color">{{ $label }}</x-filament::badge>',
            ['color' => $application->status->getColor(), 'label' => $application->status->getLabel()],
        );

        return new HtmlString('<span class="pa-sub"><span>'.e($application->vacancy->title).'</span><span>&bull;</span><span>'.e($application->reference_number).'</span>'.$badge.'</span>');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addNote')
                ->label('Add note')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->schema([
                    Textarea::make('body')->label('Internal note')->required()->rows(4)->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    $this->getRecord()->notes()->create([
                        'user_id' => auth()->id(),
                        'body' => $data['body'],
                    ]);

                    $this->record->refresh();

                    Notification::make()->title('Catatan disimpan')->success()->send();
                }),
            Action::make('changeStatus')
                ->label('Change status')
                ->icon('heroicon-o-arrow-path')
                ->schema([
                    Radio::make('status')
                        ->label('New status')
                        ->options(ApplicationStatus::class)
                        ->default(fn (): string => $this->getRecord()->status->value)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $changed = app(ChangeApplicationStatus::class)->handle(
                        $this->getRecord(),
                        $data['status'] instanceof ApplicationStatus ? $data['status'] : ApplicationStatus::from($data['status']),
                        auth()->user(),
                    );

                    $this->refreshFormData(['status']);
                    $this->record->refresh();

                    Notification::make()
                        ->title($changed ? 'Status diperbarui' : 'Status tidak berubah')
                        ->success()
                        ->send();
                }),
        ];
    }
}
