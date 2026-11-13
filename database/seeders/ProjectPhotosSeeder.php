<?php

namespace Database\Seeders;

use App\Actions\Media\AttachMedia;
use App\Actions\Projects\PublishProject;
use App\Models\Project;
use Database\Seeders\Concerns\SeedsDemoMedia;
use Illuminate\Database\Seeder;

/**
 * Memasang foto proyek dari `foto/progressive/{folder proyek}/` ke proyek yang sudah ada dan belum punya cover,
 * lalu mempublikasikannya. Folder dicocokkan dari slug proyek (`AD HOUSE` untuk `ad-house`; `FR WEB` untuk
 * `fr-house`; `FURNITURE BV HOUSE` untuk `bv-house`). Foto besar diperkecil ke 2560 px.
 *
 *   php artisan db:seed --class=ProjectPhotosSeeder
 *
 * Aman diulang: proyek yang sudah punya cover dilewati, dan folder yang tidak ada diabaikan. Cover pertama adalah
 * foto eksterior (nama berisi EKSTERIOR) bila ada; sisanya menjadi galeri berurutan.
 */
class ProjectPhotosSeeder extends Seeder
{
    use SeedsDemoMedia;

    public function run(): void
    {
        if (! is_dir($this->photoRoot())) {
            $this->command?->warn('Folder foto/progressive belum ada. Taruh foto per proyek di sana, lalu jalankan lagi.');

            return;
        }

        $updated = 0;

        foreach (Project::query()->orderBy('id')->get() as $project) {
            if ($project->cover(draft: true) !== null) {
                continue;
            }

            $photos = $this->projectPhotos($project->slug);

            if ($photos === []) {
                continue;
            }

            $name = $project->draftFields()['name'];

            foreach ($photos as $n => $file) {
                app(AttachMedia::class)->handle($file, $project, $n === 0 ? Project::ROLE_COVER : Project::ROLE_GALLERY, [
                    'alt_text' => $n === 0 ? "Tampak utama {$name}" : "{$name}, foto {$n}",
                    'sort_order' => $n,
                ]);
            }

            app(PublishProject::class)->handle($project->fresh(), auth()->id());
            $this->command?->info("{$name}: ".count($photos).' foto dipasang.');
            $updated++;
        }

        $this->command?->info("{$updated} proyek diperbarui dengan foto.");
    }
}
