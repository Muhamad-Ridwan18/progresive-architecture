<?php

namespace App\Actions\Recruitment;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Services\AdminNotifier;
use App\Support\ApplicationForm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Menyimpan lamaran dari halaman publik: jawaban, salinan struktur formulir, dokumen privat,
 * dan entri pertama riwayat status (SRS FR-FE-085, FR-RC-023, FR-RC-036).
 */
class SubmitApplication
{
    /**
     * @param  array<string, mixed>  $answers  Jawaban tervalidasi, per key field.
     * @param  array<string, UploadedFile>  $files  Berkas tervalidasi, per key field.
     */
    public function handle(JobVacancy $vacancy, array $answers, array $files): JobApplication
    {
        $fields = $this->formFor($vacancy);
        $cleaned = ApplicationForm::cleanAnswers($fields, $answers);
        $identity = ApplicationForm::identity($fields, $cleaned);
        $storedPaths = [];

        try {
            $application = DB::transaction(function () use ($vacancy, $fields, $cleaned, $identity, $files, &$storedPaths): JobApplication {
                $application = JobApplication::create([
                    'job_vacancy_id' => $vacancy->getKey(),
                    'applicant_name' => $identity['name'],
                    'applicant_email' => $identity['email'],
                    'applicant_phone' => $identity['phone'],
                    'status' => ApplicationStatus::Submitted,
                    'answers' => $cleaned,
                    'form_snapshot' => $fields,
                    'submitted_at' => now(),
                ]);

                foreach ($files as $key => $file) {
                    // Nama berkas di disk acak; nama asli hanya disimpan sebagai metadata.
                    $path = $file->store('applications/'.$application->getKey(), 'local');
                    $storedPaths[] = $path;

                    $application->files()->create([
                        'field_key' => $key,
                        'original_name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }

                $application->statusChanges()->create([
                    'user_id' => null,
                    'from_status' => null,
                    'to_status' => ApplicationStatus::Submitted,
                ]);

                activity('application')
                    ->performedOn($application)
                    ->event('submitted')
                    ->withProperties(['vacancy' => $vacancy->title])
                    ->log('Lamaran diterima');

                return $application;
            });
        } catch (\Throwable $e) {
            // Transaksi dibatalkan: berkas yang sempat tersimpan tidak boleh menjadi yatim.
            Storage::disk('local')->delete($storedPaths);

            throw $e;
        }

        // Di luar try: lamaran sudah tersimpan, jadi galat notifikasi tidak boleh menghapus berkasnya.
        app(AdminNotifier::class)->newApplication($application);

        return $application;
    }

    /**
     * Formulir lowongan; lowongan tanpa formulir memakai template awal.
     *
     * @return list<array<string, mixed>>
     */
    public function formFor(JobVacancy $vacancy): array
    {
        $fields = $vacancy->form_fields;

        return filled($fields) ? ApplicationForm::normalize($fields) : ApplicationForm::defaultFields();
    }
}
