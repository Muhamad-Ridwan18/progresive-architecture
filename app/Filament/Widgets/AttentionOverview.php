<?php

namespace App\Filament\Widgets;

use App\Services\DashboardAttention;
use Filament\Widgets\Widget;

/** Empat kartu "perlu perhatian hari ini"; setiap kartu menuju halaman tempat tindakannya dilakukan. */
class AttentionOverview extends Widget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.attention-overview';

    /** @return list<array{icon: string, color: string, count: int, label: string, url: string}> */
    public function items(): array
    {
        return DashboardAttention::all();
    }
}
