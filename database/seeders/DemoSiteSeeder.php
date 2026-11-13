<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Mengisi website dengan konten demo agar terlihat hidup: pengaturan, proyek (foto asli dari `foto/`),
 * About Us dengan tim, artikel, dan rekrutmen lengkap dengan lamaran.
 *
 *   php artisan db:seed --class=DemoSiteSeeder
 *
 * Aman dijalankan ulang: setiap bagian dilewati bila sudah ada kontennya, dan pengaturan yang sudah diisi
 * tidak ditimpa. Seeder tidak membuat akun; tindakan dicatat atas nama administrator pertama.
 * Gambar diproses langsung (tanpa worker), jadi proses ini memakan waktu beberapa detik.
 */
class DemoSiteSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->orderBy('id')->first();

        if ($admin === null) {
            $this->command?->error('Belum ada administrator. Buat akun dulu (mis. lewat tinker atau registrasi admin), lalu jalankan ulang.');

            return;
        }

        // Log aktivitas dan penulis konten mengatasnamakan administrator, seperti bila ia mengerjakannya sendiri.
        auth()->setUser($admin);

        $this->call([
            DemoSettingsSeeder::class,
            DemoProjectsSeeder::class,
            DemoAboutSeeder::class,
            DemoArticlesSeeder::class,
            DemoCareersSeeder::class,
            DemoNotificationsSeeder::class,
        ]);
    }
}
