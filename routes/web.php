<?php

use App\Http\Controllers\ApplicationFileController;
use App\Http\Controllers\Site\AboutController;
use App\Http\Controllers\Site\ArticleController;
use App\Http\Controllers\Site\CareerController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\ProjectController;
use App\Http\Controllers\Site\SearchController;
use App\Http\Controllers\Site\SeoController;
use Illuminate\Support\Facades\Route;

// Homepage = daftar proyek (SRS 3.2). Kategori dipilih lewat ?category=slug agar fallback tanpa JavaScript
// tetap bekerja. Pola URL kategori belum diputuskan (SRS Bagian 15 butir 1).
Route::get('/', [ProjectController::class, 'index'])->name('home');
Route::get('/projects/{slug}', [ProjectController::class, 'show'])->name('projects.show');
Route::get('/preview/projects/{project}', [ProjectController::class, 'preview'])->name('projects.preview');

Route::get('/about', [AboutController::class, 'show'])->name('about');
Route::get('/team', [AboutController::class, 'team'])->name('team');
Route::get('/preview/about', [AboutController::class, 'preview'])->name('about.preview');

// Formulir kontak (hanya email, SRS 3.8). Pembatasan laju ada di RateLimiter 'contact'.
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'send'])->middleware('throttle:contact')->name('contact.send');

// Careers (SRS 3.7): daftar, detail, dan lamaran tanpa akun. Pembatasan laju ada di RateLimiter 'apply'.
Route::get('/careers', [CareerController::class, 'index'])->name('careers');
Route::get('/careers/{vacancy:slug}', [CareerController::class, 'show'])->name('careers.show');
Route::post('/careers/{vacancy:slug}', [CareerController::class, 'apply'])->middleware('throttle:apply')->name('careers.apply');

// Dokumen lamaran bersifat privat (SRS FR-RC-036): hanya administrator aktif, tidak pernah lewat URL publik.
Route::get('/admin/application-files/{file}', ApplicationFileController::class)->name('admin.application-files.show');

// SEO otomatis: sitemap dan robots dibuat dari isi website (tidak ada pengaturan SEO di dashboard).
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

// News & Articles (SRS 3.6): listing editorial, detail, dan preview draft untuk admin.
Route::get('/news', [ArticleController::class, 'index'])->name('news');
Route::get('/news/{slug}', [ArticleController::class, 'show'])->name('news.show');
Route::get('/preview/news/{article}', [ArticleController::class, 'preview'])->name('news.preview');

// Pencarian website (SRS 3.4): halaman hasil lengkap dan fragmen live untuk overlay. Pembatasan laju: RateLimiter 'search'.
Route::get('/search', [SearchController::class, 'index'])->middleware('throttle:search')->name('search');
Route::get('/search/live', [SearchController::class, 'live'])->middleware('throttle:search')->name('search.live');
