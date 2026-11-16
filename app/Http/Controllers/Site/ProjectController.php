<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use App\Support\GridPattern;
use App\Support\PublicProject;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    /** Batas halaman yang dapat dimuat sekaligus saat memulihkan posisi scroll. */
    private const MAX_RESTORE_PAGES = 10;

    /**
     * Daftar proyek (SRS 3.2). Mendukung tiga bentuk permintaan dengan satu logika:
     *  - halaman penuh (juga menjadi fallback pagination tanpa JavaScript, lewat ?page=)
     *  - fragmen HTML untuk infinite scroll dan pergantian kategori (permintaan AJAX)
     *  - ?upto=N: N halaman sekaligus, untuk memulihkan posisi scroll setelah kembali dari detail
     */
    public function index(Request $request)
    {
        $perPage = GridPattern::PROJECTS_PER_PAGE;

        $categories = Category::query()
            ->ofType(Project::categoryType())
            ->whereHas('projects', fn ($q) => $q->live())
            ->orderBy('sort_order')
            ->get();

        $active = $categories->firstWhere('slug', $request->query('category'));

        $page = max(1, (int) $request->query('page', 1));
        $upto = min(self::MAX_RESTORE_PAGES, max(0, (int) $request->query('upto', 0)));

        $offset = $upto > 0 ? 0 : ($page - 1) * $perPage;
        $limit = $upto > 0 ? $perPage * $upto : $perPage;

        $base = Project::query()
            ->live()
            ->when($active, fn ($q) => $q->where('category_id', $active->id));

        $items = (clone $base)
            ->with(['category', 'publishedCoverUsage.file'])
            ->inSiteOrder()
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(fn (Project $p) => PublicProject::published($p));

        // Sisa proyek setelah halaman ini. Jumlah batch berikutnya dikirim ke klien agar kerangka
        // pemuatan (placeholder) sama tingginya dengan konten asli, termasuk batch terakhir yang
        // lebih pendek. Tanpa itu footer melonjak saat kerangka digantikan (layout shift).
        $remaining = max(0, (clone $base)->count() - ($offset + $limit));
        $nextPage = ($upto > 0 ? $upto : $page) + 1;

        $data = [
            'items' => $items,
            'offset' => $offset,
            'nextUrl' => $remaining > 0 ? $this->listUrl($active, ['page' => $nextPage]) : null,
            'nextCount' => min($perPage, $remaining),
            'prevUrl' => ($upto === 0 && $page > 1) ? $this->listUrl($active, $page > 2 ? ['page' => $page - 1] : []) : null,
            'loadedPages' => $upto > 0 ? $upto : 1,
        ];

        if ($request->ajax()) {
            return response()->view('site.projects._items', $data)->header('Vary', 'X-Requested-With');
        }

        return response()
            ->view('site.projects.index', $data + ['categories' => $categories, 'active' => $active])
            ->header('Vary', 'X-Requested-With');
    }

    /** Detail proyek (SRS 3.3). Hanya versi published; draft dan Archived tidak ditemukan. */
    public function show(string $slug)
    {
        $project = Project::query()
            ->live()
            ->with(['category', 'publishedCoverUsage.file', 'publishedGalleryUsages.file'])
            ->where('slug', $slug)
            ->firstOrFail();

        return view('site.projects.show', [
            'project' => PublicProject::published($project),
            'next' => $this->neighbour($project, older: true),
            'previous' => $this->neighbour($project, older: false),
            'preview' => false,
        ]);
    }

    /**
     * Panel proyek yang dibuka di tempat pada daftar (tanpa pindah halaman): info, data teknis, dan foto yang
     * digulir ke samping. Fragmen HTML tanpa layout; hanya versi published (draft dan Archived tidak ditemukan).
     */
    public function panel(string $slug)
    {
        $project = Project::query()
            ->live()
            ->with(['category', 'publishedCoverUsage.file', 'publishedGalleryUsages.file'])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->view('site.projects._panel', ['project' => PublicProject::published($project)]);
    }

    /** Preview draft untuk administrator (SRS FR-CM-001). Tidak pernah dapat diakses publik atau diindeks. */
    public function preview(Request $request, Project $project): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->is_active, 404);

        return response()
            ->view('site.projects.show', [
                'project' => PublicProject::draft($project),
                'next' => null,
                'previous' => null,
                'preview' => true,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Proyek berikutnya/sebelumnya dalam urutan yang sama dengan listing (terbaru dulu).
     * "Next" = lebih lama, "Previous" = lebih baru. Null bila tidak ada.
     */
    private function neighbour(Project $project, bool $older): ?PublicProject
    {
        $neighbour = Project::query()
            ->live()
            ->with(['category', 'publishedCoverUsage.file'])
            ->where(function ($q) use ($project, $older) {
                $op = $older ? '<' : '>';
                $q->where('published_at', $op, $project->published_at)
                    ->orWhere(fn ($q) => $q->where('published_at', $project->published_at)->where('id', $op, $project->id));
            })
            ->orderBy('published_at', $older ? 'desc' : 'asc')
            ->orderBy('id', $older ? 'desc' : 'asc')
            ->first();

        return $neighbour ? PublicProject::published($neighbour) : null;
    }

    /** @param array<string, mixed> $extra */
    private function listUrl(?Category $category, array $extra = []): string
    {
        return route('home', array_filter(['category' => $category?->slug, ...$extra]));
    }
}
