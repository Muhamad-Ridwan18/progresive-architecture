<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\JobApplication;
use App\Models\Project;
use App\Models\User;
use App\Services\AdminNotifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Notifikasi dashboard demo (SRS Bagian 10): lamaran terbaru, artikel dan proyek terbit, supaya lonceng
 * di topbar tidak kosong. Notifikasi sungguhan dikirim otomatis saat lamaran masuk atau konten dipublikasikan;
 * seeder ini hanya mengisi riwayat awal. Dilewati bila administrator sudah punya notifikasi.
 */
class DemoNotificationsSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('notifications')->exists()) {
            $this->command?->warn('Sudah ada notifikasi. DemoNotificationsSeeder dilewati.');

            return;
        }

        $notifier = app(AdminNotifier::class);

        // Dari yang terlama ke terbaru agar urutan di lonceng masuk akal.
        if ($project = Project::query()->live()->latest('published_at')->first()) {
            $notifier->projectPublished($project, false);
        }

        if ($article = Article::query()->live()->latest('published_at')->first()) {
            $notifier->articlePublished($article, false);
        }

        JobApplication::query()->with('vacancy')->latest('submitted_at')->take(3)->get()->reverse()
            ->each(fn (JobApplication $application) => $notifier->newApplication($application));

        // Dua yang tertua dianggap sudah dibaca; sisanya tampil sebagai belum dibaca di badge lonceng.
        User::query()->where('is_active', true)->each(
            fn (User $user) => $user->notifications()->oldest()->take(2)->get()->each->markAsRead()
        );

        $this->command?->info('Notifikasi demo dibuat.');
    }
}
