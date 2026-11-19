<?php

namespace App\Filament\Pages;

use App\Actions\About\DiscardAboutDraft;
use App\Actions\About\PublishAbout;
use App\Actions\About\SaveAboutDraft;
use App\Actions\Projects\StaleDraftException;
use App\Enums\PageStatus;
use App\Filament\Concerns\ReportsInvalidFields;
use App\Livewire\ProjectMediaManager;
use App\Models\AboutPage;
use App\Support\AboutContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Throwable;
use UnitEnum;

/**
 * Editor About Us berbasis bagian (SRS 5.4): setiap bagian diedit terpisah, bagian berulang
 * (statistik, tim, dst.) mendukung tambah, ubah, hapus, dan urutkan. Seperti editor proyek:
 * semua perubahan tersimpan otomatis ke DRAFT; website baru berubah lewat tombol Publish.
 *
 * @property-read Schema $form
 */
class ManageAboutUs extends Page
{
    use ReportsInvalidFields;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    protected static string|UnitEnum|null $navigationGroup = 'CONTENT MANAGEMENT';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'About Us';

    protected static ?string $title = 'About Us';

    protected static ?string $slug = 'about-us';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** Versi draft yang dimuat editor ini; dikirim saat menyimpan untuk mendeteksi tabrakan. */
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

    public ?string $saveMessage = null;

    public ?string $savedAt = null;

    public function mount(): void
    {
        $page = AboutPage::current();

        $this->draftVersion = $page->draft_version;
        $this->editorId = Str::random(24);
        $this->form->fill($page->draftContent());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Company Overview')
                ->description('Pengantar singkat perusahaan, bagian pertama halaman.')
                ->collapsible()
                ->schema([
                    self::auto(TextInput::make('overview.heading')->label('Heading')->maxLength(160)),
                    self::auto(Textarea::make('overview.body')->label('Overview')->rows(6)->maxLength(5000)),
                ]),

            Section::make('Company History')
                ->description('Uraian sejarah, ditambah tonggak sejarah (opsional).')
                ->collapsible()
                ->collapsed()
                ->schema([
                    self::auto(Textarea::make('history.body')->label('History')->rows(6)->maxLength(5000)),
                    self::auto(
                        Repeater::make('history.milestones')
                            ->label('Milestones')
                            ->schema([
                                self::auto(TextInput::make('year')->label('Year')->maxLength(12)),
                                self::auto(TextInput::make('title')->label('Title')->maxLength(160)),
                                self::auto(Textarea::make('description')->label('Description')->rows(2)->maxLength(1000)->columnSpanFull()),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => trim(($state['year'] ?? '').' '.($state['title'] ?? '')) ?: null)
                            ->maxItems(AboutContent::MAX_MILESTONES)
                            ->addActionLabel('Add milestone'),
                        repeater: true,
                    ),
                ]),

            Section::make('Vision & Mission')
                ->collapsible()
                ->collapsed()
                ->columns(2)
                ->schema([
                    self::auto(Textarea::make('vision_mission.vision')->label('Vision')->rows(5)->maxLength(2000)),
                    self::auto(Textarea::make('vision_mission.mission')->label('Mission')->rows(5)->maxLength(2000)),
                    Section::make('Photos')
                        ->description('Opsional. Foto tampil sebaris di bawah visi dan misi; isi keterangan singkat di bawah tiap foto (mis. "Tropical Future"). Urutan mengikuti daftar. Foto ikut berubah bersama Publish.')
                        ->compact()
                        ->columnSpanFull()
                        ->schema([
                            Livewire::make(ProjectMediaManager::class, fn (): array => [
                                'projectId' => AboutPage::current()->getKey(),
                                'role' => AboutPage::ROLE_GALLERY,
                                'kind' => 'about',
                            ])->key('vision-photos'),
                        ]),
                ]),

            Section::make('Company Values')
                ->collapsible()
                ->collapsed()
                ->schema([
                    self::auto(
                        Repeater::make('values')
                            ->label('Values')
                            ->schema([
                                self::auto(TextInput::make('title')->label('Title')->maxLength(120)),
                                self::auto(Textarea::make('description')->label('Description')->rows(2)->maxLength(600)),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->maxItems(AboutContent::MAX_VALUES)
                            ->addActionLabel('Add value'),
                        repeater: true,
                    ),
                ]),

            Section::make('Company Statistics')
                ->description('Angka ringkas, mis. "250+" dengan keterangan "Proyek selesai".')
                ->collapsible()
                ->collapsed()
                ->schema([
                    self::auto(
                        Repeater::make('statistics')
                            ->label('Statistics')
                            ->schema([
                                self::auto(TextInput::make('value')->label('Number')->maxLength(20)),
                                self::auto(TextInput::make('label')->label('Label')->maxLength(80)),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => trim(($state['value'] ?? '').' '.($state['label'] ?? '')) ?: null)
                            ->maxItems(AboutContent::MAX_STATISTICS)
                            ->addActionLabel('Add statistic'),
                        repeater: true,
                    ),
                ]),

            Section::make('Our Team')
                ->description('Opsional. Bagian ini tidak tampil di website bila kosong. Foto anggota tim ikut berubah bersama Publish.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    self::auto(
                        Repeater::make('team')
                            ->label('Team members')
                            ->schema([
                                // Id stabil per anggota: mengaitkannya dengan foto (peran `team:{id}`) walau urutan berubah.
                                Hidden::make('id')->default(fn (): string => Str::lower(Str::random(12))),
                                self::auto(TextInput::make('name')->label('Name')->maxLength(120)),
                                self::auto(TextInput::make('position')->label('Position')->maxLength(120)),
                                self::auto(Textarea::make('bio')->label('Bio')->rows(4)->maxLength(4000)->columnSpanFull()),
                                Section::make('Photo')
                                    ->description('Opsional. Klik foto untuk menentukan titik fokus pemotongan.')
                                    ->compact()
                                    ->columnSpanFull()
                                    ->schema([
                                        Livewire::make(ProjectMediaManager::class, fn (Get $get): array => [
                                            'projectId' => AboutPage::current()->getKey(),
                                            'role' => AboutPage::teamRole((string) $get('id')),
                                            'kind' => 'about',
                                        ])->key(fn (Get $get): string => 'team-photo-'.$get('id')),
                                    ]),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->maxItems(AboutContent::MAX_TEAM)
                            ->addActionLabel('Add team member'),
                        repeater: true,
                    ),
                ]),
        ]);
    }

    /**
     * Kolom dikirim ke server ~1,5 detik setelah pengguna berhenti mengetik (SRS FR-WF-001).
     * Penyimpanannya sendiri dipicu oleh updatedData() di bawah, BUKAN oleh afterStateUpdated per
     * kolom: bila dua kolom bersaudara berubah dalam satu batch, Livewire mengirimnya sebagai
     * pembaruan induk (mis. `data.overview`) dan callback per kolom tidak dipanggil, sehingga
     * editan tidak tersimpan padahal status menyatakan "saved".
     *
     * Pengulang (repeater) tambahan: menambah, menghapus, atau mengurutkan item adalah aksi di
     * server (bukan pembaruan properti), jadi hanya bisa dipicu lewat afterStateUpdated.
     */
    private static function auto(Field $field, bool $repeater = false): Field
    {
        return $repeater
            ? $field->live()->afterStateUpdated(fn ($livewire) => $livewire->autosave())
            : $field->live(onBlur: false, debounce: 1500);
    }

    /** Dipanggil Livewire untuk setiap perubahan di bawah `data`, termasuk pembaruan induk. */
    public function updatedData(): void
    {
        $this->autosave();
    }

    /** Foto diubah di komponen lain; render ulang agar status (Published / Unpublished Changes) ikut diperbarui. */
    #[On('project-media-changed')]
    public function onPhotoChanged(): void {}

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        // Tanpa tombol Save: semua tersimpan otomatis.
        return Form::make([EmbeddedSchema::make('form')])->id('form');
    }

    // ------------------------------------------------------------------ auto-save

    public function autosave(): bool
    {
        $this->saveState = 'saving';
        $this->saveMessage = null;

        try {
            $page = app(SaveAboutDraft::class)->handle(
                AboutPage::current(),
                $this->form->getState(),
                $this->draftVersion,
                auth()->id(),
                $this->editorId,
            );
        } catch (StaleDraftException $e) {
            $this->saveState = 'conflict';
            $this->saveMessage = $e->getMessage();

            Notification::make()->title('Perubahan tidak disimpan')->body($e->getMessage())->danger()->persistent()->send();

            return false;
        } catch (ValidationException $e) {
            // Pesan ditampilkan di subjudul (bukan notifikasi) agar tidak membanjiri saat mengetik.
            $this->saveState = 'invalid';
            $this->rememberInvalidFields($e->errors());
            $this->saveMessage = implode(' · ', $this->describeErrors($e->errors()));

            return false;
        } catch (Throwable $e) {
            report($e);
            $this->saveState = 'failed';

            return false;
        }

        $this->draftVersion = $page->draft_version;
        $this->saveState = 'saved';
        $this->savedAt = now()->format('H:i:s');
        $this->clearInvalidFields();

        return true;
    }

    // ------------------------------------------------------------------ tampilan

    public function getSubheading(): string
    {
        $status = AboutPage::current()->status->getLabel();

        $save = match ($this->saveState) {
            'saving' => 'Saving…',
            'invalid' => 'Not saved — '.($this->saveMessage ?? 'periksa isian'),
            'conflict' => 'Not saved — changed elsewhere, reload the page',
            'failed' => 'Not saved — failed, use Retry save',
            default => $this->savedAt ? "Saved {$this->savedAt}" : 'All changes saved',
        };

        return "{$status} · {$save}";
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
                ->action(fn () => $this->redirect(static::getUrl())),

            // Pratinjau draft di tab baru, memakai tampilan website sebenarnya (SRS 5.4).
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (): string => route('about.preview'), shouldOpenInNewTab: true),

            Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->visible(fn (): bool => $this->status() !== PageStatus::Published)
                ->requiresConfirmation()
                ->modalHeading('Publish About Us?')
                ->modalDescription('Halaman About Us di website akan digantikan oleh draft ini.')
                ->action(fn () => $this->publish()),

            Action::make('discard')
                ->label('Discard changes')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn (): bool => $this->status() === PageStatus::UnpublishedChanges)
                ->requiresConfirmation()
                ->modalHeading('Discard unpublished changes?')
                ->modalDescription('Draft dikembalikan ke versi yang sedang tampil di website. Tindakan ini tidak dapat dibatalkan.')
                ->action(fn () => $this->discard()),
        ];
    }

    private function status(): PageStatus
    {
        return AboutPage::current()->status;
    }

    private function publish(): void
    {
        // Pastikan editan terakhir (yang belum sempat auto-save) ikut tersimpan lebih dulu.
        if (! $this->autosave()) {
            Notification::make()
                ->title('Tidak dapat publish')
                ->body($this->invalidFields !== [] ? 'Perbaiki field berikut dulu: '.$this->invalidFieldsSummary().'.' : ($this->saveMessage ?? 'Perbaiki isian yang bermasalah dulu.'))
                ->warning()
                ->send();

            return;
        }

        try {
            $page = app(PublishAbout::class)->handle(AboutPage::current(), auth()->id(), $this->editorId);
        } catch (ValidationException $e) {
            Notification::make()->title('Tidak dapat publish')->body($this->errorsAsHtml($e->errors()))->danger()->send();

            return;
        }

        $this->draftVersion = $page->draft_version;
        $this->saveState = 'saved';
        $this->savedAt = now()->format('H:i:s');

        Notification::make()->title('About Us published')->success()->send();
    }

    private function discard(): void
    {
        $page = app(DiscardAboutDraft::class)->handle(AboutPage::current(), auth()->id(), $this->editorId);

        $this->draftVersion = $page->draft_version;
        $this->saveState = 'saved';
        $this->form->fill($page->draftContent());
        $this->dispatch('project-media-reload');

        Notification::make()->title('Draft dikembalikan ke versi published')->success()->send();
    }
}
