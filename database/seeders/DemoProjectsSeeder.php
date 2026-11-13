<?php

namespace Database\Seeders;

use App\Actions\Media\AttachMedia;
use App\Actions\Projects\CreateProject;
use App\Actions\Projects\PublishProject;
use App\Actions\Projects\SaveProjectDraft;
use App\Enums\CategoryType;
use App\Models\Category;
use App\Models\Project;
use Database\Seeders\Concerns\SeedsDemoMedia;
use Illuminate\Database\Seeder;

/**
 * Proyek Progressive Architect dari company profile (COMPRO WEBSITE PROGRESSIVE): 6 proyek arsitektur dan 4
 * proyek furnitur (kategori Interior), lengkap dengan lokasi, tahun, dan data teknis seperti yang tertulis di
 * deck. Teks deskripsi diambil dari deck. Dibuat lewat Action yang sama dengan dashboard, jadi alur
 * draft/publish dan log aktivitas ikut terbentuk.
 *
 *   php artisan db:seed --class=DemoProjectsSeeder
 *
 * Foto: bila ada di `foto/progressive/{folder proyek}/` ikut dipasang; bila belum, proyek terbit tanpa foto dan
 * fotonya dapat dipasang belakangan dengan `php artisan db:seed --class=ProjectPhotosSeeder`.
 * Berhenti bila sudah ada proyek, agar konten yang sudah ada tidak tercampur.
 */
class DemoProjectsSeeder extends Seeder
{
    use SeedsDemoMedia;

    /**
     * Urutan = dari yang paling baru terbit. Nilai null = tidak tertulis di deck.
     * `bio`/`client`/`stage` hanya ada untuk proyek yang lembar datanya memuatnya.
     *
     * @var list<array<string, mixed>>
     */
    private const PROJECTS = [
        [
            'name' => 'PS House', 'category' => 'Architecture', 'location' => 'Bekasi, Indonesia', 'year' => 2025,
            'client' => 'Private Client', 'typology' => 'Residential', 'land' => 200, 'building' => 178, 'storeys' => 2, 'stage' => 'Complete',
            'description' => "Located along a narrow residential street, the house is set back from the road to create a generous arrival space that accommodates the carport while establishing a clear transition from the public realm to the home. Surrounded by a neighbourhood of single-storey houses, the building responds to its context through a restrained scale and a carefully considered composition.\n\nThe architecture is defined by a single geometric element: the arch. Rather than functioning as decoration, it provides a consistent architectural order that shapes the building as a whole. The arch frames entrances, windows, and the canopy, before continuing into the interior where it defines circulation and living spaces.\n\nThe material palette is deliberately restrained. Exposed brick and a smooth kamprot wall render are left in their natural state, allowing texture and craftsmanship to define the character of the house. Slender aluminium frames provide a precise contrast to the tactile surfaces.",
        ],
        [
            'name' => 'BY House', 'category' => 'Architecture', 'location' => 'Bekasi, Indonesia', 'year' => 2023,
            'client' => 'Private Client', 'typology' => 'Residential', 'land' => 173.5, 'building' => 160, 'storeys' => 2, 'stage' => 'Complete',
            'description' => "The house was already there. A single storey, its structure still sound. The renovation worked with what existed. The old grid was kept, the new built around it. One side was left open, set back from the boundary. Enough for air to move through.\n\nThe facade is white, largely uninterrupted. A single circular window sits at the front. The only curve on an otherwise flat surface. Timber-finish soffits follow the pitch of the roof, visible from the street. Along the boundary wall, orange brick cladding runs the length of the plot.",
        ],
        [
            'name' => 'AD House', 'category' => 'Architecture', 'location' => 'Bekasi, Indonesia', 'year' => 2025,
            'client' => 'Private Client', 'typology' => 'Residential', 'land' => 129.7, 'building' => 175.8, 'storeys' => 2, 'stage' => 'Complete',
            'description' => "A single-storey house, renovated and rebuilt as two. The building was split along its length, a courtyard placed between the two masses. The plot runs deep. The division made room for light and air to reach both.\n\nTwo planes angle inward, one larger than the other. The asymmetry is intentional. The materials do not pretend: kamprot, exposed concrete, dark timber, brick. The same language carried from outside to in.\n\nThe courtyard sits between the two buildings, open above where the canopy allows. The living room faces it on one side, the kitchen and dining on the other. Brick cladding lines the courtyard walls. A concrete bench runs along one wall. In front of it, space left for planting.",
        ],
        [
            'name' => 'CR House', 'category' => 'Architecture', 'location' => 'Bandung, Indonesia', 'year' => 2025,
            'client' => null, 'typology' => null, 'land' => null, 'building' => null, 'storeys' => null, 'stage' => null,
            'description' => null,
        ],
        [
            'name' => 'FR House', 'category' => 'Architecture', 'location' => 'Bandung, Indonesia', 'year' => 2024,
            'client' => null, 'typology' => 'Residential', 'land' => 70, 'building' => 63.72, 'storeys' => 2, 'stage' => null,
            'description' => "The site measures seventy square metres, backed by open water. On a plot this size, every decision had to count.\n\nTwo volumes rise on the second floor, clad in white tile. Between them, a recessed wall of concrete breeze blocks, open to the air on both sides. The left volume angles slightly toward the road, its slope freed by the void within. Planter boxes follow the geometry, adding green to what could have been wasted space.\n\nThe main bedroom opens to the rear. A door, wide windows, and a balcony, all facing the water. Beyond it, water and open fields stretch without interruption. On a site this constrained, the view was not overlooked.",
        ],
        [
            'name' => 'TW House', 'category' => 'Architecture', 'location' => 'West Jakarta, Indonesia', 'year' => 2024,
            'client' => null, 'typology' => 'Residential', 'land' => 98, 'building' => 145.15, 'storeys' => 2, 'stage' => null,
            'description' => "Located on a bend in the road, the house responds directly to the geometry of its site. Two distinct volumes define the composition: a cantilevered white volume and a recessed volume finished in textured kamprot render. Each is expressed with its own roof orientation, creating a balanced composition that unifies the facade while clearly articulating the different functions within. The cantilever extends over the entrance, providing shelter and establishing a clear point of arrival through the integration of structure and form.\n\nAn internal courtyard separates the living area from the kitchen, bringing daylight and natural ventilation into the centre of the house. The courtyard extends through both levels, creating a vertical void that organises circulation and reinforces visual continuity throughout the house.",
        ],
        [
            'name' => 'LU House', 'category' => 'Interior', 'location' => 'Cimahi, Indonesia', 'year' => 2025,
            'client' => null, 'typology' => 'Furniture', 'land' => null, 'building' => null, 'storeys' => null, 'stage' => null,
            'description' => "This integrated cabinetry explores a contemporary interpretation of tropical design, bringing together natural materials, refined detailing, and functional clarity. A light-toned timber finish establishes a warm, understated palette, while woven rattan panels introduce texture and craftsmanship.\n\nThe design is defined by the relationship between material contrast, geometric order, and precise detailing. Woven rattan, natural timber grain, vertical articulation, and integrated lighting are brought together within a coherent architectural language.",
        ],
        [
            'name' => 'NE House', 'category' => 'Interior', 'location' => 'Bandung, Indonesia', 'year' => 2022,
            'client' => null, 'typology' => 'Furniture', 'land' => null, 'building' => null, 'storeys' => null, 'stage' => null,
            'description' => "The quality of an interior is defined not only by its visual composition, but also by the intelligence with which it accommodates everyday life. Conceived as an integrated architectural element, this bespoke storage cabinet brings together multiple domestic functions within a singular, coherent form.\n\nBehind the seamless design, a carefully organised storage system accommodates a water dispenser and gallon bottle, a foldable mattress, a coffee maker, a vacuum cleaner, and other household essentials.",
        ],
        [
            'name' => 'TD House', 'category' => 'Interior', 'location' => 'Cimahi, Indonesia', 'year' => 2023,
            'client' => null, 'typology' => 'Furniture', 'land' => null, 'building' => null, 'storeys' => null, 'stage' => null,
            'description' => 'Defined by material clarity and purposeful detailing, this kitchen reflects a contemporary approach to simplicity, functionality, and spatial harmony. At its centre, a multifunctional island establishes a natural point of connection between food preparation, dining, and social interaction.',
        ],
        [
            'name' => 'BV House', 'category' => 'Interior', 'location' => 'Cikarang, Indonesia', 'year' => 2024,
            'client' => null, 'typology' => 'Furniture', 'land' => null, 'building' => null, 'storeys' => null, 'stage' => null,
            'description' => "This kitchen is conceived as a study in material contrast, spatial clarity, and contemporary living. Oak-inspired cabinetry establishes a warm, natural foundation, complemented by sage green upper cabinets that introduce a restrained yet distinctive chromatic layer.\n\nThe cabinetry is organised around efficiency and visual continuity, integrating concealed storage with open shelving to accommodate everyday objects within a considered composition.",
        ],
    ];

    public function run(): void
    {
        if (Project::query()->exists()) {
            $this->command?->warn('Sudah ada proyek. DemoProjectsSeeder dilewati agar konten yang ada tidak tercampur.');

            return;
        }

        $categories = collect(['Architecture', 'Construction', 'Interior'])->mapWithKeys(fn (string $name) => [
            $name => Category::query()->firstOrCreate(['type' => CategoryType::Project->value, 'name' => $name]),
        ]);

        $withPhotos = 0;

        foreach (self::PROJECTS as $i => $data) {
            // Terbit bertahap; yang pertama dalam daftar adalah yang terbaru (urutan listing = terbaru dulu).
            $this->atDaysAgo(($i + 1) * 14, function () use ($data, $categories, &$withPhotos): void {
                $project = app(CreateProject::class)->handle(['name' => $data['name'], 'category_id' => $categories[$data['category']]->id], auth()->id());

                $project = app(SaveProjectDraft::class)->handle($project, [
                    'location' => $data['location'],
                    'year' => $data['year'],
                    'description' => $data['description'],
                    'client' => $data['client'],
                    'typology' => $data['typology'],
                    'land_area' => $data['land'],
                    'building_area' => $data['building'],
                    'storeys' => $data['storeys'],
                    'stage' => $data['stage'],
                ], $project->draft_version, auth()->id());

                if ($this->attachPhotos($project)) {
                    $withPhotos++;
                }

                app(PublishProject::class)->handle($project->fresh(), auth()->id());
            });
        }

        $this->command?->info(count(self::PROJECTS)." proyek dibuat ({$withPhotos} dengan foto dari foto/progressive).");
    }

    /** Pasang foto folder proyek (bila ada) sebagai cover dan galeri draft. */
    private function attachPhotos(Project $project): bool
    {
        $photos = $this->projectPhotos($project->slug);

        if ($photos === []) {
            return false;
        }

        $name = $project->draftFields()['name'];

        foreach ($photos as $n => $file) {
            app(AttachMedia::class)->handle($file, $project, $n === 0 ? Project::ROLE_COVER : Project::ROLE_GALLERY, [
                'alt_text' => $n === 0 ? "Tampak utama {$name}" : "{$name}, foto {$n}",
                'sort_order' => $n,
            ]);
        }

        return true;
    }
}
