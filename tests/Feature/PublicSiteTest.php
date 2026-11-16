<?php

namespace Tests\Feature;

use App\Actions\Media\AttachMedia;
use App\Actions\Media\UploadMedia;
use App\Actions\Projects\ArchiveProject;
use App\Actions\Projects\CreateProject;
use App\Actions\Projects\PublishProject;
use App\Actions\Projects\SaveProjectDraft;
use App\Enums\CategoryType;
use App\Enums\MediaStatus;
use App\Models\Category;
use App\Models\MediaFile;
use App\Models\Project;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    private Category $commercial;

    private Category $residential;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('media');
        Storage::fake('media-private');

        $this->commercial = Category::create(['type' => CategoryType::Project, 'name' => 'Commercial']);
        $this->residential = Category::create(['type' => CategoryType::Project, 'name' => 'Residential']);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $f) {
            @unlink($f);
        }

        parent::tearDown();
    }

    private function published(string $name, ?Category $category = null, array $fields = []): Project
    {
        $p = app(CreateProject::class)->handle(['name' => $name, 'category_id' => ($category ?? $this->commercial)->id]);

        if ($fields) {
            $p = app(SaveProjectDraft::class)->handle($p, $fields, $p->draft_version);
        }

        return app(PublishProject::class)->handle($p);
    }

    private function media(int $seed, int $w = 1600, int $h = 1000): MediaFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, ($seed * 37) % 255, ($seed * 91) % 255, ($seed * 53) % 255));
        $path = tempnam(sys_get_temp_dir(), 'img_');
        $this->tempFiles[] = $path;
        imagejpeg($im, $path, 80);

        return app(UploadMedia::class)->handle($path, "p{$seed}.jpg")->fresh();
    }

    private function many(int $count): void
    {
        foreach (range(1, $count) as $i) {
            $this->travel(1)->minutes(); // urutan terbit berbeda
            $this->published(sprintf('Project %02d', $i));
        }
    }

    // ---------------------------------------------------------------- visibilitas

    public function test_only_live_projects_are_listed(): void
    {
        $this->published('Live One');
        $draft = app(CreateProject::class)->handle(['name' => 'Secret Draft', 'category_id' => $this->commercial->id]);
        $archived = app(ArchiveProject::class)->archive($this->published('Hidden Archived'));

        $this->get('/')
            ->assertOk()
            ->assertSee('Live One')
            ->assertDontSee('Secret Draft')
            ->assertDontSee('Hidden Archived');

        $this->get('/projects/'.$draft->slug)->assertNotFound();
        $this->get('/projects/'.$archived->slug)->assertNotFound();
    }

    public function test_unpublished_edits_never_leak_to_visitors(): void
    {
        $p = $this->published('Public Name', null, ['location' => 'Jakarta']);
        app(SaveProjectDraft::class)->handle($p->fresh(), ['name' => 'Draft Only Name', 'location' => 'Rahasia'], $p->fresh()->draft_version);

        $this->get('/')->assertSee('Public Name')->assertDontSee('Draft Only Name');
        $this->get('/projects/'.$p->slug)
            ->assertOk()
            ->assertSee('Public Name')
            ->assertSee('Jakarta')
            ->assertDontSee('Draft Only Name')
            ->assertDontSee('Rahasia');
    }

    public function test_empty_state(): void
    {
        $this->get('/')->assertOk()->assertSee('Belum ada proyek');
    }

    // ---------------------------------------------------------------- kategori

    public function test_category_bar_lists_only_categories_with_live_projects_without_an_all_item(): void
    {
        $this->published('A', $this->commercial);
        // Residential hanya punya draft, jadi tidak boleh tampil.
        app(CreateProject::class)->handle(['name' => 'Only Draft', 'category_id' => $this->residential->id]);

        $response = $this->get('/')->assertOk();

        $response->assertSee('Commercial')->assertDontSee('Residential');
        $this->assertStringNotContainsString('All Projects', $response->getContent());
    }

    public function test_category_bar_follows_the_admin_defined_order(): void
    {
        $this->published('A', $this->commercial);
        $this->published('B', $this->residential);
        $this->residential->update(['sort_order' => 0]);
        $this->commercial->update(['sort_order' => 5]);

        $html = $this->get('/')->getContent();

        $this->assertLessThan(strpos($html, 'data-category="commercial"'), strpos($html, 'data-category="residential"'));
    }

    public function test_category_filter(): void
    {
        $this->published('Office Tower', $this->commercial);
        $this->published('Family Home', $this->residential);

        $this->get('/?category=residential')
            ->assertSee('Family Home')
            ->assertDontSee('Office Tower');

        // Slug tidak dikenal diabaikan (menampilkan semua), bukan error.
        $this->get('/?category=tidak-ada')->assertOk()->assertSee('Office Tower')->assertSee('Family Home');
    }

    // ---------------------------------------------------------------- pagination / infinite scroll

    public function test_first_page_has_eight_projects_and_a_sentinel_for_the_next(): void
    {
        $this->many(20);

        $response = $this->get('/')->assertOk();

        $this->assertSame(8, substr_count($response->getContent(), 'data-project-link'));
        $response->assertSee('data-sentinel', false)->assertSee('page=2', false);
    }

    public function test_ajax_returns_a_bare_fragment_and_the_pattern_continues_across_pages(): void
    {
        $this->many(20);

        $page2 = $this->get('/?page=2', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->assertSame(8, substr_count($page2->getContent(), 'data-project-link'));
        $this->assertStringNotContainsString('<html', $page2->getContent(), 'Fragmen tidak boleh memuat layout.');
        // Listing dua kolom sama besar (mockup): setiap item memakai setengah lebar.
        $this->assertSame(8, substr_count($page2->getContent(), 'md:col-span-6'));
    }

    public function test_pagination_stops_when_everything_is_shown(): void
    {
        $this->many(20);

        $last = $this->get('/?page=3', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->assertSame(4, substr_count($last->getContent(), 'data-project-link'));
        $this->assertStringNotContainsString('data-sentinel', $last->getContent());
    }

    public function test_newest_projects_come_first_and_pages_do_not_overlap(): void
    {
        $this->many(12);

        $p1 = $this->get('/')->getContent();
        $p2 = $this->get('/?page=2')->getContent();

        $this->assertStringContainsString('Project 12', $p1);
        $this->assertStringNotContainsString('Project 12', $p2);
        $this->assertStringContainsString('Project 01', $p2);
    }

    public function test_upto_restores_several_pages_at_once(): void
    {
        $this->many(20);

        $restored = $this->get('/?upto=2', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

        $this->assertSame(16, substr_count($restored->getContent(), 'data-project-link'));
        $restored->assertSee('page=3', false);
    }

    public function test_no_javascript_fallback_pagination_is_rendered(): void
    {
        $this->many(12);

        $this->get('/')->assertSee('pagination-fallback', false)->assertSee('Older', false);
        $this->get('/?page=2')->assertSee('Newer', false);
    }

    // ---------------------------------------------------------------- detail

    public function test_detail_page_shows_content_gallery_and_neighbours(): void
    {
        $this->travel(-3)->hours();
        $older = $this->published('Older Project');
        $this->travel(1)->hours();
        $middle = $this->published('Middle Project', null, ['location' => 'Bandung', 'year' => 2021, 'description' => "Paragraf satu.\n\nParagraf dua."]);
        $this->travel(1)->hours();
        $newer = $this->published('Newer Project');

        app(AttachMedia::class)->handle($this->media(1), $middle->fresh(), 'cover', ['alt_text' => 'Fasad depan']);
        foreach ([2, 3] as $seed) {
            app(AttachMedia::class)->handle($this->media($seed), $middle->fresh(), 'gallery', ['caption' => "Foto {$seed}", 'sort_order' => $seed]);
        }
        app(PublishProject::class)->handle($middle->fresh());

        $this->get('/projects/'.$middle->slug)
            ->assertOk()
            ->assertSee('<title>Middle Project', false)
            ->assertSee('Bandung')
            ->assertSee('2021')
            ->assertSee('Paragraf satu.')
            ->assertSee('Paragraf dua.')
            ->assertSee('alt="Fasad depan"', false)
            ->assertSee('Foto 2')
            ->assertSee('data-pswp-width', false)
            ->assertSee('Older Project')   // Next = lebih lama
            ->assertSee('Newer Project'); // Previous = lebih baru
    }

    public function test_description_is_escaped(): void
    {
        $p = $this->published('XSS', null, ['description' => '<script>alert(1)</script>']);

        $this->get('/projects/'.$p->slug)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_images_not_ready_are_skipped_instead_of_breaking_the_page(): void
    {
        $p = $this->published('Image Pending');
        $file = $this->media(9);
        app(AttachMedia::class)->handle($file, $p->fresh(), 'cover');
        app(PublishProject::class)->handle($p->fresh());

        // Simulasikan gambar yang kemudian bermasalah.
        $file->forceFill(['status' => MediaStatus::Failed, 'variants' => null])->save();

        $this->get('/')->assertOk()->assertSee('Image Pending');
        $this->get('/projects/'.$p->slug)->assertOk();
    }

    public function test_responsive_image_has_srcset_dimensions_and_focal_point(): void
    {
        $p = $this->published('With Cover');
        app(AttachMedia::class)->handle($this->media(5, 1600, 1000), $p->fresh(), 'cover', ['focal' => ['x' => 20, 'y' => 70]]);
        app(PublishProject::class)->handle($p->fresh());

        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('srcset="', $html);
        $this->assertStringContainsString('width="1600"', $html);
        $this->assertStringContainsString('height="1000"', $html);
        $this->assertStringContainsString('object-position: 20% 70%', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html, 'Gambar teratas dimuat segera.');
    }

    // ---------------------------------------------------------------- preview

    public function test_preview_is_hidden_from_guests(): void
    {
        $p = app(CreateProject::class)->handle(['name' => 'Draft Preview', 'category_id' => $this->commercial->id]);

        $this->get("/preview/projects/{$p->id}")->assertNotFound();
    }

    public function test_preview_is_hidden_from_inactive_admins(): void
    {
        $p = app(CreateProject::class)->handle(['name' => 'Draft Preview', 'category_id' => $this->commercial->id]);

        $this->actingAs(User::factory()->create(['is_active' => false]))
            ->get("/preview/projects/{$p->id}")
            ->assertNotFound();
    }

    public function test_admin_preview_shows_the_draft_with_noindex_and_a_banner(): void
    {
        $p = $this->published('Live Name', null, ['location' => 'Live Place']);
        app(SaveProjectDraft::class)->handle($p->fresh(), ['name' => 'Draft Name', 'location' => 'Draft Place'], $p->fresh()->draft_version);

        $this->actingAs(User::factory()->create())
            ->get("/preview/projects/{$p->id}")
            ->assertOk()
            ->assertSee('Draft Name')
            ->assertSee('Draft Place')
            ->assertSee('Preview.')
            ->assertSee('noindex', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        // Dan versi publik tidak berubah.
        $this->get('/projects/'.$p->slug)->assertSee('Live Name')->assertDontSee('Draft Name');
    }

    // ---------------------------------------------------------------- situs

    public function test_layout_uses_website_settings(): void
    {
        Settings::setMany([
            'site_name' => 'Acme Contractors',
            'tagline' => 'Building well since 1990',
            'contact_email' => 'hello@acme.test',
            'company_address' => 'Jl. Contoh 1',
            'social_instagram' => 'https://instagram.com/acme',
        ]);

        $this->get('/')
            ->assertSee('Acme Contractors')
            ->assertSee('Building well since 1990')
            ->assertSee('hello@acme.test')
            ->assertSee('Jl. Contoh 1')
            ->assertSee('https://instagram.com/acme', false);
    }

    public function test_footer_has_a_discreet_staff_login_link_on_every_public_page(): void
    {
        $loginUrl = route('filament.admin.auth.login');

        foreach (['/', '/contact', '/about'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Staff login')
                ->assertSee('href="'.$loginUrl.'" rel="nofollow"', false);
        }

        // Pintu itu hanya tautan: dashboard tetap menuntut login, dan robots.txt melarang penelusurannya.
        $this->get('/admin')->assertRedirect($loginUrl);
        $this->get('/robots.txt')->assertSee('Disallow: /admin');
    }

    public function test_social_links_are_icon_only_logos_in_the_menu_and_footer(): void
    {
        Settings::setMany([
            'social_instagram' => 'https://instagram.com/acme',
            'social_linkedin' => 'https://linkedin.com/company/acme',
            'social_other' => [['platform' => 'TikTok', 'url' => 'https://tiktok.com/@acme'], ['platform' => 'Blog', 'url' => 'https://blog.acme.test']],
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        // Menu dan footer masing-masing memuat empat ikon; semuanya bertanda aria-label dan berisi logo SVG.
        $this->assertSame(8, substr_count($html, 'target="_blank"'));
        foreach (['Instagram', 'LinkedIn', 'TikTok', 'Blog'] as $label) {
            $this->assertSame(2, substr_count($html, 'aria-label="'.$label.'"'));
        }
        $this->assertStringContainsString('aria-label="Social media"', $html);

        // Tidak ada teks nama platform yang tampil sebagai tautan (hanya ikon).
        $this->assertDoesNotMatchRegularExpression('/>\s*Instagram\s*<\/a>/', $html);
    }

    public function test_every_public_page_has_the_floating_back_to_top_button(): void
    {
        foreach (['/', '/about', '/team', '/news', '/careers', '/contact', '/search'] as $path) {
            $response = $this->get($path);

            if ($response->isRedirection()) {
                $response = $this->get($response->headers->get('Location'));
            }

            $response->assertOk()->assertSee('id="back-to-top"', false)->assertSee('aria-label="Back to top"', false);
        }
    }

    public function test_menu_button_is_a_three_line_hamburger(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $button = substr($html, strpos($html, 'id="menu-toggle"'), 700);

        $this->assertSame(3, substr_count($button, 'block h-0.5 bg-ink'));
    }

    public function test_project_opens_in_place_from_the_list_through_a_panel_fragment(): void
    {
        $project = $this->published('Rumah Panel', null, ['description' => 'Narasi singkat proyek.', 'location' => 'Bandung', 'client' => 'Klien Satu']);
        app(AttachMedia::class)->handle($this->media(1), $project->fresh(), 'cover', ['alt_text' => 'Fasad']);
        foreach ([2, 3] as $seed) {
            app(AttachMedia::class)->handle($this->media($seed), $project->fresh(), 'gallery', ['sort_order' => $seed, 'caption' => "Keterangan foto {$seed}"]);
        }
        app(PublishProject::class)->handle($project->fresh());

        // Tile membawa alamat panel; tanpa JavaScript tautannya tetap ke halaman detail.
        $this->get('/')->assertOk()
            ->assertSee('data-panel="'.route('projects.panel', $project->slug).'"', false)
            ->assertSee('href="'.route('projects.show', $project->slug).'"', false);

        $panel = $this->get(route('projects.panel', $project->slug))->assertOk()
            ->assertSee('data-project-panel', false)
            ->assertSee('Rumah Panel')
            ->assertSee('Narasi singkat proyek.')
            ->assertSee('Klien Satu')
            ->assertSee('data-panel-scroller', false)
            ->assertSee('Keterangan foto 2')
            ->assertSee('Keterangan foto 3')
            ->assertSee('</span> / 03', false)
            ->assertSee(route('projects.show', $project->slug), false)
            ->getContent();

        // Fragmen, bukan halaman penuh.
        $this->assertStringNotContainsString('<html', $panel);
    }

    public function test_text_added_after_a_photo_shows_in_the_strip_as_narrative_or_quote(): void
    {
        $project = $this->published('Rumah Teks');
        app(AttachMedia::class)->handle($this->media(1), $project->fresh(), 'cover');
        app(AttachMedia::class)->handle($this->media(2), $project->fresh(), 'gallery', ['sort_order' => 1, 'note' => 'Narasi setelah foto kedua.']);
        app(AttachMedia::class)->handle($this->media(3), $project->fresh(), 'gallery', ['sort_order' => 2, 'note' => 'Rumah ini bernapas.', 'note_by' => 'Hilmi, Founder']);
        app(PublishProject::class)->handle($project->fresh());

        $panel = $this->get(route('projects.panel', $project->slug))->assertOk()
            ->assertSee('Narasi setelah foto kedua.')
            ->assertSee('Rumah ini bernapas.')
            ->assertSee('Hilmi, Founder')
            ->assertSee('01', false)
            ->getContent();

        // Kutipan tampil dengan tanda kutip; teks biasa tidak.
        $this->assertStringContainsString('&ldquo;Rumah ini bernapas.&rdquo;', $panel);
        $this->assertStringNotContainsString('&ldquo;Narasi setelah foto kedua.', $panel);

        $this->get(route('projects.show', $project->slug))->assertOk()
            ->assertSee('Narasi setelah foto kedua.')
            ->assertSee('Hilmi, Founder');
    }

    public function test_panel_is_not_available_for_unpublished_projects(): void
    {
        $live = $this->published('Rumah Live');
        $draft = app(CreateProject::class)->handle(['name' => 'Rumah Draft', 'category_id' => $live->category_id]);

        $this->get(route('projects.panel', $draft->slug))->assertNotFound();
        $this->get('/projects/tidak-ada/panel')->assertNotFound();
    }

    public function test_footer_always_credits_webyouneed(): void
    {
        $this->get('/')->assertOk()->assertSee('Design by Webyouneed');
        $this->get('/contact')->assertOk()->assertSee('Design by Webyouneed');
    }

    public function test_seo_is_generated_automatically_from_the_page_and_site_identity(): void
    {
        Settings::setMany(['site_name' => 'Acme Studio', 'company_description' => 'Studio desain <b>tropis</b> di Bandung.']);
        $this->published('Rumah Satu', null, ['description' => 'Rumah dua lantai dengan taman belakang.']);

        $home = $this->get('/')->assertOk()
            ->assertSee('<title>Acme Studio — Commercial</title>', false)
            ->assertSee('<meta name="description" content="Studio desain tropis di Bandung.">', false)
            ->assertSee('<link rel="canonical" href="'.url('/').'">', false)
            ->assertSee('twitter:card', false)
            ->assertSee('application/ld+json', false)
            ->getContent();

        $this->assertStringContainsString('"@type":"Organization"', $home);

        $this->get('/contact')->assertOk()->assertSee('<title>Contact — Acme Studio</title>', false);
    }

    public function test_long_descriptions_are_trimmed_and_noindex_pages_have_no_canonical(): void
    {
        $this->get('/search')->assertOk()
            ->assertSee('noindex', false)
            ->assertDontSee('rel="canonical"', false)
            ->assertDontSee('application/ld+json', false);

        Settings::setMany(['company_description' => str_repeat('Kalimat panjang. ', 40)]);
        $html = $this->get('/')->getContent();
        preg_match('/<meta name="description" content="([^"]*)"/', $html, $m);

        $this->assertLessThanOrEqual(160, mb_strlen(html_entity_decode($m[1])));
    }

    public function test_sitemap_lists_published_content_only_and_robots_points_to_it(): void
    {
        $live = $this->published('Rumah Live');
        $draft = app(CreateProject::class)->handle(['name' => 'Rumah Draft', 'category_id' => $live->category_id]);

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

        $this->assertStringContainsString(route('projects.show', $live->slug), $xml);
        $this->assertStringNotContainsString($draft->slug, $xml);
        $this->assertStringContainsString(route('contact'), $xml);

        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_no_social_icons_are_rendered_without_social_settings(): void
    {
        $this->get('/')->assertOk()->assertDontSee('aria-label="Social media"', false);
    }

    public function test_menu_links_resolve_and_placeholder_pages_are_not_indexed(): void
    {
        // Halaman yang belum dibangun berupa penanda dan tidak boleh diindeks.
        foreach (['/about'] as $url) {
            $this->get($url)->assertOk()->assertSee('noindex', false);
        }

        // Contact, Careers, dan News sudah nyata dan boleh diindeks.
        foreach (['/contact', '/careers', '/news'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('noindex', false);
        }
    }
}
