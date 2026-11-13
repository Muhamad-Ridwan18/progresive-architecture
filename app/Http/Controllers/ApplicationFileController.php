<?php

namespace App\Http\Controllers;

use App\Models\JobApplicationFile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pratinjau dan unduhan dokumen lamaran (SRS FR-RC-035/036). Berkas ada di disk privat dan
 * hanya dapat dibuka administrator aktif; selain itu dijawab 404 agar keberadaannya tidak bocor.
 */
class ApplicationFileController extends Controller
{
    /** Hanya jenis ini yang boleh dibuka langsung di browser; sisanya selalu diunduh. */
    private const INLINE_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    public function __invoke(Request $request, JobApplicationFile $file): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->is_active, 404);

        $inline = ! $request->boolean('download') && in_array($file->mime_type, self::INLINE_MIME_TYPES, true);

        return response()->file(
            Storage::disk('local')->path($file->path),
            [
                'Content-Type' => $file->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.addslashes($file->original_name).'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
