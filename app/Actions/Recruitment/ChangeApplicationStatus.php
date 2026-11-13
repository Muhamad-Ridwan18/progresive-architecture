<?php

namespace App\Actions\Recruitment;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Mengubah status lamaran secara manual dan mencatat riwayatnya (SRS FR-RC-033). */
class ChangeApplicationStatus
{
    /** @return bool true bila status berubah; false bila sama dengan status sekarang. */
    public function handle(JobApplication $application, ApplicationStatus $to, ?User $by): bool
    {
        $from = $application->status;

        if ($from === $to) {
            return false;
        }

        DB::transaction(function () use ($application, $from, $to, $by): void {
            $application->update(['status' => $to]);

            $application->statusChanges()->create([
                'user_id' => $by?->getKey(),
                'from_status' => $from,
                'to_status' => $to,
            ]);

            activity('application')
                ->performedOn($application)
                ->causedBy($by)
                ->event('status_changed')
                ->withProperties(['old' => ['status' => $from->value], 'attributes' => ['status' => $to->value]])
                ->log('Status lamaran diubah');
        });

        return true;
    }
}
