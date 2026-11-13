<?php

namespace App\Http\Controllers\Site;

use App\Actions\Recruitment\SubmitApplication;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\JobCategory;
use App\Models\JobVacancy;
use App\Services\Settings;
use App\Support\ApplicationForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Halaman Careers publik (SRS 3.7): daftar lowongan, detail, dan formulir lamaran tanpa akun.
 */
class CareerController extends Controller
{
    /** Filter lewat query string (`?category=slug&status=open|closed`) agar bekerja tanpa JavaScript. */
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['open', 'closed'], true) ? $request->query('status') : null;
        $categorySlug = $request->query('category');

        $categories = JobCategory::query()
            ->whereHas('vacancies', fn ($q) => $q->visibleToPublic())
            ->orderBy('sort_order')
            ->get();

        $vacancies = JobVacancy::query()
            ->visibleToPublic()
            ->with('category')
            ->when($categorySlug, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $categorySlug)))
            ->latest('published_at')
            ->get()
            ->when($status !== null, fn ($list) => $list->filter(
                fn (JobVacancy $vacancy): bool => $vacancy->isOpen() === ($status === 'open'),
            ))
            // Lowongan terbuka di atas, yang sudah tutup di bawah.
            ->sortBy(fn (JobVacancy $vacancy): int => $vacancy->isOpen() ? 0 : 1)
            ->values();

        return view('site.careers.index', [
            'vacancies' => $vacancies,
            'categories' => $categories,
            'activeCategory' => $categorySlug,
            'activeStatus' => $status,
            // Tab News di halaman Careers (Careers berada di bawah News pada company profile).
            'newsCategories' => Category::query()->ofType(Article::categoryType())->whereHas('articles', fn ($q) => $q->live())->orderBy('sort_order')->get(),
            'intro' => Settings::get('careers_intro'),
        ]);
    }

    public function show(JobVacancy $vacancy): View
    {
        $this->ensureVisible($vacancy);

        return view('site.careers.show', [
            'vacancy' => $vacancy->load('category'),
            'fields' => app(SubmitApplication::class)->formFor($vacancy),
        ]);
    }

    public function apply(Request $request, JobVacancy $vacancy, SubmitApplication $submit): RedirectResponse
    {
        $this->ensureVisible($vacancy);

        // Kolom jebakan: manusia tidak melihatnya. Bot diberi "sukses" palsu.
        if (filled($request->input('website_url'))) {
            return redirect()->route('careers.show', $vacancy);
        }

        // Lowongan Closed atau lewat deadline tidak menerima lamaran (SRS FR-FE-086).
        if (! $vacancy->isOpen()) {
            return redirect()->route('careers.show', $vacancy)
                ->withErrors(['form' => 'Lowongan ini sudah ditutup dan tidak menerima lamaran.']);
        }

        $fields = $submit->formFor($vacancy);
        $validation = ApplicationForm::validation($fields);

        $validated = Validator::make(
            $request->all(),
            [...$validation['rules'], 'consent' => ['accepted']],
            ['consent.accepted' => 'Anda perlu menyetujui pemrosesan data untuk keperluan rekrutmen.'],
            [...$validation['attributes'], 'consent' => 'persetujuan'],
        )->validate();

        $application = $submit->handle(
            $vacancy,
            $validated['answers'] ?? [],
            $validated['files'] ?? [],
        );

        return redirect()
            ->route('careers.show', $vacancy)
            ->with('application_reference', $application->reference_number);
    }

    /** Draft tidak pernah tampil di publik; alamatnya pun dibuat seolah tidak ada. */
    private function ensureVisible(JobVacancy $vacancy): void
    {
        abort_unless($vacancy->status->value !== 'draft', 404);
    }
}
