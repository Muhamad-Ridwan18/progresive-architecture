<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pembersihan gambar tanpa referensi (SRS 12.3 dan 12.9). Pada produksi, scheduler dijalankan cron:
//   * * * * * cd /path/to/app && php artisan schedule:run
Schedule::command('media:purge')->dailyAt('03:00')->withoutOverlapping();

// Penerbitan artikel terjadwal (SRS FR-WF-012). Memakai scheduler yang sama dengan pembersihan media di atas.
Schedule::command('articles:publish-scheduled')->everyMinute()->withoutOverlapping();

// Pengingat deadline lowongan H-3 dan hari-H (SRS FR-NT-006). Setiap pagi.
Schedule::command('jobs:notify-deadlines')->dailyAt('07:00')->withoutOverlapping();
