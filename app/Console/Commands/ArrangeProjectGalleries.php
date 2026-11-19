<?php

namespace App\Console\Commands;

use App\Actions\Projects\PublishProject;
use App\Actions\Projects\SaveProjectDraft;
use App\Models\MediaUsage;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Menyusun ulang galeri proyek menurut ketentuan grup foto: foto galeri dikelompokkan 2 sampai 3 foto (foto berurutan
 * yang jenisnya sama, eksterior atau interior dari nama berkas asli, tidak dicampur), dan narasi proyek dipecah:
 * paragraf pertama tetap di header, paragraf berikutnya menjadi caption grup berurutan (grup 1, 2, 3, ...). Tidak ada
 * teks yang dikarang dan tidak ada yang hilang; paragraf yang lebih banyak dari grup digabung ke caption grup terakhir.
 *
 * Proyek yang sudah tersusun (ada foto yang digabung) dilewati kecuali dengan `--force`, yang menyusun ulang dengan
 * menyatukan kembali narasi (deskripsi + caption grup yang ada). Proyek berslug "testing*" dan yang tanpa foto galeri
 * dilewati. `--dry-run` hanya menampilkan rencana; `--publish` langsung mempublikasikan hasilnya.
 */
class ArrangeProjectGalleries extends Command
{
    protected $signature = 'projects:arrange-galleries
        {--dry-run : Tampilkan rencana tanpa mengubah apa pun}
        {--publish : Publikasikan proyek setelah disusun}
        {--force : Susun ulang proyek yang sudah tersusun}';

    protected $description = 'Susun galeri semua proyek menjadi grup 2-3 foto dan pecah narasi menjadi caption grup';

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

                $alreadyArranged = $photos->contains(fn (MediaUsage $u): bool => $u->join_previous);

                if ($alreadyArranged && ! $this->option('force')) {
                    $this->line("- {$project->slug}: sudah tersusun, dilewati (gunakan --force untuk menyusun ulang).");

                    return;
                }

                $paragraphs = $this->narrative($project, $photos, $alreadyArranged);
                $plan = $this->plan($photos, $paragraphs);

                $this->line("- {$project->slug}: {$photos->count()} foto menjadi {$plan->count()} grup ("
                    .$plan->map(fn (array $group): int => $group['photos']->count())->implode(' + ')
                    .'); '.max(0, count($paragraphs) - 1).' paragraf narasi menjadi caption.');

                foreach ($plan as $number => $group) {
                    $preview = $group['caption'] === null ? '(tanpa caption)' : Str::limit(str_replace("\n", ' ', $group['caption']), 70);
                    $this->line('    '.($number + 1).'. ['.$group['photos']->count().' foto] '.$preview);
                }

                if ($dryRun) {
                    return;
                }

                $this->apply($project, $plan, $paragraphs[0] ?? null);
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
     * Narasi proyek sebagai daftar paragraf. Pada penyusunan ulang, caption grup yang ada disatukan kembali di belakang
     * deskripsi supaya tidak hilang.
     *
     * @param  Collection<int, MediaUsage>  $photos
     * @return list<string>
     */
    private function narrative(Project $project, Collection $photos, bool $alreadyArranged): array
    {
        $description = (string) ($project->draftFields()['description'] ?? '');

        $paragraphs = collect(preg_split('/\R{2,}/', trim($description)) ?: [])->map(fn (string $p): string => trim($p));

        if ($alreadyArranged) {
            $existing = $photos
                ->filter(fn (MediaUsage $u): bool => filled($u->note) && blank($u->note_by))
                ->flatMap(fn (MediaUsage $u): array => preg_split('/\R{2,}/', trim((string) $u->note)) ?: [])
                ->map(fn (string $p): string => trim($p));

            $paragraphs = $paragraphs->concat($existing);
        }

        return $paragraphs->filter()->values()->all();
    }

    /**
     * @param  Collection<int, MediaUsage>  $photos
     * @param  list<string>  $paragraphs
     * @return Collection<int, array{caption: ?string, photos: Collection<int, MediaUsage>}>
     */
    private function plan(Collection $photos, array $paragraphs): Collection
    {
        // Foto berurutan dengan jenis sama menjadi satu rangkaian (run); jenis berbeda tidak dicampur dalam satu grup.
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
            foreach ($this->split($run['photos']) as $chunk) {
                $groups->push(['caption' => null, 'photos' => $chunk]);
            }
        }

        // Paragraf ke-2 dan seterusnya menjadi caption grup berurutan; kelebihannya digabung ke grup terakhir.
        $captions = array_slice($paragraphs, 1);

        foreach ($groups as $index => $group) {
            if (! isset($captions[$index])) {
                break;
            }

            $text = $captions[$index];

            if ($index === $groups->count() - 1) {
                $text = implode("\n\n", array_slice($captions, $index));
            }

            $groups[$index] = ['caption' => $text, 'photos' => $group['photos']];
        }

        return $groups;
    }

    /** Jenis foto dari nama berkas aslinya, mis. "AD HOUSE - INTERIOR 13.jpg" (null bila tidak terbaca). */
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

    /** @param  Collection<int, array{caption: ?string, photos: Collection<int, MediaUsage>}>  $plan */
    private function apply(Project $project, Collection $plan, ?string $headerParagraph): void
    {
        DB::transaction(function () use ($project, $plan, $headerParagraph): void {
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

            // Header hanya memuat paragraf pertama; sisanya sudah menjadi caption grup di atas.
            if ($headerParagraph !== null && $plan->contains(fn (array $g): bool => $g['caption'] !== null)) {
                app(SaveProjectDraft::class)->handle($project->refresh(), ['description' => $headerParagraph], $project->draft_version);
            }
        });
    }
}
