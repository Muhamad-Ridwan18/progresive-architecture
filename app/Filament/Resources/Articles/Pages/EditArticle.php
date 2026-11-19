<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Actions\Articles\DiscardArticleDraft;
use App\Actions\Articles\PublishArticle;
use App\Actions\Articles\SaveArticleDraft;
use App\Actions\Articles\ScheduleArticle;
use App\Actions\Projects\StaleDraftException;
use App\Enums\ArticleStatus;
use App\Filament\Concerns\ReportsInvalidFields;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Throwable;

/**
 * Editor artikel. Sama dengan editor proyek: tanpa tombol Save, semua perubahan tersimpan otomatis ke
 * DRAFT (SRS 11.1); versi published hanya berubah lewat Publish atau jadwal (SRS 11.2); nomor versi draft
 * mencegah dua tab/administrator saling menimpa (SRS FR-WF-004).
 *
 * @property Article $record
 */
class EditArticle extends EditRecord
{
    use ReportsInvalidFields;

    protected static string $resource = ArticleResource::class;

    #[Locked]
    public int $draftVersion = 1;

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
            $data = $this->form->getState();
        } catch (ValidationException $e) {
            $this->rememberInvalidFields($e->errors());
            $this->saveState = 'invalid';

            return false;
        }

        try {
            $saved = app(SaveArticleDraft::class)->handle($this->getRecord(), $data, $this->draftVersion, auth()->id(), $this->editorId);
        } catch (StaleDraftException $e) {
            $this->saveState = 'conflict';

            Notification::make()->title('Perubahan tidak disimpan')->body($e->getMessage())->danger()->persistent()->send();

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

    /** `save()` bawaan Filament akan menulis langsung ke kolom utama, melewati aturan draft. Dialihkan. */
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $this->autosave();
    }

    /** Perubahan cover terjadi di komponen lain; muat ulang artikel agar status ikut diperbarui. */
    #[On('project-media-changed')]
    public function onMediaChanged(): void
    {
        $this->record = Article::query()->findOrFail($this->getRecord()->getKey());
    }

    // ------------------------------------------------------------------ tampilan

    public function getHeading(): string
    {
        return $this->getRecord()->draftFields()['title'] ?? 'Article';
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();
        $status = $record->status->getLabel();

        if ($record->status === ArticleStatus::Scheduled) {
            $status .= ' · '.$record->scheduled_at->format('d M Y, H:i');
        }

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
        return [];
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
                ->action(fn () => $this->redirect(ArticleResource::getUrl('edit', ['record' => $this->getRecord()]))),
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (): string => route('news.preview', $this->getRecord()), shouldOpenInNewTab: true),
            Action::make('publish')
                ->label('Publish now')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->status !== ArticleStatus::Published)
                ->requiresConfirmation()
                ->modalHeading('Publish article?')
                ->modalDescription('Versi yang sedang tampil di website akan digantikan oleh draft ini.')
                ->action(fn () => $this->publish()),
            Action::make('schedule')
                ->label('Schedule')
                ->icon('heroicon-o-clock')
                ->color('info')
                ->visible(fn (): bool => $this->getRecord()->status === ArticleStatus::Draft)
                ->schema([
                    DateTimePicker::make('scheduled_at')
                        ->label('Publish at')
                        ->seconds(false)
                        ->minDate(now())
                        ->required(),
                ])
                ->action(fn (array $data) => $this->schedule($data['scheduled_at'])),
            Action::make('cancelSchedule')
                ->label('Cancel schedule')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->status === ArticleStatus::Scheduled)
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record = app(ScheduleArticle::class)->cancel($this->getRecord(), auth()->id());
                }),
            // Aksi yang jarang dipakai dikelompokkan di menu "⋯" agar Preview dan Publish yang menonjol.
            ActionGroup::make([
                Action::make('discard')
                    ->label('Discard changes')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (): bool => $this->getRecord()->status === ArticleStatus::UnpublishedChanges)
                    ->requiresConfirmation()
                    ->modalHeading('Discard unpublished changes?')
                    ->modalDescription('Draft dikembalikan ke versi yang sedang tampil di website. Tindakan ini tidak dapat dibatalkan.')
                    ->action(fn () => $this->discard()),
                Action::make('feature')
                    ->label(fn (): string => $this->getRecord()->is_featured ? 'Remove featured' : 'Set featured')
                    ->icon(fn (): string => $this->getRecord()->is_featured ? 'heroicon-s-star' : 'heroicon-o-star')
                    ->color('gray')
                    ->visible(fn (): bool => $this->getRecord()->status->isLive())
                    ->action(function (): void {
                        $record = $this->getRecord();
                        $record->markFeatured(! $record->is_featured);
                        $this->record = $record->refresh();
                    }),
                DeleteAction::make()
                    ->modalDescription('Artikel dan relasi gambarnya akan dihapus. Gambar masuk antrean penghapusan 7 hari.'),
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
        if (! $this->autosave()) {
            Notification::make()
                ->title('Tidak dapat publish')
                ->body($this->invalidFields !== [] ? 'Perbaiki field berikut dulu: '.$this->invalidFieldsSummary().'.' : 'Perbaiki field yang bermasalah dulu.')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->record = app(PublishArticle::class)->handle($this->getRecord(), auth()->id(), $this->editorId);
        } catch (ValidationException $e) {
            Notification::make()->title('Tidak dapat publish')->body($this->errorsAsHtml($e->errors()))->danger()->send();

            return;
        }

        $this->draftVersion = $this->getRecord()->draft_version;
        $this->saveState = 'saved';
        $this->savedAt = now()->format('H:i:s');

        Notification::make()->title('Article published')->success()->send();
    }

    private function schedule(mixed $at): void
    {
        if (! $this->autosave()) {
            Notification::make()
                ->title('Tidak dapat menjadwalkan')
                ->body($this->invalidFields !== [] ? 'Perbaiki field berikut dulu: '.$this->invalidFieldsSummary().'.' : 'Perbaiki field yang bermasalah dulu.')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->record = app(ScheduleArticle::class)->schedule($this->getRecord(), Carbon::parse($at), auth()->id());
        } catch (ValidationException $e) {
            Notification::make()->title('Tidak dapat menjadwalkan')->body($this->errorsAsHtml($e->errors()))->danger()->send();

            return;
        }

        Notification::make()->title('Artikel dijadwalkan')->success()->send();
    }

    private function discard(): void
    {
        $this->record = app(DiscardArticleDraft::class)->handle($this->getRecord(), auth()->id(), $this->editorId);
        $this->draftVersion = $this->getRecord()->draft_version;
        $this->saveState = 'saved';

        $this->form->fill($this->getRecord()->draftFields());
        $this->dispatch('project-media-reload');

        Notification::make()->title('Draft dikembalikan ke versi published')->success()->send();
    }
}
