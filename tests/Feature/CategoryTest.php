<?php

namespace Tests\Feature;

use App\Actions\Projects\CreateProject;
use App\Enums\CategoryType;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create());
    }

    private function make(string $name, CategoryType $type = CategoryType::Project, array $extra = []): Category
    {
        return Category::create(['type' => $type, 'name' => $name, ...$extra]);
    }

    public function test_slug_and_sort_order_are_generated(): void
    {
        $a = $this->make('Commercial Buildings');
        $b = $this->make('Residential');

        $this->assertSame('commercial-buildings', $a->slug);
        $this->assertSame(1, $a->sort_order);
        $this->assertSame(2, $b->sort_order);
    }

    public function test_slug_is_unique_per_type_but_may_repeat_across_types(): void
    {
        $p1 = $this->make('Industrial');
        $p2 = $this->make('Industrial');
        $article = $this->make('Industrial', CategoryType::Article);

        $this->assertSame('industrial', $p1->slug);
        $this->assertSame('industrial-2', $p2->slug);
        $this->assertSame('industrial', $article->slug);
        // Urutan dihitung per jenis, bukan global.
        $this->assertSame(1, $article->sort_order);
    }

    public function test_tabs_only_show_categories_of_their_type(): void
    {
        $project = $this->make('Infrastructure');
        $article = $this->make('Company News', CategoryType::Article);

        Livewire::test(ListCategories::class)
            ->assertCanSeeTableRecords([$project])
            ->assertCanNotSeeTableRecords([$article])
            ->set('activeTab', 'article')
            ->assertCanSeeTableRecords([$article])
            ->assertCanNotSeeTableRecords([$project]);
    }

    public function test_category_can_be_created_from_the_form(): void
    {
        Livewire::test(CreateCategory::class)
            ->fillForm(['type' => 'article', 'name' => 'Press Release'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('categories', ['type' => 'article', 'name' => 'Press Release', 'slug' => 'press-release']);
    }

    public function test_duplicate_slug_in_same_type_is_rejected_by_the_form(): void
    {
        $this->make('Housing');

        Livewire::test(CreateCategory::class)
            ->fillForm(['type' => 'project', 'name' => 'Housing Two', 'slug' => 'housing'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_reordering_updates_sort_order(): void
    {
        $a = $this->make('A');
        $b = $this->make('B');
        $c = $this->make('C');

        Livewire::test(ListCategories::class)
            ->call('reorderTable', [$c->id, $a->id, $b->id]);

        $this->assertSame(
            [$c->id, $a->id, $b->id],
            Category::query()->orderBy('sort_order')->pluck('id')->all(),
        );
    }

    public function test_category_in_use_cannot_be_deleted_until_content_is_moved(): void
    {
        $category = $this->make('Bridges');
        $other = $this->make('Roads');
        $target = $this->make('Tunnels');

        $project = app(CreateProject::class)->handle(['name' => 'Bridge A', 'category_id' => $category->id]);

        $this->assertTrue($category->isInUse());
        $this->assertSame(1, $category->usageCount());
        $this->assertFalse($category->delete());
        $this->assertDatabaseHas('categories', ['id' => $category->id]);

        // Pengaman kedua di database: penghapusan langsung (melewati model) pun ditolak.
        try {
            DB::table('categories')->where('id', $category->id)->delete();
            $this->fail('Foreign key seharusnya menolak penghapusan kategori yang dipakai.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        // Kategori lain yang tidak dipakai tetap bisa dihapus.
        $this->assertTrue($other->delete());

        // Setelah konten dipindahkan ke kategori lain, kategori bisa dihapus.
        $project->forceFill(['category_id' => $target->id])->save();
        $this->assertTrue($category->fresh()->delete());
    }

    public function test_category_changes_are_logged_without_reorder_noise(): void
    {
        $category = $this->make('Hospitals');
        $category->update(['name' => 'Healthcare']);
        $category->update(['sort_order' => 9]);

        $this->assertSame(2, Activity::query()->where('log_name', 'category')->count());
    }
}
