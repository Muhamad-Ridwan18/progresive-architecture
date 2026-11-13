<?php

namespace App\Filament\Resources\JobApplications\Schemas;

use App\Models\JobApplication;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Detail pelamar: tahap lamaran selalu terlihat di atas (SRS FR-RC-035), lalu tab Overview (jawaban + ringkasan),
 * Documents (dibaca langsung di halaman, tanpa mengunduh), dan Activity (riwayat status dan catatan).
 */
class JobApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        ViewEntry::make('pipeline')->label('')->view('filament.job-applications.pipeline'),
                    ]),
                Tabs::make('application')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Overview')
                            ->schema([
                                Grid::make(['default' => 1, 'lg' => 3])->schema([
                                    Group::make([
                                        Section::make('Application answers')
                                            ->description('Sesuai formulir saat lamaran dikirim, bukan formulir lowongan yang sekarang.')
                                            ->schema([
                                                ViewEntry::make('answers')->label('')->view('filament.job-applications.answers'),
                                            ]),
                                    ])->columnSpan(['lg' => 2]),
                                    Group::make([
                                        Section::make('Contact')
                                            ->schema([
                                                TextEntry::make('status')->badge(),
                                                TextEntry::make('applicant_email')->label('Email')->icon('heroicon-o-envelope')->copyable(),
                                                TextEntry::make('applicant_phone')->label('Phone')->icon('heroicon-o-phone')->placeholder('—'),
                                                TextEntry::make('submitted_at')->label('Applied')->icon('heroicon-o-calendar')->dateTime('d M Y, H:i'),
                                                TextEntry::make('reference_number')->label('Reference')->copyable(),
                                            ]),
                                        Section::make('Position')
                                            ->schema([
                                                TextEntry::make('vacancy.title')->label('Position applied'),
                                                TextEntry::make('vacancy.category.name')->label('Category')->placeholder('—'),
                                                TextEntry::make('vacancy.location')->label('Location')->placeholder('—'),
                                                TextEntry::make('vacancy.employment_type')->label('Type')->placeholder('—'),
                                            ]),
                                        Section::make('Latest note')
                                            ->schema([
                                                TextEntry::make('latest_note')
                                                    ->label('')
                                                    ->state(fn (JobApplication $record): ?string => $record->notes()->first()?->body)
                                                    ->placeholder('Belum ada catatan.')
                                                    ->limit(240),
                                            ]),
                                    ])->columnSpan(['lg' => 1]),
                                ]),
                            ]),
                        Tab::make('Documents')
                            ->badge(fn (JobApplication $record): int => $record->files()->count())
                            ->schema([
                                ViewEntry::make('documents')->label('')->view('filament.job-applications.documents'),
                            ]),
                        Tab::make('Activity')
                            ->schema([
                                ViewEntry::make('history')->label('')->view('filament.job-applications.history'),
                            ]),
                    ]),
            ]);
    }
}
