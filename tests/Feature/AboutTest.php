<?php

namespace Tests\Feature;

use App\Actions\About\DiscardAboutDraft;
use App\Actions\About\PublishAbout;
use App\Actions\About\SaveAboutDraft;
use App\Actions\Projects\StaleDraftException;
use App\Enums\PageStatus;
use App\Filament\Pages\ManageAboutUs;
use App\Models\AboutPage;
use App\Models\User;
use App\Support\AboutContent;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AboutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Filament::setCurrentPanel('admin');
    }

    /**
     * Isi lengkap untuk uji. Override mengganti satu BAGIAN secara utuh (bukan penggabungan rekursif,
     * karena penggabungan rekursif tidak pernah bisa mengosongkan sebuah daftar).
     */
    private function content(array $override = []): array
    {
        return array_merge([
            'overview' => ['heading' => 'Membangun dengan tanggung jawab', 'body' => "Kami kontraktor sejak 1998.\n\nKami fokus pada mutu."],
            'history' => [
                'body' => 'Berawal dari tim kecil di Bandung.',
                'milestones' => [['year' => '1998', 'title' => 'Perusahaan berdiri', 'description' => 'Proyek pertama.']],
            ],
            'vision_mission' => ['vision' => 'Menjadi kontraktor terpercaya.', 'mission' => 'Bekerja aman dan tepat waktu.'],
            'values' => [['title' => 'Integritas', 'description' => 'Jujur dalam bekerja.']],
            'statistics' => [['value' => '250+', 'label' => 'Proyek selesai']],
            'team' => [['name' => 'Siti Rahma', 'position' => 'Direktur', 'bio' => 'Insinyur sipil.']],
        ], $override);
    }

    private function save(AboutPage $p, array $content): AboutPage
    {
        return app(SaveAboutDraft::class)->handle($p, $content, $p->draft_version);
    }

    private function published(array $content = []): AboutPage
    {
        $p = $this->save(AboutPage::current(), $this->content($content));

        return app(PublishAbout::class)->handle($p);
    }

    // ---------------------------------------------------------------- aturan konten

    public function test_normalize_cleans_input_into_a_canonical_shape(): void
    {
        $n = AboutContent::normalize([
            'overview' => ['heading' => '  Halo  ', 'body' => '   ', 'bukan_field' => 'dibuang'],
            'statistics' => [
                'uuid-a' => ['value' => '10', 'label' => 'Proyek'],
                'uuid-b' => ['value' => '', 'label' => ''],          // kosong semua: dibuang
                'uuid-c' => 'bukan array',                           // tidak valid: dibuang
            ],
            'tidak_dikenal' => ['x' => 1],
        ]);

        $this->assertSame('Halo', $n['overview']['heading']);
        $this->assertNull($n['overview']['body'], 'String kosong menjadi null.');
        $this->assertArrayNotHasKey('bukan_field', $n['overview']);
        $this->assertArrayNotHasKey('tidak_dikenal', $n);
        $this->assertSame([['value' => '10', 'label' => 'Proyek']], $n['statistics'], 'Diindeks ulang, item kosong dibuang.');
        $this->assertSame($n, AboutContent::normalize($n), 'Normalisasi bersifat idempoten.');
    }

    public function test_validation_rules(): void
    {
        // Pasangan [bagian yang salah, kunci galat yang diharapkan].
        foreach ([
            [['statistics' => [['value' => '5', 'label' => '']]], 'statistics.0.label'],
            [['statistics' => [['value' => '', 'label' => 'Tanpa angka']]], 'statistics.0.value'],
            [['team' => [['name' => '', 'position' => 'Tanpa nama']]], 'team.0.name'],
            [['values' => [['title' => '', 'description' => 'Tanpa judul']]], 'values.0.title'],
            [['overview' => ['heading' => str_repeat('x', 161)]], 'overview.heading'],
            [['statistics' => array_map(fn ($i) => ['value' => "$i", 'label' => "L$i"], range(1, AboutContent::MAX_STATISTICS + 1))], 'statistics'],
        ] as [$bad, $key]) {
            try {
                AboutContent::validated($this->content($bad));
                $this->fail("Seharusnya ditolak: {$key}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($key, $e->errors(), "Galat untuk {$key}");
            }
        }
    }

    // ---------------------------------------------------------------- draft / publish

    public function test_saving_a_draft_never_touches_the_published_version(): void
    {
        $p = $this->save(AboutPage::current(), $this->content());

        $this->assertSame(PageStatus::Draft, $p->status);
        $this->assertNull($p->published);
        $this->assertNull($p->publishedContent());
        $this->assertSame('Membangun dengan tanggung jawab', $p->draftContent()['overview']['heading']);
    }

    public function test_publish_copies_the_draft_and_logs_before_and_after(): void
    {
        $p = $this->published();

        $this->assertSame(PageStatus::Published, $p->status);
        $this->assertNotNull($p->published_at);
        $this->assertSame($p->draftContent(), $p->publishedContent());

        $log = Activity::where('log_name', 'about')->where('event', 'published')->first();
        $this->assertNotNull($log);
        $this->assertSame('Membangun dengan tanggung jawab', $log->properties['attributes']['overview']['heading']);
    }

    public function test_an_empty_page_cannot_be_published(): void
    {
        $this->expectException(ValidationException::class);
        app(PublishAbout::class)->handle(AboutPage::current());
    }

    public function test_editing_after_publish_keeps_the_live_version_until_republished(): void
    {
        $p = $this->published();
        $p = $this->save($p, $this->content(['overview' => ['heading' => 'Judul baru']]));

        $this->assertSame(PageStatus::UnpublishedChanges, $p->status);
        $this->assertSame('Membangun dengan tanggung jawab', $p->publishedContent()['overview']['heading']);

        $p = app(PublishAbout::class)->handle($p);
        $this->assertSame('Judul baru', $p->publishedContent()['overview']['heading']);
    }

    public function test_reverting_edits_returns_status_to_published(): void
    {
        $p = $this->published();

        $p = $this->save($p, $this->content(['overview' => ['heading' => 'Sementara']]));
        $this->assertSame(PageStatus::UnpublishedChanges, $p->status);

        $p = $this->save($p, $this->content());
        $this->assertSame(PageStatus::Published, $p->status);
    }

    public function test_discard_restores_the_published_version(): void
    {
        $p = $this->published();
        $p = $this->save($p, $this->content(['overview' => ['heading' => 'Salah ketik']]));

        $p = app(DiscardAboutDraft::class)->handle($p);

        $this->assertSame('Membangun dengan tanggung jawab', $p->draftContent()['overview']['heading']);
        $this->assertSame(PageStatus::Published, $p->status);
    }

    public function test_discard_requires_a_published_version(): void
    {
        $this->expectException(RuntimeException::class);
        app(DiscardAboutDraft::class)->handle($this->save(AboutPage::current(), $this->content()));
    }

    public function test_stale_saves_are_rejected_and_do_not_overwrite(): void
    {
        $p = AboutPage::current();
        $old = $p->draft_version;
        $p = $this->save($p, $this->content(['overview' => ['heading' => 'Tab pertama']]));

        try {
            app(SaveAboutDraft::class)->handle($p, $this->content(['overview' => ['heading' => 'Tab usang']]), $old);
            $this->fail('Seharusnya ditolak.');
        } catch (StaleDraftException) {
            $this->assertSame('Tab pertama', $p->fresh()->draftContent()['overview']['heading']);
        }
    }

    public function test_overlapping_saves_from_the_same_editor_are_accepted_but_other_editors_are_not(): void
    {
        $save = app(SaveAboutDraft::class);
        $p = AboutPage::current();
        $v = $p->draft_version;

        $p = $save->handle($p, $this->content(['overview' => ['heading' => 'A satu', 'body' => 'x']]), $v, null, 'editor-A');
        $p = $save->handle($p, $this->content(['overview' => ['heading' => 'A dua', 'body' => 'x']]), $v, null, 'editor-A');
        $this->assertSame('A dua', $p->draftContent()['overview']['heading']);

        $this->expectException(StaleDraftException::class);
        $save->handle($p->fresh(), $this->content(), $v, null, 'editor-B');
    }

    public function test_invalid_content_is_not_saved(): void
    {
        $p = AboutPage::current();

        try {
            $this->save($p, $this->content(['statistics' => [['value' => '5', 'label' => '']]]));
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertNull($p->fresh()->draft['overview']['heading']);
        }
    }

    // ---------------------------------------------------------------- halaman publik

    public function test_unpublished_page_shows_a_placeholder_and_does_not_write_to_the_database(): void
    {
        $this->get('/about')->assertOk()->assertSee('sedang disiapkan')->assertSee('noindex', false);

        $this->assertSame(0, AboutPage::count(), 'Kunjungan publik tidak boleh membuat baris.');
    }

    public function test_published_page_shows_every_section(): void
    {
        $this->published();

        $this->get('/about')
            ->assertOk()
            ->assertSee('<title>About', false)
            ->assertSee('Membangun dengan tanggung jawab')
            ->assertSee('Kami kontraktor sejak 1998.')
            ->assertSee('Kami fokus pada mutu.')
            ->assertSee('250+')
            ->assertSee('Proyek selesai')
            ->assertSee('Berawal dari tim kecil di Bandung.')
            ->assertSee('Perusahaan berdiri')
            ->assertSee('1998')
            ->assertSee('Menjadi kontraktor terpercaya.')
            ->assertSee('Bekerja aman dan tepat waktu.')
            ->assertSee('Integritas')
            ->assertDontSee('noindex', false);

        // Tim punya halaman sendiri (mockup), bukan bagian halaman About.
        $this->get('/about')->assertOk()->assertSee('Siti Rahma')->assertSee('Direktur');
    }

    public function test_empty_sections_are_hidden_and_our_team_is_optional(): void
    {
        $this->published([
            'history' => ['body' => null, 'milestones' => []],
            'vision_mission' => ['vision' => null, 'mission' => null],
            'values' => [],
            'statistics' => [],
            'team' => [],
        ]);

        $html = $this->get('/about')->assertOk()->getContent();

        foreach (['History', 'Vision &amp; Mission', 'Values', 'Our Team', 'Company statistics'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $html, "Bagian kosong '{$hidden}' tidak boleh tampil.");
        }
        $this->assertStringContainsString('Membangun dengan tanggung jawab', $html);
    }

    public function test_content_is_escaped(): void
    {
        $this->published(['overview' => ['heading' => '<script>alert(1)</script>', 'body' => '<img src=x onerror=alert(1)>']]);

        $this->get('/about')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_unpublished_edits_never_leak_to_visitors(): void
    {
        $p = $this->published();
        $this->save($p, $this->content(['overview' => ['heading' => 'JUDUL-DRAFT-RAHASIA']]));

        $this->get('/about')->assertSee('Membangun dengan tanggung jawab')->assertDontSee('JUDUL-DRAFT-RAHASIA');
    }

    public function test_preview_is_admin_only_noindex_and_shows_the_draft(): void
    {
        $p = $this->published();
        $this->save($p, $this->content(['overview' => ['heading' => 'JUDUL-DRAFT-RAHASIA']]));

        $this->get('/preview/about')->assertNotFound();

        $this->actingAs(User::factory()->create(['is_active' => false]))->get('/preview/about')->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get('/preview/about')
            ->assertOk()
            ->assertSee('JUDUL-DRAFT-RAHASIA')
            ->assertSee('Preview.')
            ->assertSee('noindex', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    // ---------------------------------------------------------------- editor admin

    public function test_editor_page_opens_filled_from_the_draft(): void
    {
        $this->actingAs(User::factory()->create());
        $this->save(AboutPage::current(), $this->content());

        $this->get('/admin/about-us')->assertOk();

        Livewire::test(ManageAboutUs::class)
            ->assertFormSet(['overview.heading' => 'Membangun dengan tanggung jawab'])
            ->assertSee('Company Overview')
            ->assertSee('Our Team');
    }

    public function test_autosave_writes_to_the_draft_only(): void
    {
        $this->actingAs(User::factory()->create());
        $this->published();

        Livewire::test(ManageAboutUs::class)
            ->fillForm(['overview.heading' => 'Diubah lewat editor'])
            ->call('autosave')
            ->assertSet('saveState', 'saved');

        $p = AboutPage::current();
        $this->assertSame('Diubah lewat editor', $p->draftContent()['overview']['heading']);
        $this->assertSame('Membangun dengan tanggung jawab', $p->publishedContent()['overview']['heading']);
        $this->assertSame(PageStatus::UnpublishedChanges, $p->status);
    }

    public function test_repeaters_can_be_added_and_saved(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageAboutUs::class)
            ->fillForm([
                'statistics' => [['value' => '12', 'label' => 'Tahun pengalaman'], ['value' => '99%', 'label' => 'Tepat waktu']],
                'team' => [['name' => 'Andi', 'position' => 'Manajer', 'bio' => null]],
            ])
            ->call('autosave')
            ->assertSet('saveState', 'saved');

        $d = AboutPage::current()->draftContent();
        $this->assertSame(['12', '99%'], array_column($d['statistics'], 'value'));
        $this->assertSame('Andi', $d['team'][0]['name']);
    }

    public function test_autosave_reports_invalid_input_without_saving_it(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageAboutUs::class)
            ->fillForm(['statistics' => [['value' => '5', 'label' => '']]])
            ->call('autosave')
            ->assertSet('saveState', 'invalid')
            ->assertSee('Setiap statistik perlu keterangan');

        $this->assertSame([], AboutPage::current()->draftContent()['statistics']);
    }

    public function test_autosave_detects_a_conflicting_editor(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Livewire::test(ManageAboutUs::class);

        // Tab lain menyimpan lebih dulu.
        $this->save(AboutPage::current(), $this->content(['overview' => ['heading' => 'Dari tab lain']]));

        $page->fillForm(['overview.heading' => 'Dari tab usang'])
            ->call('autosave')
            ->assertSet('saveState', 'conflict');

        $this->assertSame('Dari tab lain', AboutPage::current()->draftContent()['overview']['heading']);
    }

    public function test_publish_and_discard_actions(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageAboutUs::class)
            ->fillForm($this->content())
            ->callAction('publish')
            ->assertSet('saveState', 'saved');

        $this->assertSame(PageStatus::Published, AboutPage::current()->status);
        $this->assertSame('250+', AboutPage::current()->publishedContent()['statistics'][0]['value']);

        Livewire::test(ManageAboutUs::class)
            ->fillForm(['overview.heading' => 'Akan dibuang'])
            ->call('autosave')
            ->callAction('discard')
            ->assertFormSet(['overview.heading' => 'Membangun dengan tanggung jawab']);

        $this->assertSame(PageStatus::Published, AboutPage::current()->status);
    }

    public function test_publish_action_refuses_an_empty_page(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageAboutUs::class)->callAction('publish');

        $this->assertNull(AboutPage::current()->published);
        $this->assertSame(PageStatus::Draft, AboutPage::current()->status);
    }
}
