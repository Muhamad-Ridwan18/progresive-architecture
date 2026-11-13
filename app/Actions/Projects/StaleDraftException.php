<?php

namespace App\Actions\Projects;

use RuntimeException;

/** Draft sudah diubah oleh sesi lain sejak editor ini memuatnya. Dipakai bersama oleh semua konten berdraft. */
class StaleDraftException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Konten ini sudah diubah di tab atau oleh administrator lain. Muat ulang halaman agar tidak saling menimpa.');
    }
}
