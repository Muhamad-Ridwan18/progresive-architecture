<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\Settings;
use Illuminate\Database\Seeder;

/**
 * Pengaturan website dari company profile (SRS Bagian 8): identitas dan kontak (SEO dibuat otomatis, tidak ada pengaturannya). Hanya mengisi kunci yang
 * masih kosong; nilai yang sudah diatur di dashboard tidak pernah ditimpa. Jam kerja dan media sosial tidak ada
 * di deck, jadi dibiarkan kosong untuk diisi lewat Website Settings.
 */
class DemoSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'site_name' => 'Progressive Architect',
            'tagline' => 'Expression of life. Tropical future. Progressive living.',
            'company_name' => 'Progressive Architect',
            'company_description' => 'Progressive Architect is a multidisciplinary design studio founded in 2019 and based in Bandung, Indonesia, rooted in a Tropical Modern design approach and integrating architecture, construction, and interior design.',
            'company_address' => "Suwarna Mas Residence No.15, Cikoneng street,\nBojong Soang, West Java 40288",
            'contact_email' => 'progressivearchitectstudio@gmail.com',
            'contact_phone' => '+62 821-1743-8719',
            'careers_intro' => "At Progressive Architect, we believe that great architecture is created through continuous learning, collaboration, and exploration.\n\nBased in Bandung, Indonesia, our office is located at Suwarna Mas Residence No. 15, Kabupaten Bandung. Since our establishment, we have successfully delivered more than 110 architectural design projects, 40 residential and healthcare construction projects, and over 15 interior design projects.\n\nWe are looking for passionate, motivated, and talented individuals who are eager to grow, contribute, and shape meaningful spaces through architecture and design.",
        ];

        $existing = Setting::query()->pluck('value', 'key');
        $missing = collect($defaults)->reject(fn ($value, string $key) => filled($existing[$key] ?? null))->all();

        Settings::setMany($missing);

        $this->command?->info(count($missing).' pengaturan website diisi.');
    }
}
