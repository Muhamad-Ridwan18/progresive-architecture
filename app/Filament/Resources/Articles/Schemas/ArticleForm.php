<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Livewire\ProjectMediaManager;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

/**
 * Create: hanya judul dan kategori, lalu langsung diarahkan ke editor.
 * Edit: dua kolom. Kiri = tulisan (judul, ringkasan, isi, cover); kanan = Publishing, Organize, dan URL.
 * Setiap perubahan disimpan otomatis sebagai draft (SRS 11.1).
 */
class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            // Ringkasan perubahan yang belum di-publish (hanya di editor artikel yang sudah live).
            Callout::make(fn (?Article $record): string => self::changeSummary($record))
                ->description('Website masih menampilkan versi sebelumnya sampai Anda menekan Publish now.')
                ->warning()
                ->visibleOn('edit')
                ->visible(fn (?Article $record): bool => $record !== null && self::changedLabels($record) !== []),

            // Halaman Create: hanya judul dan kategori.
            Section::make('Article details')
                ->columns(2)
                ->visibleOn('create')
                ->schema([
                    self::title()->columnSpanFull(),
                    self::category(),
                ]),

            // Halaman Edit: tulisan di kiri, pengaturan di kanan.
            Grid::make(['default' => 1, 'lg' => 3])
                ->visibleOn('edit')
                ->schema([
                    Group::make([
                        Section::make('Title & excerpt')
                            ->schema([
                                self::title(),
                                self::autosave(
                                    Textarea::make('excerpt')->label('Excerpt')->rows(3)->maxLength(500)
                                        ->helperText('Ringkasan singkat untuk daftar dan hasil pencarian.')
                                ),
                            ]),
                        Section::make('Content')
                            ->description('Gambar di dalam tulisan maksimal 5 MB.')
                            ->schema([
                                // Gambar di dalam teks tersimpan sebagai lampiran editor (disk public), terpisah dari
                                // pipeline media cover. Keterangan (caption) belum didukung editor bawaan.
                                self::autosave(
                                    RichEditor::make('content')
                                        ->label('')
                                        ->fileAttachmentsDisk('public')
                                        ->fileAttachmentsDirectory('articles/inline')
                                        ->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                        ->fileAttachmentsMaxSize(5120)
                                        ->columnSpanFull()
                                ),
                            ]),
                        Section::make('Cover image')
                            ->description('Gambar utama artikel. Klik gambar untuk menentukan titik fokus pemotongan.')
                            ->schema([
                                Livewire::make(ProjectMediaManager::class, fn (?Article $record): array => [
                                    'projectId' => $record->id,
                                    'role' => Article::ROLE_COVER,
                                    'kind' => 'article',
                                ])->key('article-cover-manager'),
                            ]),
                    ])->columnSpan(['lg' => 2]),

                    Group::make([
                        Section::make('Publishing')
                            ->schema([
                                Text::make(fn (?Article $record): string => 'Status: '.($record?->status->getLabel() ?? '—')),
                                Text::make(fn (?Article $record): string => self::publishingLine($record)),
                            ]),
                        Section::make('Organize')
                            ->schema([
                                self::category(),
                                self::autosave(
                                    Select::make('author_id')
                                        ->label('Author')
                                        ->options(fn (): array => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                                        ->searchable(),
                                    debounce: false,
                                ),
                            ]),
                        Section::make('URL')
                            ->schema([
                                self::autosave(
                                    TextInput::make('slug')->label('Slug')->maxLength(255)
                                        ->helperText(fn (?Article $record): string => url('/news').'/'.($record?->draftFields()['slug'] ?? ''))
                                ),
                            ]),
                    ])->columnSpan(['lg' => 1]),
                ]),
        ]);
    }

    private static function title(): TextInput
    {
        return self::autosave(TextInput::make('title')->label('Title')->required()->maxLength(255));
    }

    private static function category(): Select
    {
        return self::autosave(
            Select::make('category_id')
                ->label('Category')
                ->options(fn (): array => Category::query()
                    ->ofType(Article::categoryType())
                    ->orderBy('sort_order')
                    ->pluck('name', 'id')
                    ->all())
                ->required()
                ->searchable()
                ->helperText('Kelola kategori di menu Categories, tab Article Categories.'),
            debounce: false,
        );
    }

    /**
     * Kolom dikirim ke server ~1,5 detik setelah pengguna berhenti mengetik (SRS FR-WF-001).
     * Penyimpanannya dipicu EditArticle::updatedData(); di halaman Create tidak ada pengait itu.
     */
    private static function autosave(TextInput|Select|Textarea|RichEditor $field, bool $debounce = true): TextInput|Select|Textarea|RichEditor
    {
        $key = $field->getName();

        return $field
            ->live(onBlur: false, debounce: $debounce ? 1500 : 0)
            // Kolom yang isinya berbeda dari versi di website diberi penanda "Changed" di atas kolomnya.
            ->hint(fn (?Article $record): ?string => $record !== null && in_array($key, $record->changedFieldKeys(), true) ? 'Changed' : null)
            ->hintColor('warning')
            ->hintIcon(fn (?Article $record): ?string => $record !== null && in_array($key, $record->changedFieldKeys(), true) ? 'heroicon-m-pencil-square' : null);
    }

    /** @return list<string> */
    private static function changedLabels(Article $article): array
    {
        $labels = [
            'title' => 'Title', 'slug' => 'Slug', 'category_id' => 'Category', 'excerpt' => 'Excerpt',
            'content' => 'Content', 'author_id' => 'Author',
        ];

        $changed = array_map(fn (string $key): string => $labels[$key], $article->changedFieldKeys());

        if ($article->hasUnpublishedMediaChanges()) {
            $changed[] = 'Cover';
        }

        return $changed;
    }

    private static function changeSummary(?Article $article): string
    {
        $labels = $article ? self::changedLabels($article) : [];

        return count($labels).' perubahan belum di-publish: '.implode(', ', $labels);
    }

    private static function publishingLine(?Article $article): string
    {
        return match (true) {
            $article === null => '',
            $article->scheduled_at !== null && $article->status->value === 'scheduled' => 'Dijadwalkan: '.$article->scheduled_at->format('d M Y, H:i'),
            $article->published_at !== null => 'Live sejak: '.$article->published_at->format('d M Y, H:i'),
            default => 'Belum pernah dipublikasikan.',
        };
    }
}
