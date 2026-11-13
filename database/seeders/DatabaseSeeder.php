<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Konten demo hanya untuk lingkungan lokal (foto asli dari folder `foto/`, lowongan, artikel, dan seterusnya).
        if (app()->environment('local')) {
            $this->call(DemoSiteSeeder::class);
        }
    }
}
