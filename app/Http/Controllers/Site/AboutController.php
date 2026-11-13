<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AboutPage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AboutController extends Controller
{
    /** Halaman About Us (SRS 3.5). Hanya versi published; belum terbit = halaman penanda. */
    public function show(Request $request): Response
    {
        // Pembacaan publik tidak boleh menulis ke database, jadi tidak memakai AboutPage::current().
        $page = AboutPage::query()->find(1);
        $content = $page?->publishedContent();

        if ($content === null) {
            return response()->view('site.coming-soon', ['title' => 'About Us']);
        }

        return response()->view('site.about', [
            'content' => $content,
            'photos' => $page->teamPhotos(draft: false),
            'visionPhotos' => $page->visionPhotos(draft: false),
            'preview' => false,
        ] + $this->teamData($request, $content['team']));
    }

    /** Halaman Team lama dialihkan ke bagian Team di halaman About. */
    public function team(): RedirectResponse
    {
        return redirect(route('about').'#team', 301);
    }

    /**
     * Anggota tim untuk bagian Team di About: semua profil dikirim (awalnya hanya kartu yang tampil dan profil
     * dibuka dengan halus saat kartu diklik, js/team.js). `selected` null bila belum ada pilihan lewat ?member=id.
     *
     * @param  list<array<string, mixed>>  $team
     * @return array<string, mixed>
     */
    private function teamData(Request $request, array $team): array
    {
        $members = collect($team);

        return [
            'members' => $members,
            'selected' => $members->firstWhere('id', $request->query('member')),
        ];
    }

    /** Preview draft untuk administrator (SRS 5.4). Tidak pernah dapat diakses publik atau diindeks. */
    public function preview(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->is_active, 404);

        $page = AboutPage::current();

        return response()
            ->view('site.about', [
                'content' => $page->draftContent(),
                'photos' => $page->teamPhotos(draft: true),
                'visionPhotos' => $page->visionPhotos(draft: true),
                'preview' => true,
            ] + $this->teamData($request, $page->draftContent()['team']))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private');
    }
}
