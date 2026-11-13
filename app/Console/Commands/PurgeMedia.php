<?php

namespace App\Console\Commands;

use App\Actions\Media\PurgeOrphanedMedia;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:purge {--dry-run : Hanya tampilkan jumlahnya, tanpa menghapus}')]
#[Description('Hapus permanen gambar tanpa referensi yang masa retensinya sudah lewat')]
class PurgeMedia extends Command
{
    public function handle(PurgeOrphanedMedia $purge): int
    {
        $dry = (bool) $this->option('dry-run');
        $result = $purge->handle($dry);

        $this->info(($dry ? '[dry-run] ' : '')."Antrean hapus diperbarui: {$result['reconciled']}, dihapus permanen: {$result['purged']}.");

        return self::SUCCESS;
    }
}
