<?php

namespace Database\Seeders;

use App\Actions\Recruitment\ChangeApplicationStatus;
use App\Enums\ApplicationStatus;
use App\Enums\FormFieldType;
use App\Enums\JobStatus;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Models\JobVacancy;
use App\Models\User;
use App\Services\VacancyFormComposer;
use App\Support\ApplicationForm;
use Database\Seeders\Concerns\SeedsDemoMedia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Rekrutmen dari company profile (SRS 3.7, 6): 8 posisi di Bandung (Architect, Junior Architect, Architectural
 * Intern, Drafter, Interior Designer, Interior Designer Intern, Supervisor, Estimator) dengan teks "Come and join
 * us" dari deck. Deck tidak memuat kualifikasi, jadi dibiarkan kosong untuk diisi tim. Lamaran di bawah adalah
 * DATA CONTOH (nama fiktif, email example.com) agar halaman Applicants terlihat hidup. Dilewati bila sudah ada
 * lowongan.
 */
class DemoCareersSeeder extends Seeder
{
    use SeedsDemoMedia;

    private const INTRO = "At Progressive Architect, we believe that great architecture is created through continuous learning, collaboration, and exploration.\n\nBased in Bandung, Indonesia, our office is located at Suwarna Mas Residence No. 15, Kabupaten Bandung. Since our establishment, we have successfully delivered more than 110 architectural design projects, 40 residential and healthcare construction projects, and over 15 interior design projects.\n\nWe are looking for passionate, motivated, and talented individuals who are eager to grow, contribute, and shape meaningful spaces through architecture and design.";

    /** [judul, kategori, jenis kerja, jumlah lamaran contoh] */
    private const VACANCIES = [
        ['Architect', 'Architecture', 'Full-time', 3],
        ['Junior Architect', 'Architecture', 'Full-time', 3],
        ['Architectural Intern', 'Architecture', 'Magang', 2],
        ['Drafter', 'Architecture', 'Full-time', 2],
        ['Interior Designer', 'Interior', 'Full-time', 2],
        ['Interior Designer Intern', 'Interior', 'Magang', 1],
        ['Supervisor', 'Construction', 'Full-time', 2],
        ['Estimator', 'Construction', 'Full-time', 1],
    ];

    /** Nama fiktif untuk lamaran contoh. */
    private const NAMES = [
        'Andi Prasetyo', 'Sinta Rahayu', 'Budi Hartono', 'Rina Wulandari', 'Dimas Aditya', 'Maya Sari', 'Fajar Nugroho', 'Lia Kartika',
        'Yoga Permana', 'Nadia Putri', 'Hendra Wijaya', 'Citra Lestari', 'Agus Setiawan', 'Putri Ayu', 'Rizky Maulana', 'Tari Anggraeni',
    ];

    public function run(): void
    {
        if (JobVacancy::query()->exists()) {
            $this->command?->warn('Sudah ada lowongan. DemoCareersSeeder dilewati agar konten yang ada tidak tercampur.');

            return;
        }

        $categories = $this->categories();
        $admin = auth()->user() ?? User::query()->first();
        $nameIndex = 0;
        $applications = 0;

        foreach (self::VACANCIES as $v => [$title, $categoryName, $type, $count]) {
            $category = $categories[$categoryName];

            $form = app(VacancyFormComposer::class)->compose($category->default_field_keys, $category->default_required_keys);

            $vacancy = JobVacancy::create([
                'title' => $title,
                'job_category_id' => $category->id,
                'department' => $categoryName,
                'location' => 'Bandung',
                'employment_type' => $type,
                'description' => self::INTRO,
                'qualifications' => null,
                // Daftar dokumen di halaman lowongan mengikuti isian unggah yang wajib pada formulirnya.
                'required_documents' => collect($form)->where('type', 'file')->where('required', true)->pluck('label')->implode("\n"),
                'deadline' => now()->addDays(30 + $v * 3)->toDateString(),
                'status' => JobStatus::Published,
                'form_fields' => $form,
                'published_at' => now()->subDays(20 - $v),
            ]);

            // `for`, bukan range(1, 0): range menghitung mundur dan menghasilkan dua elemen.
            for ($n = 1; $n <= $count; $n++) {
                $this->application($vacancy, self::NAMES[$nameIndex % count(self::NAMES)], $nameIndex, $admin);
                $nameIndex++;
                $applications++;
            }
        }

        $this->command?->info(count(self::VACANCIES).' lowongan dan '.$applications.' lamaran contoh dibuat.');
    }

    /** @return array<string, JobCategory> */
    private function categories(): array
    {
        // Pilihan awal isian per kategori, dicentang dari pustaka formulir (menu Form Builder).
        $design = ['cv', 'portfolio', 'cover_letter', 'edu', 'major', 'school', 'software', 'portfolio_link'];

        return [
            'Architecture' => JobCategory::create(['name' => 'Architecture', 'default_field_keys' => $design, 'default_required_keys' => ['cv', 'portfolio', 'edu']]),
            'Interior' => JobCategory::create(['name' => 'Interior', 'default_field_keys' => $design, 'default_required_keys' => ['cv', 'portfolio', 'edu']]),
            'Construction' => JobCategory::create([
                'name' => 'Construction',
                'default_field_keys' => ['cv', 'edu', 'exp', 'years_experience', 'expected_salary'],
                'default_required_keys' => ['cv', 'edu'],
            ]),
        ];
    }

    private function application(JobVacancy $vacancy, string $name, int $seed, ?User $admin): void
    {
        $fields = ApplicationForm::normalize($vacancy->form_fields);
        $email = str($name)->lower()->slug('.')->toString().'@example.com';
        $daysAgo = 2 + ($seed * 3) % 25;

        $answers = ['edu' => ['S1', 'D3', 'S1', 'S2'][$seed % 4], 'major' => 'Arsitektur', 'school' => 'Institut Teknologi Nasional', 'software' => ['AutoCAD', 'SketchUp'], 'years_experience' => 1 + $seed % 5, 'expected_salary' => 4000000 + ($seed % 5) * 500000, 'exp' => [['PT Contoh Karya', 'Staf', (string) (2021 + $seed % 4)]], 'full_name' => $name, 'email' => $email, 'phone' => '0812-'.sprintf('%04d-%04d', 1000 + $seed * 37, 2000 + $seed * 91), 'cover_letter' => 'Saya tertarik dengan posisi ini dan siap bergabung bila diterima.'];

        $application = $this->atDaysAgo($daysAgo, function () use ($vacancy, $name, $email, $answers, $fields): JobApplication {
            $application = JobApplication::create([
                'job_vacancy_id' => $vacancy->id,
                'applicant_name' => $name,
                'applicant_email' => $email,
                'applicant_phone' => $answers['phone'],
                'status' => ApplicationStatus::Submitted,
                'answers' => $answers,
                'form_snapshot' => $fields,
                'submitted_at' => now(),
            ]);

            $application->statusChanges()->create(['user_id' => null, 'from_status' => null, 'to_status' => ApplicationStatus::Submitted]);

            collect($fields)->where('type', FormFieldType::File->value)->each(function (array $f) use ($application, $name): void {
                $path = "applications/{$application->id}/{$f['key']}.pdf";
                $pdf = $this->pdf("{$f['label']} - {$name}");
                Storage::disk('local')->put($path, $pdf);

                $application->files()->create([
                    'field_key' => $f['key'],
                    'original_name' => str($f['label'])->before(' (')->slug().'-'.str($name)->slug().'.pdf',
                    'path' => $path,
                    'mime_type' => 'application/pdf',
                    'size' => strlen($pdf),
                ]);
            });

            return $application;
        });

        $flow = [ApplicationStatus::Reviewing, ApplicationStatus::Shortlisted, ApplicationStatus::Interview, ApplicationStatus::Accepted];
        $steps = $seed % 6;

        foreach (array_slice($flow, 0, min($steps, 4)) as $k => $status) {
            $this->atDaysAgo(max(0, $daysAgo - 1 - $k), fn () => app(ChangeApplicationStatus::class)->handle($application->fresh(), $status, $admin));
        }

        if ($seed % 7 === 3) {
            app(ChangeApplicationStatus::class)->handle($application->fresh(), ApplicationStatus::Rejected, $admin);
        }

        if ($steps >= 2) {
            $application->notes()->create(['user_id' => $admin?->id, 'body' => 'Portofolio dan pengalaman sesuai. Jadwalkan wawancara.']);
        }
    }

    /** PDF satu halaman yang valid dan sangat kecil, cukup untuk uji pratinjau dan unduhan. */
    private function pdf(string $text): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = "BT /F1 18 Tf 72 720 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $out = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $body) {
            $offsets[] = strlen($out);
            $out .= ($i + 1)." 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($out);
        $out .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $out .= sprintf("%010d 00000 n \n", $offset);
        }

        return $out."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
