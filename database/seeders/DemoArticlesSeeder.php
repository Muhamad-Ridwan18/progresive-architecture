<?php

namespace Database\Seeders;

use App\Actions\Articles\CreateArticle;
use App\Actions\Articles\PublishArticle;
use App\Actions\Articles\SaveArticleDraft;
use App\Actions\Articles\ScheduleArticle;
use App\Enums\CategoryType;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\Concerns\SeedsDemoMedia;
use Illuminate\Database\Seeder;

/**
 * Artikel News dari company profile (SRS 3.6, 5.2), dalam kategori Insights, Awards, dan Publication. Company
 * profile belum berisi artikel; isinya disusun dari teks deck itu sendiri (catatan desain proyek dan profil
 * pendiri), tanpa fakta tambahan. Judul dan teks bersifat contoh sampai tim menulis artikel sendiri.
 * Hasilnya: 5 artikel terbit (1 featured), 1 terjadwal, 1 draft. Cover dipasang lewat dashboard. Dilewati bila
 * sudah ada artikel.
 */
class DemoArticlesSeeder extends Seeder
{
    use SeedsDemoMedia;

    /** [judul, kategori, hari lalu, excerpt, isi] */
    private const PUBLISHED = [
        ['A courtyard between two buildings', 'Insights', 3,
            'How AD House was split along its length so light and air could reach both masses.',
            ['heading' => 'AD House, Bekasi',
                'paragraphs' => [
                    'A single-storey house, renovated and rebuilt as two. The building was split along its length, a courtyard placed between the two masses. The plot runs deep. The division made room for light and air to reach both.',
                    'The courtyard sits between the two buildings, open above where the canopy allows. The living room faces it on one side, the kitchen and dining on the other. Brick cladding lines the courtyard walls. A concrete bench runs along one wall. In front of it, space left for planting.',
                    'Above, the void rises to a skylight, interrupted only by the bridge that crosses it. Bedroom windows open toward it.',
                ],
                'quote' => 'The house does not change character. Outside and inside, the same materials, the same surfaces. The courtyard connects what the renovation divided.']],
        ['One geometric element: the arch at PS House', 'Insights', 12,
            'The arch is not decoration. It gives PS House a single architectural order, from facade to interior.',
            ['heading' => 'PS House, Bekasi',
                'paragraphs' => [
                    'The architecture is defined by a single geometric element: the arch. Rather than functioning as decoration, it provides a consistent architectural order that shapes the building as a whole.',
                    'The arch frames entrances, windows, and the canopy, before continuing into the interior where it defines circulation and living spaces. This continuity creates a coherent spatial language, reinforcing the relationship between structure, form, and the experience of movement throughout the house.',
                    'The staircase forms the spatial and visual centre of the house. Its structure and balustrade follow a continuous curve, expressing a single architectural gesture from base to top.',
                ],
                'quote' => 'The identity of the house emerges through proportion, material, and detail rather than ornament.']],
        ['Building on seventy square metres', 'Insights', 24,
            'FR House sits on a plot of 70 m² backed by open water. On a plot this size, every decision had to count.',
            ['heading' => 'FR House, Bandung',
                'paragraphs' => [
                    'The site measures seventy square metres, backed by open water. Two volumes rise on the second floor, clad in white tile. Between them, a recessed wall of concrete breeze blocks, open to the air on both sides.',
                    'The ground floor is compact. At the entrance, the void opens immediately above, the staircase beside it. Toward the back, the kitchen sits beneath an exposed bondek ceiling, a direct response to the limited floor height.',
                    'The void rises through both floors, open to the landing above. Concrete breeze blocks close the transition at both ends, drawing air through from front to back.',
                ],
                'quote' => 'On a site this constrained, the view was not overlooked.']],
        ['Recognition for Hilmi Haerul Umam', 'Awards', 40,
            'Top 25 Nominee at the SOHO Interior Design Competition (2017) and Third Prize at the Panatayuda Public Open Space Design Competition (2016).',
            ['heading' => 'Competitions',
                'paragraphs' => [
                    'During his academic years, Hilmi Haerul Umam actively participated in numerous architecture and interior design competitions, demonstrating an early commitment to design excellence and innovation.',
                    'His work was recognized as one of the Top 25 Nominees in the SOHO Interior Design Competition in 2017. In 2016, he was awarded Third Prize in the Panatayuda Public Open Space Design Competition in Bandung, reflecting his interest in creating meaningful public environments and human-centered spaces.',
                ],
                'quote' => null]],
        ['Light from above: skylights and voids', 'Insights', 55,
            'Across several houses, a louvred skylight or a void brings daylight down through the full height of the building.',
            ['heading' => 'Daylight as a material',
                'paragraphs' => [
                    'At AD House, a louvred skylight lets light fall onto the concrete below, its pattern changing through the day. The void beneath it draws light further down, reaching the ground floor.',
                    'At BY House, two voids open the house to light from above: one above the staircase, one above the kitchen. Windows at both draw daylight down through the full height of the house.',
                    'At PS House, glass blocks integrated into the second-floor void wall allow natural daylight to penetrate deep into the centre of the house, reducing the reliance on artificial lighting during the day.',
                ],
                'quote' => null]],
    ];

    public function run(): void
    {
        if (Article::query()->exists()) {
            $this->command?->warn('Sudah ada artikel. DemoArticlesSeeder dilewati agar konten yang ada tidak tercampur.');

            return;
        }

        $categories = collect(['Insights', 'Awards', 'Publication'])->mapWithKeys(fn (string $name) => [
            $name => Category::query()->firstOrCreate(['type' => CategoryType::Article->value, 'name' => $name]),
        ]);

        $author = auth()->user() ?? User::query()->first();
        $featured = null;

        foreach (self::PUBLISHED as [$title, $categoryName, $daysAgo, $excerpt, $body]) {
            $article = $this->atDaysAgo($daysAgo, function () use ($title, $categories, $categoryName, $excerpt, $body, $author): Article {
                $article = $this->draft($title, $categories[$categoryName]->id, $excerpt, $this->content($body), $author);

                return app(PublishArticle::class)->handle($article->fresh(), $author?->id);
            });

            $featured ??= $article;
        }

        $featured->markFeatured(true);

        $scheduled = $this->draft(
            'Tropical Modern: functionality, comfort, and context',
            $categories['Insights']->id,
            'The approach behind the studio: works that balance functionality, comfort, and contextual architectural expression.',
            $this->content([
                'heading' => 'Our approach',
                'paragraphs' => [
                    'Progressive Architect is rooted in a Tropical Modern design approach. The studio creates works that balance functionality, comfort, and contextual architectural expression.',
                    'Each project is approached as a response to the way people live, translated through a thoughtful composition of space, proportion, materials, and light.',
                ],
                'quote' => null,
            ]),
            $author,
        );
        app(ScheduleArticle::class)->schedule($scheduled, now()->addDay()->setTime(8, 0), $author?->id);

        $this->draft(
            'Cabinetry as architecture',
            $categories['Insights']->id,
            'Draft: notes on furniture as an integrated architectural element.',
            '<p>Catatan awal tentang furnitur sebagai bagian dari arsitektur, belum selesai.</p>',
            $author,
        );

        $this->command?->info('7 artikel dibuat (5 terbit termasuk 1 unggulan, 1 terjadwal, 1 draft).');
    }

    private function draft(string $title, int $categoryId, string $excerpt, string $content, ?User $author): Article
    {
        $article = app(CreateArticle::class)->handle(['title' => $title, 'category_id' => $categoryId], $author?->id);

        $article = app(SaveArticleDraft::class)->handle($article, [
            'excerpt' => $excerpt,
            'content' => $content,
            'author_id' => $author?->id,
        ], $article->draft_version, $author?->id);

        return $article->fresh();
    }

    /**
     * Isi artikel: heading, paragraf, dan kutipan opsional.
     *
     * @param  array{heading: string, paragraphs: list<string>, quote: ?string}  $body
     */
    private function content(array $body): string
    {
        $html = '<h2>'.e($body['heading']).'</h2>';

        foreach ($body['paragraphs'] as $paragraph) {
            $html .= '<p>'.e($paragraph).'</p>';
        }

        if ($body['quote']) {
            $html .= '<blockquote><p>'.e($body['quote']).'</p></blockquote>';
        }

        return $html;
    }
}
