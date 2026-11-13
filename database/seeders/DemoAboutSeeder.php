<?php

namespace Database\Seeders;

use App\Actions\About\PublishAbout;
use App\Actions\About\SaveAboutDraft;
use App\Models\AboutPage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Halaman About Us dan Team dari company profile: profil studio, visi, tiga semboyan, angka pencapaian, dan 8
 * anggota tim dengan jabatan (bio lengkap hanya ada untuk pendiri di deck). Foto anggota tim dipasang lewat
 * dashboard (About Us, Our Team). Dilewati bila halaman sudah berisi.
 */
class DemoAboutSeeder extends Seeder
{
    public function run(): void
    {
        $page = AboutPage::current();

        if ($this->hasContent($page)) {
            $this->command?->warn('About Us sudah berisi. DemoAboutSeeder dilewati.');

            return;
        }

        $founderBio = "Hilmi Haerul Umam is an Indonesian architect and design entrepreneur born in Bandung, 1996. He graduated with a Bachelor’s degree in Architecture from Institut Teknologi Nasional at 2017, where he developed a strong foundation in architectural design, spatial thinking, and the relationship between people and the built environment.\n\n"
            ."During his academic years, Hilmi actively participated in numerous architecture and interior design competitions, demonstrating an early commitment to design excellence and innovation. His work was recognized as one of the Top 25 Nominees in the SOHO Interior Design Competition in 2017. In 2016, he was awarded Third Prize in the Panatayuda Public Open Space Design Competition in Bandung, reflecting his interest in creating meaningful public environments and human-centered spaces.\n\n"
            ."Following graduation, Hilmi gained professional experience in both architecture and interior design, working on a diverse range of residential, commercial, and hospitality projects. These formative years allowed him to refine his design methodology, strengthen his technical expertise, and develop a comprehensive understanding of project delivery from concept to completion.\n\n"
            ."In 2019, driven by a vision to create architecture that is responsive, contextual, and enduring, Hilmi founded Progressive Architect, an independent design practice based in Bandung, Indonesia. Under his leadership, the firm has established a distinctive design approach that integrates tropical modern architecture, functional planning, and thoughtful spatial experiences tailored to contemporary lifestyles.\n\n"
            .'As Principal Architect, Hilmi believes that architecture should extend beyond aesthetics and function. For him, architecture serves as a framework that shapes the way people live, interact, and connect with their surroundings. Through Progressive Architect, he continues to explore innovative design solutions that balance creativity, practicality, and environmental responsiveness, delivering spaces that are both timeless and deeply relevant to their context.';

        $team = collect([
            ['Hilmi Haerul Umam', 'Founder', $founderBio],
            ['Nur Annisa Hidayati Zaenudin', 'Managing Director of Design', null],
            ['Nisa Yasyfa Faridan', 'Architect', null],
            ['Aldy Rifky Fahrurrozy', 'Architect', null],
            ['Reyan Rizki Ramadhan', 'Drafter', null],
            ['Wizdan Nur', 'Interior', null],
            ['Ryan Rusdiana', 'Supervisor', null],
            ['Haris Setia Putra', 'Supervisor', null],
        ])->map(fn (array $m): array => ['id' => Str::lower(Str::random(12)), 'name' => $m[0], 'position' => $m[1], 'bio' => $m[2]]);

        $page = app(SaveAboutDraft::class)->handle($page, [
            'overview' => [
                'heading' => 'Expression of life',
                'body' => "Progressive Architect is a multidisciplinary design studio founded in 2019 by Hilmi Haerul Umam and based in Bandung, Indonesia. Rooted in a Tropical Modern design approach, the studio creates works that balance functionality, comfort, and contextual architectural expression. Each project is approached as a response to the way people live, translated through a thoughtful composition of space, proportion, materials, and light.\n\n"
                    ."From the very beginning, Progressive Architect has believed that the quality of a project is defined not only by the strength of its design, but also by how precisely it is realized. Guided by this principle, the studio established an integrated practice that combines architecture and construction within a continuous process, ensuring that every idea is executed with accuracy, integrity, and attention to detail.\n\n"
                    ."As the practice evolved, Progressive Architect expanded into furniture design and production in its fifth year of operation. This expansion strengthened the studio’s ability to deliver a more comprehensive design experience, extending from the scale of architecture to the finer details that shape everyday interactions within a space.\n\n"
                    .'Today, Progressive Architect operates as an integrated design ecosystem encompassing architecture, construction, and interior design. Through a holistic approach, each project is conceived not only to fulfill functional requirements, but also to create spaces that are meaningful, enduring, and timeless.',
            ],
            'history' => [
                'body' => null,
                'milestones' => [
                    ['year' => '2019', 'title' => 'Founded in Bandung', 'description' => 'Hilmi Haerul Umam founds Progressive Architect as an independent design practice.'],
                    ['year' => '2024', 'title' => 'Furniture design and production', 'description' => 'In its fifth year of operation, the studio expands into furniture design and production.'],
                ],
            ],
            'vision_mission' => [
                'vision' => 'We believe architecture is more than a vessel for human activities, it is a framework that shapes the way people live. Progressive Architect strives to redefine living through architecture that is adaptive, contextual, and designed to create lasting value.',
                'mission' => null,
            ],
            'values' => [
                ['title' => 'Expression of life', 'description' => null],
                ['title' => 'Tropical future', 'description' => null],
                ['title' => 'Progressive living', 'description' => null],
            ],
            'statistics' => [
                ['value' => '110+', 'label' => 'Architectural design projects'],
                ['value' => '40', 'label' => 'Residential and healthcare construction projects'],
                ['value' => '15+', 'label' => 'Interior design projects'],
            ],
            'team' => $team->all(),
        ], $page->draft_version, auth()->id());

        app(PublishAbout::class)->handle($page->fresh(), auth()->id());

        $this->command?->info('Halaman About Us dan Team dibuat (8 anggota tim).');
    }

    /** Apakah halaman sudah berisi (judul atau uraian overview) atau pernah dipublikasikan? */
    private function hasContent(AboutPage $page): bool
    {
        $overview = $page->draftContent()['overview'];

        return filled($overview['heading']) || filled($overview['body']) || $page->published !== null;
    }
}
