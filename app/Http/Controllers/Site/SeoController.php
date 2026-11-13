<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AboutPage;
use App\Models\Article;
use App\Models\Project;
use Illuminate\Http\Response;

/** SEO otomatis: sitemap.xml dan robots.txt dibuat dari isi website yang sudah tayang. */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'lastmod' => Project::query()->live()->max('updated_at')],
            ['loc' => route('news'), 'lastmod' => Article::query()->live()->max('updated_at')],
            ['loc' => route('careers'), 'lastmod' => null],
            ['loc' => route('contact'), 'lastmod' => null],
        ]);

        // About hanya masuk bila sudah dipublikasikan (halaman penanda tidak diindeks).
        $about = AboutPage::query()->find(1);

        if ($about?->publishedContent() !== null) {
            $urls->push(['loc' => route('about'), 'lastmod' => $about->updated_at]);
        }

        Project::query()->live()->inSiteOrder()->get(['slug', 'updated_at'])
            ->each(fn (Project $project) => $urls->push(['loc' => route('projects.show', $project->slug), 'lastmod' => $project->updated_at]));

        Article::query()->live()->inSiteOrder()->get(['slug', 'updated_at'])
            ->each(fn (Article $article) => $urls->push(['loc' => route('news.show', $article->slug), 'lastmod' => $article->updated_at]));

        return response()
            ->view('site.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /preview/',
            'Disallow: /search',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
