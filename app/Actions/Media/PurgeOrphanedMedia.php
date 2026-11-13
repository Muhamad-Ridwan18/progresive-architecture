<?php

namespace App\Actions\Media;

use App\Models\MediaFile;

/**
 * Garbage collection gambar (SRS 12.3 dan 12.9).
 *
 * Aturan:
 *  - Unggahan yang tidak pernah dipakai: dibuang setelah `temp_retention_hours`.
 *  - Gambar yang pernah dipakai lalu dilepas: dibuang setelah `removed_retention_days` (7 hari).
 *  - Gambar yang masih punya penggunaan tidak pernah dibuang. Referensi dicek ulang tepat
 *    sebelum penghapusan permanen (NFR-REL-001).
 */
class PurgeOrphanedMedia
{
    /** @return array{reconciled:int, purged:int} */
    public function handle(bool $dryRun = false): array
    {
        $reconciled = $this->reconcile($dryRun);
        $purged = 0;

        $candidates = MediaFile::query()
            ->whereNotNull('orphaned_at')
            ->where(function ($query): void {
                $query
                    ->where(fn ($q) => $q->whereNull('committed_at')
                        ->where('orphaned_at', '<=', now()->subHours((int) config('media.temp_retention_hours'))))
                    ->orWhere(fn ($q) => $q->whereNotNull('committed_at')
                        ->where('orphaned_at', '<=', now()->subDays((int) config('media.removed_retention_days'))));
            })
            ->get();

        foreach ($candidates as $file) {
            // Pemeriksaan ulang tepat sebelum hapus: jangan pernah menghapus file yang dipakai.
            if ($file->usages()->exists()) {
                if (! $dryRun) {
                    $file->markCommitted();
                }

                continue;
            }

            if (! $dryRun) {
                $this->purge($file);
            }

            $purged++;
        }

        return ['reconciled' => $reconciled, 'purged' => $purged];
    }

    /**
     * Pengaman: file tanpa penggunaan tetapi belum bertanda orphaned (misalnya konten dihapus
     * tanpa melalui model) dimasukkan ke antrean hapus sekarang.
     */
    private function reconcile(bool $dryRun): int
    {
        $query = MediaFile::query()->whereNull('orphaned_at')->whereDoesntHave('usages');

        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->update(['orphaned_at' => now()]);
        }

        return $count;
    }

    private function purge(MediaFile $file): void
    {
        activity('media')
            ->event('deleted')
            ->withProperties(['old' => [
                'file' => $file->original_name,
                'sha256' => $file->sha256,
                'was_used' => $file->committed_at !== null,
            ]])
            ->log('Media file permanently deleted');

        $file->deletePhysicalFiles();
        $file->delete();
    }
}
