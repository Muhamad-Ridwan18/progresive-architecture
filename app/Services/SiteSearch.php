<?php

namespace App\Services;

use App\Models\Article;
use App\Models\JobVacancy;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Pencarian website publik (SRS 3.4): Projects, News & Articles, Careers.
 *
 * Hanya konten yang tampil di website (versi published) yang dicari; draft, Scheduled, dan lowongan Draft
 * tidak pernah muncul. Memakai LIKE agar bekerja sama di semua database; cukup untuk skala website company
 * profile. Isi artikel (HTML) sengaja tidak dicari: potongan tag seperti "li" atau "br" akan cocok di
 * hampir semua artikel.
 */
class SiteSearch
{
    public const TYPES = ['projects', 'articles', 'careers'];

    public const MIN_LENGTH = 2;

    /**
     * @param  list<string>|null  $types  batasi jenis konten; null = semua
     * @return array{projects: Collection<int, array<string, string>>, articles: Collection<int, array<string, string>>, careers: Collection<int, array<string, string>>}
     */
    public function search(string $query, int $limit = 5, ?array $types = null): array
    {
        $query = trim($query);
        $types ??= self::TYPES;
        $empty = collect();

        if (mb_strlen($query) < self::MIN_LENGTH) {
            return ['projects' => $empty, 'articles' => $empty, 'careers' => $empty];
        }

        $like = '%'.$this->escapeLike($query).'%';

        return [
            'projects' => in_array('projects', $types, true) ? $this->projects($like, $limit) : $empty,
            'articles' => in_array('articles', $types, true) ? $this->articles($like, $limit) : $empty,
            'careers' => in_array('careers', $types, true) ? $this->careers($like, $limit) : $empty,
        ];
    }

    /** @return Collection<int, array<string, string>> */
    private function projects(string $like, int $limit): Collection
    {
        return Project::query()
            ->live()
            ->with('category')
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', $like)
                ->orWhere('location', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like)))
            ->inSiteOrder()
            ->limit($limit)
            ->get()
            ->map(fn (Project $p): array => [
                'title' => $p->name,
                'meta' => collect([$p->category?->name, $p->location, $p->year])->filter()->implode(' · '),
                'url' => route('projects.show', $p->slug),
            ]);
    }

    /** @return Collection<int, array<string, string>> */
    private function articles(string $like, int $limit): Collection
    {
        return Article::query()
            ->live()
            ->with('category')
            ->where(fn (Builder $q) => $q
                ->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like)))
            ->inSiteOrder()
            ->limit($limit)
            ->get()
            ->map(fn (Article $a): array => [
                'title' => $a->title,
                'meta' => collect([$a->category?->name, $a->published_at?->translatedFormat('d M Y')])->filter()->implode(' · '),
                'url' => route('news.show', $a->slug),
            ]);
    }

    /** @return Collection<int, array<string, string>> */
    private function careers(string $like, int $limit): Collection
    {
        return JobVacancy::query()
            ->visibleToPublic()
            ->where(fn (Builder $q) => $q
                ->where('title', 'like', $like)
                ->orWhere('department', 'like', $like)
                ->orWhere('location', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('qualifications', 'like', $like))
            ->latest('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (JobVacancy $v): array => [
                'title' => $v->title,
                'meta' => collect([
                    $v->location,
                    $v->employment_type,
                    $v->isOpen()
                        ? ($v->deadline ? 'Deadline '.$v->deadline->translatedFormat('d M Y') : null)
                        : 'Closed',
                ])->filter()->implode(' · '),
                'url' => route('careers.show', $v->slug),
            ]);
    }

    /** Karakter khusus LIKE (% dan _) dari pengguna diperlakukan sebagai teks biasa. */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
