<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Batas unggahan sementara Livewire (bawaan 12 MB) disamakan dengan batas gambar SRS (50 MB).
        // Pada server produksi, upload_max_filesize dan post_max_size PHP juga harus >= 50M.
        config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.config('media.max_size_kb')]]);

        // Pembatasan formulir kontak per IP (SRS NFR-SEC-005): 5 per menit dan 20 per jam.
        // Kiriman yang gagal validasi juga dihitung, jadi batasnya sengaja cukup longgar agar
        // pengguna sungguhan yang beberapa kali salah isi tidak terkunci.
        RateLimiter::for('contact', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(20)->by($request->ip()),
        ]);

        // Pembatasan pengiriman lamaran per IP. Dokumen lamaran besar, jadi lebih ketat dari formulir kontak.
        RateLimiter::for('apply', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perHour(15)->by($request->ip()),
        ]);

        // Live search mengirim satu permintaan per jeda ketikan; 60 per menit per IP cukup untuk manusia.
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Data situs (Website Settings, SRS Bagian 8) untuk navbar, footer, dan metadata di layout publik.
        // Juga untuk halaman `site.*`: isi slot dirender di luar cakupan variabel layout.
        View::composer(['components.layouts.app', 'site.*'], function ($view): void {
            $s = Settings::all();
            $public = fn (?string $path): ?string => $path ? Storage::disk('public')->url($path) : null;

            $social = collect([
                'Instagram' => $s['social_instagram'] ?? null,
                'LinkedIn' => $s['social_linkedin'] ?? null,
                'Facebook' => $s['social_facebook'] ?? null,
            ])->merge(
                collect($s['social_other'] ?? [])->pluck('url', 'platform')
            )->filter()->all();

            $view->with('site', [
                'name' => $s['site_name'] ?? config('app.name'),
                'tagline' => $s['tagline'] ?? null,
                'logo' => $public($s['logo'] ?? null),
                'favicon' => $public($s['favicon'] ?? null),
                'company_name' => $s['company_name'] ?? null,
                'company_address' => $s['company_address'] ?? null,
                'contact_email' => $s['contact_email'] ?? null,
                'contact_phone' => $s['contact_phone'] ?? null,
                'office_hours' => $s['office_hours'] ?? null,
                'company_description' => $s['company_description'] ?? null,
                // Gambar berbagi bawaan (Open Graph) dipilih otomatis: cover proyek terbaru yang sudah tayang.
                'fallback_image' => Cache::remember('seo.fallback_image', 600, function (): ?string {
                    $project = Project::query()->live()->inSiteOrder()->first();

                    return $project?->cover(draft: false)?->file->url(1200);
                }),
                'social' => $social,
                // Tab kategori proyek di header (hanya kategori yang punya proyek published).
                'categories' => Category::query()
                    ->ofType(Project::categoryType())
                    ->whereHas('projects', fn ($q) => $q->live())
                    ->orderBy('sort_order')
                    ->get(['id', 'name', 'slug']),
            ]);
        });

        // Catat waktu login terakhir (SRS 6.2 - User Table: Last Login).
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }
}
