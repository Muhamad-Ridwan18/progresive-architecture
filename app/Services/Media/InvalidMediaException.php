<?php

namespace App\Services\Media;

use RuntimeException;

/** Gambar ditolak (tidak valid, terlalu besar, atau tidak dapat diproses). Pesannya aman ditampilkan ke admin. */
class InvalidMediaException extends RuntimeException {}
