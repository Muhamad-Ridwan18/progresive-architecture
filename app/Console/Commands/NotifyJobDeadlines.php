<?php

namespace App\Console\Commands;

use App\Enums\JobStatus;
use App\Models\JobVacancy;
use App\Services\AdminNotifier;
use Illuminate\Console\Command;

/**
 * Pengingat batas waktu lowongan (SRS FR-NT-006): notifikasi pada H-3 dan hari-H untuk lowongan Published.
 * Dijadwalkan setiap hari (routes/console.php). Setiap tahap hanya dikirim sekali per lowongan; bila
 * deadline diundur, penanda direset sehingga pengingat dikirim lagi pada tanggal yang baru.
 */
class NotifyJobDeadlines extends Command
{
    /** Tahap pengingat: sisa hari => kolom penanda. */
    private const STAGES = [3 => 'deadline_h3_notified_at', 0 => 'deadline_h0_notified_at'];

    protected $signature = 'jobs:notify-deadlines';

    protected $description = 'Kirim notifikasi dashboard untuk lowongan yang deadline-nya H-3 dan hari-H';

    public function handle(AdminNotifier $notifier): int
    {
        $sent = 0;

        foreach (self::STAGES as $daysLeft => $column) {
            JobVacancy::query()
                ->where('status', JobStatus::Published->value)
                ->whereDate('deadline', today()->addDays($daysLeft))
                ->whereNull($column)
                ->each(function (JobVacancy $vacancy) use ($notifier, $daysLeft, $column, &$sent): void {
                    $notifier->jobDeadline($vacancy, $daysLeft);
                    $vacancy->forceFill([$column => now()])->saveQuietly();
                    $sent++;
                });
        }

        $this->info("{$sent} pengingat deadline dikirim.");

        return self::SUCCESS;
    }
}
