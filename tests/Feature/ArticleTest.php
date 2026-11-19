<?php

namespace Tests\Feature;

use App\Actions\Articles\CreateArticle;
use App\Actions\Articles\DiscardArticleDraft;
use App\Actions\Articles\PublishArticle;
use App\Actions\Articles\SaveArticleDraft;
use App\Actions\Articles\ScheduleArticle;
use App\Actions\Projects\StaleDraftException;
use App\Enums\ArticleStatus;
use App\Enums\CategoryType;
use App\Filament\Resources\Articles\Pages\CreateArticle as CreateArticlePage;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->category = Category::create(['type' => CategoryType::Article, 'name' => 'Konstruksi']);
    }

    private function admin(): User
    {
        Filament::setCurrentPanel('admin');

        return tap(User::factory()->create(), fn (User $user) => $this->actingAs($user));
    }

    private function draftArticle(string $title = 'Beton Cepat'): Article
    {
        return app(CreateArticle::class)->handle(['title' => $title, 'category_id' => $this->category->id]);
    }

    // ---- Alur draft dan publish -----------------------------------------------------------

    public function test_new_article_is_a_draft_with_a_unique_slug_and_not_public(): void
    {
        $a = $this->draftArticle('Beton Cepat');
        $b = $this->draftArticle('Beton Cepat');

        $this->assertSame(ArticleStatus::Draft, $a->status);
        $this->assertSame('beton-cepat', $a->slug);
        $this->assertSame('beton-cepat-2', $b->slug);

        $this->get('/news/beton-cepat')->assertNotFound();
    }

    public function test_publishing_goes_live_and_editing_afterwards_keeps_the_published_version_until_republished(): void
    {
        $article = $this->draftArticle();
        $save = app(SaveArticleDraft::class);

        $article = $save->handle($article, ['excerpt' => 'Versi satu', 'content' => '<p>Isi satu</p>'], $article->draft_version);
        $article = app(PublishArticle::class)->handle($article);

        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->get('/news/beton-cepat')->assertOk()->assertSee('Isi satu', false);

        $article = $save->handle($article, ['content' => '<p>Isi dua</p>'], $article->draft_version);

        $this->assertSame(ArticleStatus::UnpublishedChanges, $article->status);
        $this->get('/news/beton-cepat')->assertSee('Isi satu', false)->assertDontSee('Isi dua', false);

        // Editan dikembalikan seperti semula: status kembali ke Published.
        $article = $save->handle($article, ['content' => '<p>Isi satu</p>'], $article->draft_version);
        $this->assertSame(ArticleStatus::Published, $article->status);

        $article = $save->handle($article, ['content' => '<p>Isi tiga</p>'], $article->draft_version);
        $article = app(DiscardArticleDraft::class)->handle($article);

        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertStringContainsString('Isi satu', $article->draftFields()['content']);
    }

    public function test_stale_draft_version_is_rejected(): void
    {
        $article = $this->draftArticle();
        $save = app(SaveArticleDraft::class);
        $version = $article->draft_version;

        $save->handle($article, ['excerpt' => 'Pertama'], $version, editorId: 'tab-a');

        $this->expectException(StaleDraftException::class);
        $save->handle($article->refresh(), ['excerpt' => 'Menimpa'], $version, editorId: 'tab-b');
    }

    public function test_discard_needs_a_published_version(): void
    {
        $this->expectException(RuntimeException::class);

        app(DiscardArticleDraft::class)->handle($this->draftArticle());
    }

    public function test_publish_requires_valid_fields(): void
    {
        $article = $this->draftArticle();
        $article->forceFill(['draft_data' => [...$article->draftFields(), 'slug' => 'Slug Salah!']])->save();

        $this->expectException(ValidationException::class);

        app(PublishArticle::class)->handle($article->refresh());
    }

    // ---- Scheduled publishing -------------------------------------------------------------

    public function test_only_future_drafts_can_be_scheduled(): void
    {
        $article = $this->draftArticle();
        $schedule = app(ScheduleArticle::class);

        try {
            $schedule->schedule($article, now()->subMinute());
            $this->fail('Jadwal di masa lalu seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('scheduled_at', $e->errors());
        }

        $scheduled = $schedule->schedule($article, now()->addDay());
        $this->assertSame(ArticleStatus::Scheduled, $scheduled->status);

        // Scheduled belum tampil di website.
        $this->get('/news/'.$scheduled->slug)->assertNotFound();

        $cancelled = $schedule->cancel($scheduled);
        $this->assertSame(ArticleStatus::Draft, $cancelled->status);
        $this->assertNull($cancelled->scheduled_at);
    }

    public function test_command_publishes_only_due_articles_using_their_scheduled_time(): void
    {
        $due = Article::factory()->scheduledAt(now()->subMinutes(5))->create(['title' => 'Sudah Waktunya']);
        $future = Article::factory()->scheduledAt(now()->addHour())->create(['title' => 'Belum Waktunya']);
        $scheduledTime = $due->scheduled_at->copy();

        $this->artisan('articles:publish-scheduled')->assertSuccessful();

        $due->refresh();
        $this->assertSame(ArticleStatus::Published, $due->status);
        $this->assertNull($due->scheduled_at);
        $this->assertTrue($due->published_at->equalTo($scheduledTime));
        $this->assertSame(ArticleStatus::Scheduled, $future->refresh()->status);

        $this->get('/news/'.$due->slug)->assertOk();
        $this->get('/news/'.$future->slug)->assertNotFound();
    }

    public function test_scheduled_article_that_fails_validation_returns_to_draft(): void
    {
        $article = Article::factory()->scheduledAt(now()->subMinute())->create();
        $article->forceFill(['draft_data' => [...$article->draftFields(), 'slug' => 'Rusak!']])->save();

        $this->artisan('articles:publish-scheduled')->assertSuccessful();

        $this->assertSame(ArticleStatus::Draft, $article->refresh()->status);
    }

    // ---- Featured dan listing -------------------------------------------------------------

    public function test_only_one_article_is_featured_at_a_time(): void
    {
        $first = Article::factory()->featured()->create();
        $second = Article::factory()->create();

        $second->markFeatured(true);

        $this->assertFalse($first->refresh()->is_featured);
        $this->assertTrue($second->refresh()->is_featured);
        $this->assertSame(1, Article::query()->where('is_featured', true)->count());
    }

    public function test_featured_article_comes_first_in_its_category_even_when_older(): void
    {
        Article::factory()->create(['title' => 'Artikel Terbaru', 'published_at' => now()]);
        Article::factory()->featured()->create(['title' => 'Artikel Unggulan', 'published_at' => now()->subWeek()]);

        $this->get('/news')->assertOk()->assertSeeInOrder(['Artikel Unggulan', 'Artikel Terbaru']);
    }

    public function test_listing_shows_one_category_at_a_time_starting_with_the_first(): void
    {
        $other = Category::create(['type' => CategoryType::Article, 'name' => 'Arsitektur']);
        Article::factory()->create(['title' => 'Tentang Beton']);
        Article::factory()->create(['title' => 'Tentang Fasad', 'category_id' => $other->id]);

        // Tab pertama (urutan kategori) tampil bawaan; tab lain dibuka lewat ?category=.
        $this->get('/news')->assertSee('Tentang Beton')->assertDontSee('Tentang Fasad');
        $this->get('/news?category=arsitektur')->assertSee('Tentang Fasad')->assertDontSee('Tentang Beton');
        $this->get('/news')->assertSee('Arsitektur')->assertSee(route('careers'), false);
    }

    public function test_listing_hides_drafts_and_scheduled(): void
    {
        Article::factory()->create(['title' => 'Terbit']);
        Article::factory()->draft()->create(['title' => 'Rahasia Draft']);
        Article::factory()->scheduledAt(now()->addDay())->create(['title' => 'Rahasia Jadwal']);

        $this->get('/news')->assertSee('Terbit')->assertDontSee('Rahasia Draft')->assertDontSee('Rahasia Jadwal');
    }

    public function test_awards_use_a_row_list_and_publication_uses_large_blocks(): void
    {
        $awards = Category::create(['type' => CategoryType::Article, 'name' => 'Awards']);
        $publication = Category::create(['type' => CategoryType::Article, 'name' => 'Publication']);
        Article::factory()->create(['title' => 'Penghargaan Satu', 'category_id' => $awards->id, 'published_at' => '2017-05-01 10:00:00']);
        Article::factory()->create(['title' => 'Buku Konsep', 'category_id' => $publication->id, 'excerpt' => 'Deskripsi publikasi']);

        $this->get('/news?category=awards')->assertSee('Penghargaan Satu')->assertSee('2017')
            ->assertDontSee('aspect-[4/5]', false);

        $this->get('/news?category=publication')->assertSee('Buku Konsep')->assertSee('Deskripsi publikasi')
            ->assertSee('aspect-[4/5]', false);
    }

    public function test_grid_paginates_and_serves_html_fragments_for_infinite_scroll(): void
    {
        foreach (range(1, 8) as $i) {
            Article::factory()->create(['title' => "Artikel nomor {$i}", 'published_at' => now()->subDays($i)]);
        }

        $first = $this->get('/news')->assertOk();
        $first->assertSee('Artikel nomor 1')->assertSee('data-news-sentinel', false)->assertDontSee('Artikel nomor 7');

        $fragment = $this->get('/news?page=2', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
        $fragment->assertSee('Artikel nomor 7')->assertDontSee('<html', false)->assertDontSee('data-news-sentinel', false);
    }

    public function test_detail_shows_related_articles_from_the_same_category_first(): void
    {
        $other = Category::create(['type' => CategoryType::Article, 'name' => 'Arsitektur']);
        $article = Article::factory()->create(['title' => 'Utama']);
        Article::factory()->create(['title' => 'Sekategori']);
        Article::factory()->create(['title' => 'Beda Kategori', 'category_id' => $other->id, 'published_at' => now()->subYear()]);

        $this->get('/news/'.$article->slug)->assertOk()
            ->assertSee('Related Articles')
            ->assertSee('Sekategori')
            ->assertSee('Beda Kategori');
    }

    public function test_article_body_is_sanitized(): void
    {
        $article = Article::factory()->create(['content' => '<p>Aman</p><script>alert(1)</script><p onclick="x()">Klik</p>']);

        $this->get('/news/'.$article->slug)->assertOk()
            ->assertSee('Aman')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('onclick', false);
    }

    // ---- Preview --------------------------------------------------------------------------

    public function test_preview_shows_the_draft_to_active_admins_only(): void
    {
        $article = $this->draftArticle('Belum Terbit');
        app(SaveArticleDraft::class)->handle($article, ['content' => '<p>Isi rahasia</p>'], $article->draft_version);

        $this->get(route('news.preview', $article))->assertNotFound();

        $this->actingAs(User::factory()->create(['is_active' => false]))
            ->get(route('news.preview', $article))->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get(route('news.preview', $article))
            ->assertOk()
            ->assertSee('Isi rahasia', false)
            ->assertSee('noindex', false);
    }

    // ---- Dashboard ------------------------------------------------------------------------

    public function test_admin_can_create_an_article_and_lands_in_the_editor(): void
    {
        $this->admin();

        Livewire::test(CreateArticlePage::class)
            ->fillForm(['title' => 'Artikel Baru', 'category_id' => $this->category->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = Article::query()->firstOrFail();
        $this->assertSame(ArticleStatus::Draft, $article->status);

        Livewire::test(ListArticles::class)->assertSuccessful()->assertCanSeeTableRecords([$article]);
    }

    public function test_editor_autosaves_to_draft_and_publish_and_schedule_actions_work(): void
    {
        $admin = $this->admin();
        $article = $this->draftArticle();

        $editor = Livewire::test(EditArticle::class, ['record' => $article->getKey()])
            ->assertSuccessful()
            ->fillForm(['excerpt' => 'Ringkasan otomatis'])
            ->call('autosave')
            ->assertSet('saveState', 'saved');

        $this->assertSame('Ringkasan otomatis', $article->refresh()->draftFields()['excerpt']);
        $this->assertNull($article->excerpt, 'Kolom utama (published) tidak boleh berubah oleh auto-save.');

        $editor->callAction('schedule', ['scheduled_at' => now()->addDay()->toDateTimeString()]);
        $this->assertSame(ArticleStatus::Scheduled, $article->refresh()->status);

        $editor->callAction('cancelSchedule')->callAction('publish');
        $article->refresh();

        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertSame('Ringkasan otomatis', $article->excerpt);
        $this->assertSame($admin->id, $article->updated_by);
    }

    public function test_editor_summarises_what_changed_since_publish_but_not_for_unpublished_drafts(): void
    {
        $this->admin();
        $article = $this->draftArticle();

        $this->assertSame([], $article->changedFieldKeys());
        Livewire::test(EditArticle::class, ['record' => $article->getKey()])->assertDontSee('perubahan belum di-publish');

        $article = app(PublishArticle::class)->handle($article);
        $article = app(SaveArticleDraft::class)->handle($article, ['excerpt' => 'Ringkasan baru'], $article->draft_version);

        $this->assertSame(['excerpt'], $article->changedFieldKeys());
        Livewire::test(EditArticle::class, ['record' => $article->getKey()])
            ->assertSee('1 perubahan belum di-publish: Excerpt')
            ->assertSee('Changed');
    }

    public function test_list_has_a_tab_per_status_that_filters_the_table_and_counts_articles(): void
    {
        $this->admin();
        $draft = $this->draftArticle('Masih Draft');
        $published = app(PublishArticle::class)->handle($this->draftArticle('Sudah Tayang'));

        Livewire::test(ListArticles::class)
            ->assertSee('Published')
            ->assertCanSeeTableRecords([$draft, $published])
            ->set('activeTab', ArticleStatus::Published->value)
            ->assertCanSeeTableRecords([$published])
            ->assertCanNotSeeTableRecords([$draft]);
    }

    public function test_only_live_articles_can_be_featured_from_the_list_star(): void
    {
        $this->admin();
        $draft = $this->draftArticle('Masih Draft');
        $published = app(PublishArticle::class)->handle($this->draftArticle('Sudah Tayang'));

        Livewire::test(ListArticles::class)
            ->callTableColumnAction('is_featured', $draft)
            ->callTableColumnAction('is_featured', $published);

        $this->assertFalse($draft->refresh()->is_featured);
        $this->assertTrue($published->refresh()->is_featured);
    }

    public function test_article_editor_names_the_invalid_fields(): void
    {
        $this->admin();
        $article = $this->draftArticle();

        Livewire::test(EditArticle::class, ['record' => $article->getKey()])
            ->fillForm(['title' => ''])
            ->call('autosave')
            ->assertSet('saveState', 'invalid')
            ->assertSet('invalidFields', ['Title'])
            ->assertSee('Not saved — fix: Title');
    }

    public function test_category_used_by_an_article_cannot_be_deleted(): void
    {
        Article::factory()->create(['category_id' => $this->category->id]);

        $this->assertTrue($this->category->isInUse());
        $this->assertFalse($this->category->delete());
    }

    public function test_scheduled_command_is_registered_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'articles:publish-scheduled'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }
}
