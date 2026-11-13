<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\PublicArticle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class ArticleController extends Controller
{
    /** Jumlah artikel pada grid per muat (di luar artikel featured/hero). */
    private const PER_PAGE = 6;

    private const RELATED = 3;

    /** Kategori yang ditampilkan sebagai daftar baris (tahun, judul, penulis) alih-alih kartu. */
    private const ROW_CATEGORIES = ['awards'];

    /** Kategori yang ditampilkan sebagai blok besar satu per baris (sampul dan deskripsi). */
    private const BLOCK_CATEGORIES = ['publication'];

    /**
     * Listing News (SRS 3.6), mengikuti mockup: tab kategori (Insights, Awards, Publication, ...) dan satu
     * kategori ditampilkan sekaligus (bawaan: yang pertama). Insights berupa kartu 3 kolom, Awards daftar
     * baris, Publication blok besar. Artikel featured tampil paling atas. Satu logika untuk halaman penuh
     * (dan fallback pagination lewat ?page=) dan fragmen AJAX untuk infinite scroll.
     */
    public function index(Request $request)
    {
        $categories = Category::query()
            ->ofType(Article::categoryType())
            ->whereHas('articles', fn ($q) => $q->live())
            ->orderBy('sort_order')
            ->get();

        $active = $categories->firstWhere('slug', $request->query('category')) ?? $categories->first();
        $page = max(1, (int) $request->query('page', 1));

        $base = Article::query()
            ->live()
            ->with(['category', 'author', 'publishedCoverUsage.file'])
            ->when($active, fn ($q) => $q->where('category_id', $active->id))
            ->orderByDesc('is_featured')
            ->inSiteOrder();

        $offset = ($page - 1) * self::PER_PAGE;
        $items = (clone $base)->skip($offset)->take(self::PER_PAGE)->get()->map(fn (Article $a) => PublicArticle::published($a));
        $remaining = max(0, (clone $base)->count() - ($offset + self::PER_PAGE));

        $data = [
            'items' => $items,
            'offset' => $offset,
            'layout' => $this->layoutFor($active),
            'nextUrl' => $remaining > 0 ? $this->listUrl($active, ['page' => $page + 1]) : null,
            'prevUrl' => $page > 1 ? $this->listUrl($active, $page > 2 ? ['page' => $page - 1] : []) : null,
        ];

        if ($request->ajax()) {
            return response()->view('site.news._items', $data)->header('Vary', 'X-Requested-With');
        }

        return response()
            ->view('site.news.index', $data + ['categories' => $categories, 'active' => $active])
            ->header('Vary', 'X-Requested-With');
    }

    /** cards | rows | blocks, menurut slug kategori. */
    private function layoutFor(?Category $category): string
    {
        return match (true) {
            $category !== null && in_array($category->slug, self::ROW_CATEGORIES, true) => 'rows',
            $category !== null && in_array($category->slug, self::BLOCK_CATEGORIES, true) => 'blocks',
            default => 'cards',
        };
    }

    /** Detail artikel (SRS FR-FE-074). Hanya versi published; draft dan Scheduled tidak ditemukan. */
    public function show(string $slug)
    {
        $article = Article::query()
            ->live()
            ->with(['category', 'author', 'publishedCoverUsage.file'])
            ->where('slug', $slug)
            ->firstOrFail();

        return view('site.news.show', [
            'article' => PublicArticle::published($article),
            'related' => $this->related($article),
            'preview' => false,
        ]);
    }

    /** Preview draft untuk administrator (SRS FR-CM-001). Tidak pernah dapat diakses publik atau diindeks. */
    public function preview(Request $request, Article $article): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->is_active, 404);

        return response()
            ->view('site.news.show', [
                'article' => PublicArticle::draft($article),
                'related' => collect(),
                'preview' => true,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Related Articles: kategori yang sama dulu, dilengkapi artikel terbaru lainnya bila kurang.
     *
     * @return Collection<int, PublicArticle>
     */
    private function related(Article $article)
    {
        $base = fn (): Builder => Article::query()->live()->whereKeyNot($article->getKey())
            ->with(['category', 'author', 'publishedCoverUsage.file'])->inSiteOrder();

        $related = $base()->where('category_id', $article->category_id)->take(self::RELATED)->get();

        if ($related->count() < self::RELATED) {
            $related = $related->concat(
                $base()->whereNotIn('id', $related->modelKeys())->take(self::RELATED - $related->count())->get()
            );
        }

        return $related->map(fn (Article $a) => PublicArticle::published($a));
    }

    /** @param array<string, mixed> $extra */
    private function listUrl(?Category $category, array $extra = []): string
    {
        return route('news', array_filter(['category' => $category?->slug, ...$extra]));
    }
}
