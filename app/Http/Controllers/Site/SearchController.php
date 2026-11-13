<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\SiteSearch;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /** Hasil per kelompok pada overlay live. */
    private const LIVE_LIMIT = 4;

    /** Hasil per kelompok pada halaman lengkap. */
    private const PAGE_LIMIT = 30;

    private const LABELS = ['projects' => 'Projects', 'articles' => 'News & Articles', 'careers' => 'Careers'];

    /** Halaman hasil lengkap dengan filter jenis konten (SRS FR-FE-052). Juga berfungsi tanpa JavaScript. */
    public function index(Request $request, SiteSearch $search)
    {
        $q = trim((string) $request->query('q', ''));
        $type = in_array($request->query('type'), SiteSearch::TYPES, true) ? $request->query('type') : null;

        $groups = $search->search($q, self::PAGE_LIMIT, $type ? [$type] : null);

        return response()
            ->view('site.search.index', [
                'q' => $q,
                'type' => $type,
                'groups' => $groups,
                'labels' => self::LABELS,
                'tooShort' => $q !== '' && mb_strlen($q) < SiteSearch::MIN_LENGTH,
                'total' => collect($groups)->sum(fn ($items) => $items->count()),
            ])
            // Halaman hasil pencarian tidak perlu diindeks mesin pencari.
            ->header('X-Robots-Tag', 'noindex, follow');
    }

    /** Fragmen HTML untuk overlay live search (SRS FR-FE-050/051). */
    public function live(Request $request, SiteSearch $search)
    {
        $q = trim((string) $request->query('q', ''));
        $groups = $search->search($q, self::LIVE_LIMIT);

        return response()
            ->view('site.search._results', [
                'q' => $q,
                'groups' => $groups,
                'labels' => self::LABELS,
                'total' => collect($groups)->sum(fn ($items) => $items->count()),
                'tooShort' => mb_strlen($q) < SiteSearch::MIN_LENGTH,
                'compact' => true,
            ])
            ->header('Cache-Control', 'no-store');
    }
}
