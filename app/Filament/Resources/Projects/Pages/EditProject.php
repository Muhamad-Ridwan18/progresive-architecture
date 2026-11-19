<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Actions\Projects\ArchiveProject;
use App\Actions\Projects\DiscardProjectDraft;
use App\Actions\Projects\PublishProject;
use App\Actions\Projects\SaveProjectDraft;
use App\Actions\Projects\StaleDraftException;
use App\Enums\ProjectStatus;
use App\Filament\Concerns\ReportsInvalidFields;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Throwable;

/**
 * Editor proyek. Berbeda dari halaman edit bawaan Filament:
 *  - Tidak ada tombol Save. Semua perubahan tersimpan otomatis ke DRAFT (SRS 11.1).
 *  - Versi published hanya berubah lewat tombol Publish (SRS 11.2).
 *  - Nomor versi draft mencegah dua tab/administrator saling menimpa (SRS FR-WF-004).
 *
 * @property Project $record
 */
class EditProject extends EditRecord
{
    use ReportsInvalidFields;

    protected static string $resource = ProjectResource::class;

    /** Versi draft yang dimuat editor ini. Dikirim saat menyimpan untuk mendeteksi tabrakan. */
    #[Locked]
    public int $draftVersion = 1;

    /**
     * Identitas editor ini (satu per tab yang dibuka). Penyimpanan usang dari editor yang SAMA
     * diterima (permintaan tumpang tindih), dari editor lain ditolak. Dikunci agar tidak bisa
     * dipalsukan dari browser.
     */
    #[Locked]
    public string $editorId = '';

    /** saved | saving | invalid | conflict | failed */
    public string $saveState = 'saved';

    public ?string $savedAt = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->draftVersion = $this->getRecord()->draft_version;
        $this->editorId = Str::random(24);
    }

    /** Form selalu diisi dari DRAFT, bukan dari kolom utama (versi published). */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->getRecord()->draftFields();
    }

    // ------------------------------------------------------------------ auto-save

    /** Dipanggil Livewire untuk setiap perubahan di bawah `data`, termasuk pembaruan induk yang dibatch. */
    public function updatedData(): void
    {
        $this->autosave();
    }

    public function autosave(): bool
    {
        $this->authorizeAccess();
        $this->saveState = 'saving';

        try {
            // Memvalidasi seluruh form. Galat tampil di bawah field terkait.
            $data = $this->form->getState();
        } catch (ValidationException $e) {
            $this->rememberInvalidFields($e->errors());
            $this->saveState = 'invalid';

            return false;
        }

        try {
            $saved = app(SaveProjectDraft::class)->handle($this->getRecord(), $data, $this->draftVersion, auth()->id(), $this->editorId);
        } catch (StaleDraftException $e) {
            $this->saveState = 'conflict';

            Notification::make()
                ->title('Perubahan tidak disimpan')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return false;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError("data.{$field}", $messages[0]);
            }
            $this->rememberInvalidFields($e->errors());
            $this->saveState = 'invalid';

            return false;
        } catch (Throwable $e) {
            report($e);
            $this->saveState = 'failed';

            return false;
        }

        $this->record = $saved;
        $this->draftVersion = $saved->draft_version;
        $this->saveState = 'saved';
        $this->savedAt = now()->format('H:i:s');
        $this->clearInvalidFields();

        return true;
    }

    /**
     * `save()` bawaan Filament terbuka untuk dipanggil dari browser dan akan menulis langsung ke
     * kolom utama, melewati aturan draft. Dialihkan ke auto-save.
     */
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $this->autosave();
    }

    /** Perubahan gambar terjadi di komponen lain; muat ulang proyek agar status ikut diperbarui. */
    #[On('project-media-changed')]
    public function onMediaChanged(): void
    {
        $this->record = Project::query()->findOrFail($this->getRecord()->getKey());
    }

    // ------------------------------------------------------------------ tampilan

    public function getHeading(): string
    {
        return $this->getRecord()->draftFields()['name'] ?? 'Project';
    }

    public function getSubheading(): string
    {
        $status = $this->getRecord()->status->getLabel();

        $save = match ($this->saveState) {
            'saving' => 'Saving…',
            'invalid' => $this->invalidFields !== [] ? 'Not saved — fix: '.$this->invalidFieldsSummary() : 'Not saved — fix the highlighted fields',
            'conflict' => 'Not saved — changed elsewhere, reload the page',
            'failed' => 'Not saved — failed, use Retry save',
            default => $this->savedAt ? "Saved {$this->savedAt}" : 'All changes saved',
        };

        return "{$status} · {$save}";
    }

    protected function getFormActions(): array
    {
        return []; // Tidak ada tombol Save: semua tersimpan otomatis.
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry')
                ->label('Retry save')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => in_array($this->saveState, ['failed', 'invalid'], true))
                ->action(fn () => $this->autosave()),

            Action::make('reload')
                ->label('Reload')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->saveState === 'conflict')
                ->action(fn () => $this->redirect(ProjectResource::getUrl('edit', ['record' => $this->getRecord()]))),

            // Pratinjau draft di tab baru, memakai tampilan website sebenarnya (SRS FR-CM-001).
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (): string => route('projects.preview', $this->getRecord()), shouldOpenInNewTab: true),

            Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->visible(fn (): bool => in_array($this->getRecord()->status, [ProjectStatus::Draft, ProjectStatus::UnpublishedChanges], true))
                ->requiresConfirmation()
                ->modalHeading('Publish project?')
                ->modalDescription('Versi yang sedang tampil di website akan digantikan oleh draft ini.')
                ->action(fn () => $this->publish()),

            // Aksi yang jarang dipakai dikelompokkan di menu "⋯" agar Preview dan Publish yang menonjol.
            ActionGroup::make([
                Action::make('discard')
                    ->label('Discard changes')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (): bool => $this->getRecord()->status === ProjectStatus::UnpublishedChanges)
                    ->requiresConfirmation()
                    ->modalHeading('Discard unpublished changes?')
                    ->modalDescription('Draft dikembalikan ke versi yang sedang tampil di website. Tindakan ini tidak dapat dibatalkan.')
                    ->action(fn () => $this->discard()),

                Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->visible(fn (): bool => $this->getRecord()->status !== ProjectStatus::Archived)
                    ->requiresConfirmation()
                    ->modalDescription('Proyek tidak akan tampil di website sampai dipulihkan.')
                    ->action(function (): void {
                        $this->record = app(ArchiveProject::class)->archive($this->getRecord());
                    }),

                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (): bool => $this->getRecord()->status === ProjectStatus::Archived)
                    ->action(function (): void {
                        $this->record = app(ArchiveProject::class)->restore($this->getRecord());
                    }),

                DeleteAction::make()
                    ->modalDescription('Proyek dan relasi gambarnya akan dihapus. Gambar masuk antrean penghapusan 7 hari.'),
            ])
                ->label('More actions')
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button()
                ->outlined(),
        ];
    }

    // ------------------------------------------------------------------ aksi

    private function publish(): void
    {
        // Pastikan editan terakhir (yang belum sempat auto-save) ikut tersimpan lebih dulu.
        if (! $this->autosave()) {
            Notification::make()
                ->title('Tidak dapat publish')
                ->body($this->invalidFields !== [] ? 'Perbaiki field berikut dulu: '.$this->invalidFieldsSummary().'.' : 'Perbaiki field yang bermasalah dulu.')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->record = app(PublishProject::class)->handle($this->getRecord(), auth()->id(), $this->editorId);
        } catch (ValidationException $e) {
            Notification::make()->title('Tidak dapat publish')->body($this->errorsAsHtml($e->errors()))->danger()->send();

            return;
        }

        $this->draftVersion = $this->getRecord()->draft_version;
        $this->saveState = 'saved';
        $this->savedAt = now()->format('H:i:s');

        Notification::make()->title('Project published')->success()->send();
    }

    private function discard(): void
    {
        $this->record = app(DiscardProjectDraft::class)->handle($this->getRecord(), auth()->id(), $this->editorId);
        $this->draftVersion = $this->getRecord()->draft_version;
        $this->saveState = 'saved';

        $this->form->fill($this->getRecord()->draftFields());
        $this->dispatch('project-media-reload');

        Notification::make()->title('Draft dikembalikan ke versi published')->success()->send();
    }
}
