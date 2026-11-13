<?php

namespace App\Filament\Resources\JobVacancies\Schemas;

use App\Enums\JobStatus;
use App\Filament\Resources\FormFields\Schemas\FormFieldChecklist;
use App\Models\JobCategory;
use App\Models\JobVacancy;
use App\Services\VacancyFormComposer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class JobVacancyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Vacancy')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Position')
                            ->required()
                            ->maxLength(190)
                            ->columnSpanFull(),
                        Select::make('job_category_id')
                            ->label('Category')
                            ->options(fn (): array => JobCategory::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                            ->required()
                            ->live()
                            // Saat dibuat, centang isian diwariskan dari pilihan bawaan kategori (SRS FR-RC-011).
                            ->afterStateUpdated(function (?string $state, Set $set, string $operation): void {
                                if ($operation === 'create' && filled($state)) {
                                    $selection = app(VacancyFormComposer::class)->selectionFromCategory(JobCategory::query()->find($state));
                                    $set('field_keys', $selection['keys']);
                                    $set('required_keys', $selection['required']);
                                }
                            }),
                        TextInput::make('department')
                            ->label('Department')
                            ->maxLength(120),
                        TextInput::make('location')
                            ->label('Location')
                            ->required()
                            ->maxLength(120),
                        Select::make('employment_type')
                            ->label('Employment type')
                            ->options(JobVacancy::EMPLOYMENT_TYPES)
                            ->required(),
                        DatePicker::make('deadline')
                            ->label('Application deadline')
                            ->helperText('Lamaran ditolak setelah tanggal ini. Kosongkan bila tanpa batas.'),
                        Select::make('status')
                            ->label('Status')
                            ->options(JobStatus::class)
                            ->default(JobStatus::Draft->value)
                            ->required()
                            ->helperText('Draft tidak tampil di website. Closed tampil tetapi tidak menerima lamaran.'),
                    ]),
                Section::make('Details')
                    ->schema([
                        Textarea::make('description')->label('Job description')->rows(6),
                        Textarea::make('qualifications')->label('Qualifications')->rows(6),
                        Textarea::make('required_documents')
                            ->label('Required documents')
                            ->helperText('Tampil di halaman lowongan. Field unggah yang sebenarnya diatur pada formulir di bawah.')
                            ->rows(3),
                    ]),
                Section::make('Application form')
                    ->description('Centang isian yang harus diisi pelamar untuk posisi ini. Pilihan awal mengikuti kategori; perubahan di sini hanya berlaku untuk lowongan ini. Lamaran yang sudah masuk menyimpan salinan formulirnya sendiri.')
                    ->schema(FormFieldChecklist::make('field_keys', 'required_keys')),
            ]);
    }
}
