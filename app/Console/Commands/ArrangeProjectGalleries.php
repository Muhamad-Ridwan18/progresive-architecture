<?php

namespace App\Console\Commands;

use App\Actions\Projects\PublishProject;
use App\Models\MediaUsage;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menyusun ulang galeri proyek menurut ketentuan grup foto: foto berurutan dengan jenis yang sama (eksterior atau interior,
 * dibaca dari nama berkas aslinya) dikelompokkan 2 sampai 3 foto, dan tiap grup mendapat satu caption di foto pertamanya.
 *
 * Aman diulang: grup dan caption dibuat ulang dari nol setiap kali dijalankan (`--dry-run` hanya menampilkan rencananya).
 * Proyek yang slug-nya diawali "testing" dilewati. Dengan `--publish`, hasilnya langsung dipublikasikan.
 */
class ArrangeProjectGalleries extends Command
{
    protected $signature = 'projects:arrange-galleries {--dry-run : Tampilkan rencana tanpa mengubah apa pun} {--publish : Publikasikan proyek setelah disusun}';

    protected $description = 'Susun galeri semua proyek menjadi grup 2-3 foto dengan satu caption per grup';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $arranged = 0;

        Project::query()
            ->where('slug', 'not like', 'testing%')
            ->orderBy('id')
            ->each(function (Project $project) use ($dryRun, &$arranged): void {
                $photos = $project->usages(draft: true, role: Project::ROLE_GALLERY);

                if ($photos->isEmpty()) {
                    $this->line("- {$project->slug}: tidak ada foto galeri, dilewati.");

                    return;
                }

                $plan = $this->plan($project, $photos);

                $this->line("- {$project->slug}: {$photos->count()} foto menjadi {$plan->count()} grup ("
                    .$plan->map(fn (array $group): int => $group['photos']->count())->implode(' + ').').');

                foreach ($plan as $number => $group) {
                    $this->line('    '.($number + 1).'. '.$group['caption'].' ['.$group['photos']->count().' foto]');
                }

                if ($dryRun) {
                    return;
                }

                $this->apply($plan);
                $project->refresh()->syncStatus();

                if ($this->option('publish')) {
                    try {
                        app(PublishProject::class)->handle($project->refresh());
                        $this->line('    dipublikasikan.');
                    } catch (ValidationException $e) {
                        $this->warn('    tidak dapat dipublikasikan: '.collect($e->errors())->flatten()->first());
                    }
                }

                $arranged++;
            });

        $this->info($dryRun ? 'Dry run selesai; tidak ada yang diubah.' : "{$arranged} proyek disusun.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, MediaUsage>  $photos
     * @return Collection<int, array{caption: string, photos: Collection<int, MediaUsage>}>
     */
    private function plan(Project $project, Collection $photos): Collection
    {
        $name = $project->draftFields()['name'] ?? $project->name;

        // Foto berurutan dengan jenis sama menjadi satu rangkaian (run).
        $runs = [];

        foreach ($photos as $photo) {
            $kind = $this->kind($photo->file->original_name);
            $last = array_key_last($runs);

            if ($last !== null && $runs[$last]['kind'] === $kind) {
                $runs[$last]['photos']->push($photo);
            } else {
                $runs[] = ['kind' => $kind, 'photos' => collect([$photo])];
            }
        }

        $groups = collect();

        foreach ($runs as $run) {
            $label = $run['kind'] === null ? $name : $name.' — '.$run['kind'];

            foreach ($this->split($run['photos']) as $chunk) {
                $groups->push(['caption' => $label, 'photos' => $chunk]);
            }
        }

        return $groups;
    }

    /** Jenis foto dari nama berkas aslinya, mis. "AD HOUSE - INTERIOR 13.jpg". */
    private function kind(string $originalName): ?string
    {
        return match (true) {
            (bool) preg_match('/ek?steri?or|exterior/i', $originalName) => 'Exterior',
            (bool) preg_match('/interior/i', $originalName) => 'Interior',
            default => null,
        };
    }

    /**
     * Bagi rangkaian foto menjadi grup 3 foto; sisa 1 foto diatur agar tidak ada foto sendirian (mis. 4 = 2 + 2, 7 = 3 + 2 + 2).
     *
     * @param  Collection<int, MediaUsage>  $photos
     * @return list<Collection<int, MediaUsage>>
     */
    private function split(Collection $photos): array
    {
        $total = $photos->count();

        if ($total <= 3) {
            return [$photos];
        }

        $sizes = [];
        $left = $total;

        while ($left > 0) {
            if ($left === 4) {
                array_push($sizes, 2, 2);
                break;
            }

            $take = $left === 2 ? 2 : min(3, $left);
            $sizes[] = $take;
            $left -= $take;
        }

        $chunks = [];
        $offset = 0;

        foreach ($sizes as $size) {
            $chunks[] = $photos->slice($offset, $size)->values();
            $offset += $size;
        }

        return $chunks;
    }

    /** @param  Collection<int, array{caption: string, photos: Collection<int, MediaUsage>}>  $plan */
    private function apply(Collection $plan): void
    {
        DB::transaction(function () use ($plan): void {
            foreach ($plan as $group) {
                foreach ($group['photos'] as $index => $photo) {
                    $photo->update([
                        'join_previous' => $index > 0,
                        'note' => $index === 0 ? $group['caption'] : null,
                        'note_by' => null,
                        'caption' => null,
                    ]);
                }
            }
        });
    }
}
