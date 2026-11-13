<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Services\DashboardAttention;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Dashboard sebagai pusat tindakan yang berfokus pada konten: sapaan, hal yang perlu perhatian hari ini, angka utama,
 * grafik publikasi, proyek dan artikel terbaru, lalu rekrutmen (jarang, jadi di bagian bawah). Urutan widget diatur oleh `$sort` masing-masing widget.
 */
class Dashboard extends BaseDashboard
{
    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 3];
    }

    public function getHeading(): string|Htmlable
    {
        $hour = (int) now()->format('G');
        $greeting = match (true) {
            $hour < 11 => 'Selamat pagi',
            $hour < 15 => 'Selamat siang',
            $hour < 19 => 'Selamat sore',
            default => 'Selamat malam',
        };

        $firstName = explode(' ', trim((string) auth()->user()?->name))[0];

        return $firstName !== '' ? $greeting.', '.$firstName : $greeting;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $pending = count(DashboardAttention::pending());

        $summary = $pending > 0
            ? "Ada {$pending} hal yang perlu perhatian Anda hari ini."
            : 'Semua beres. Tidak ada yang mendesak hari ini.';

        return now()->locale('id')->translatedFormat('l, d F Y').' · '.$summary;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewSite')
                ->label('Lihat website')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(url('/'), shouldOpenInNewTab: true),
            Action::make('newArticle')
                ->label('New article')
                ->icon('heroicon-o-newspaper')
                ->color('gray')
                ->url(ArticleResource::getUrl('create')),
            Action::make('newProject')
                ->label('New project')
                ->icon('heroicon-o-plus')
                ->url(ProjectResource::getUrl('create')),
        ];
    }
}
