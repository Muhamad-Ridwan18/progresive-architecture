<?php

namespace App\Filament\Pages;

use App\Services\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Website Settings (SRS Bagian 8): satu halaman dengan lima tab.
 *
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'SYSTEM';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Website Settings';

    protected static ?string $title = 'Website Settings';

    protected static ?string $slug = 'settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Settings::all());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Settings')
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make('General')->schema([
                        TextInput::make('site_name')->label('Site Name')->required()->maxLength(120),
                        TextInput::make('tagline')->label('Tagline')->maxLength(200),
                        FileUpload::make('logo')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('settings')
                            ->maxSize(2048),
                        FileUpload::make('favicon')
                            ->label('Favicon')
                            ->image()
                            ->disk('public')
                            ->directory('settings')
                            ->maxSize(512),
                    ]),
                    Tab::make('Company Information')->schema([
                        TextInput::make('company_name')->label('Company Name')->maxLength(200),
                        Textarea::make('company_description')->label('Description')->rows(5),
                        Textarea::make('company_address')->label('Address')->rows(3),
                    ]),
                    Tab::make('Contact')->schema([
                        TextInput::make('contact_email')->label('Email')->email()->maxLength(255),
                        TextInput::make('contact_phone')->label('Phone')->tel()->maxLength(50),
                        Textarea::make('office_hours')->label('Office Hours')->rows(3),
                        Textarea::make('careers_intro')
                            ->label('Careers intro')
                            ->helperText('Teks "Come and join us" di halaman Careers. Paragraf dipisahkan baris kosong.')
                            ->rows(8),
                    ]),
                    Tab::make('Social Media')->schema([
                        TextInput::make('social_instagram')->label('Instagram')->url(),
                        TextInput::make('social_linkedin')->label('LinkedIn')->url(),
                        TextInput::make('social_facebook')->label('Facebook')->url(),
                        Repeater::make('social_other')
                            ->label('Other platforms')
                            ->schema([
                                TextInput::make('platform')->required()->helperText('Logo dikenali dari nama: TikTok, YouTube, X, WhatsApp, Pinterest, Behance. Lainnya memakai ikon tautan.'),
                                TextInput::make('url')->url()->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add platform'),
                    ]),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Save changes')
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                ])->key('form-actions'),
            ]);
    }

    public function save(): void
    {
        $changes = Settings::setMany($this->form->getState());

        if ($changes['new'] !== []) {
            // SRS FR-ST-002 / FR-AL-002: perubahan pengaturan tercatat dengan nilai sebelum dan sesudah.
            activity('settings')
                ->event('updated')
                ->withProperties(['old' => $changes['old'], 'attributes' => $changes['new']])
                ->log('Website settings updated');
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
