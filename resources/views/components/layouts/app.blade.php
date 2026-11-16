@props(['title' => null, 'description' => null, 'image' => null, 'noindex' => false])

@php
    // SEO otomatis (tidak ada pengaturannya di dashboard): judul, deskripsi, gambar berbagi, canonical, dan data
    // terstruktur diturunkan dari isi halaman dan pengaturan identitas website.
    $siteName = $site['name'];
    $plain = fn (?string $text): ?string => filled($text) ? trim(preg_replace('/\s+/', ' ', strip_tags($text))) : null;
    $categoryNames = collect($site['categories'])->pluck('name')->implode(', ');
    $fullTitle = $title ? $title.' — '.$siteName : ($categoryNames ? $siteName.' — '.$categoryNames : $siteName);
    $metaDescription = $plain($description) ?: $plain($site['company_description']) ?: $plain($site['tagline']);
    $metaDescription = $metaDescription ? \Illuminate\Support\Str::limit($metaDescription, 157) : null;
    $ogImage = $image ?: $site['logo'] ?: $site['fallback_image'];
    $ogImage = $ogImage ? url($ogImage) : null;
    $canonical = url()->current();

    $organization = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $site['company_name'] ?: $siteName,
        'url' => url('/'),
        'logo' => $site['logo'] ? url($site['logo']) : null,
        'email' => $site['contact_email'],
        'telephone' => $site['contact_phone'],
        'address' => $site['company_address'] ? ['@type' => 'PostalAddress', 'streetAddress' => preg_replace('/\s+/', ' ', $site['company_address'])] : null,
        'sameAs' => array_values($site['social']) ?: null,
    ]);

    // Menu mengikuti company profile: Home, About, News, Contact (Team ada di dalam About). Careers berada di bawah News
    // (tab pada halaman News), tetapi tetap dapat dibuka langsung di /careers.
    $menu = [
        ['Home', route('home'), request()->routeIs('home', 'projects.*')],
        ['About', route('about'), request()->routeIs('about')],
        ['News', route('news'), request()->routeIs('news', 'news.*', 'careers', 'careers.*')],
        ['Contact', route('contact'), request()->routeIs('contact')],
    ];

    // Wordmark dua baris: kata pertama di atas, sisanya di bawah ("Progressive / Architect").
    $brandLines = explode(' ', $siteName, 2);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>document.documentElement.className = 'js';</script>

    <title>{{ $fullTitle }}</title>
    @if ($metaDescription)
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if ($noindex)
        <meta name="robots" content="noindex, nofollow">
    @else
        <link rel="canonical" href="{{ $canonical }}">
    @endif

    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
    @if ($metaDescription)
        <meta property="og:description" content="{{ $metaDescription }}">
    @endif
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $fullTitle }}">
    @if ($metaDescription)
        <meta name="twitter:description" content="{{ $metaDescription }}">
    @endif
    @if ($ogImage)
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif

    @unless ($noindex)
        <script type="application/ld+json">{!! json_encode($organization, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    @endunless

    @if ($site['favicon'])
        <link rel="icon" href="{{ $site['favicon'] }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:bg-ink focus:px-4 focus:py-2 focus:text-white">
        Skip to content
    </a>

    {{-- Navbar sticky (SRS FR-FE-001), mengikuti mockup: wordmark di kiri, tab kategori proyek di tengah
         (baris sendiri di bawahnya pada layar kecil), pencarian dan menu di kanan. Tab dipakai juga oleh
         js/projects.js untuk berpindah kategori tanpa reload di halaman utama (SRS FR-FE-012..015). --}}
    <header class="sticky top-0 z-40 border-b border-line bg-bg/95 backdrop-blur">
        <div class="container-page relative flex flex-wrap items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center" style="height: var(--header-height)" aria-label="{{ $siteName }} — home">
                @if ($site['logo'])
                    <img src="{{ $site['logo'] }}" alt="{{ $siteName }}" class="h-8 w-auto">
                @else
                    <span class="text-[0.75rem] font-medium uppercase leading-[1.3] tracking-[0.16em]">
                        {{ $brandLines[0] }}@if (isset($brandLines[1]))<br>{{ $brandLines[1] }}@endif
                    </span>
                @endif
            </a>

            @if ($site['categories']->isNotEmpty())
                @php $activeCategory = request()->routeIs('home') ? request()->query('category') : null; @endphp
                <nav id="category-bar" aria-label="Project categories" data-home="{{ route('home') }}"
                     class="category-bar order-3 -mx-6 w-[calc(100%+3rem)] border-t border-line px-6 md:absolute md:left-1/2 md:top-0 md:order-none md:mx-0 md:w-auto md:-translate-x-1/2 md:border-0 md:px-0 md:[&>a]:h-16">
                    @foreach ($site['categories'] as $category)
                        @php $isActive = $activeCategory === $category->slug; @endphp
                        <a href="{{ $isActive ? route('home') : route('home', ['category' => $category->slug]) }}"
                           data-category="{{ $category->slug }}" class="label"
                           @if ($isActive) aria-current="true" @endif>{{ $category->name }}</a>
                    @endforeach
                </nav>
            @endif

            <div class="-mr-2 flex items-center" style="height: var(--header-height)">
                {{-- Tanpa JavaScript tautan ini membuka halaman hasil; dengan JavaScript membuka overlay (js/search.js). --}}
                <a href="{{ route('search') }}" id="search-toggle" aria-expanded="false" aria-controls="search-overlay"
                   class="flex h-11 w-11 items-center justify-center" aria-label="Search">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-4-4"></path></svg>
                </a>
                <button type="button" id="menu-toggle" aria-expanded="false" aria-controls="site-menu"
                        class="flex h-11 w-11 items-center justify-center" aria-label="Open menu">
                    <span class="block w-6 space-y-1.5" aria-hidden="true">
                        <span class="block h-0.5 bg-ink"></span>
                        <span class="block h-0.5 bg-ink"></span>
                        <span class="block h-0.5 bg-ink"></span>
                    </span>
                </button>
            </div>
        </div>
    </header>

    {{-- Menu kecil di bawah tombol menu (bukan layar penuh): panel gelap di kanan atas; klik di luar panel menutupnya. --}}
    <div id="site-menu" hidden class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Main menu">
        <div class="container-page relative h-full">
            <div class="absolute right-6 w-[min(20rem,calc(100vw-3rem))] bg-overlay p-6 text-white shadow-2xl md:right-6" style="top: calc(var(--header-height) - 0.5rem)">
                <div class="flex items-center justify-between">
                    <span class="label text-[#b5b5b5]">Menu</span>
                    <button type="button" id="menu-close" class="-mr-3 flex h-11 w-11 items-center justify-center text-2xl" aria-label="Close menu">&times;</button>
                </div>
                <nav aria-label="Main" class="mt-2 flex flex-col gap-1">
                    @foreach ($menu as [$label, $url, $current])
                        <a href="{{ $url }}" @if ($current) aria-current="page" @endif style="--i: {{ $loop->index }}"
                           class="w-fit text-[1.75rem] font-medium uppercase leading-[1.2] tracking-[-0.01em] {{ $current ? 'underline decoration-2 underline-offset-8' : '' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </nav>
                <div class="mt-6 space-y-1 border-t border-[#2a2a2a] pt-4 text-small text-[#b5b5b5]">
                    @foreach ([$site['contact_email'], $site['contact_phone'], $site['tagline']] as $line)
                        @if ($line)
                            <p>{{ $line }}</p>
                        @endif
                    @endforeach
                </div>

                {{-- Media sosial: hanya ikon (nama platform ada di label untuk pembaca layar). --}}
                @if (count($site['social']))
                    <ul class="-ml-3 mt-3 flex flex-wrap gap-1" aria-label="Social media">
                        @foreach ($site['social'] as $name => $url)
                            <li><x-social-link :name="$name" :url="$url" class="text-white hover:bg-white/10" /></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- Pencarian kecil seperti menu (bukan layar penuh): panel di kanan atas berisi kolom cari dan daftar hasil live
         (SRS FR-FE-050..052). Klik di luar panel atau Esc menutupnya. --}}
    <div id="search-overlay" hidden class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Search">
        <div class="container-page relative h-full">
            <div class="absolute right-6 w-[min(28rem,calc(100vw-3rem))] border border-line bg-bg shadow-2xl" style="top: calc(var(--header-height) - 0.5rem)">
                <form method="GET" action="{{ route('search') }}" role="search" class="flex items-center gap-2 px-4">
                    <label for="search-input" class="sr-only">Cari di website</label>
                    <input id="search-input" name="q" type="search" autocomplete="off" maxlength="100"
                           data-live="{{ route('search.live') }}" placeholder="Cari proyek, artikel, atau lowongan"
                           class="h-14 min-w-0 flex-1 border-0 bg-transparent p-0 text-body placeholder:text-ink-muted/60 focus:outline-none focus:ring-0">
                    <button type="button" id="search-close" class="-mr-3 flex h-11 w-11 shrink-0 items-center justify-center text-2xl" aria-label="Close search">&times;</button>
                </form>

                <div id="search-results" class="max-h-[min(60vh,30rem)] overflow-y-auto border-t border-line px-4 py-4 empty:hidden" role="status" aria-live="polite"></div>

                <a id="search-see-all" hidden href="{{ route('search') }}" data-base="{{ route('search') }}"
                   class="label block border-t border-line px-4 py-3 hover:bg-bg-soft">See all results &rarr;</a>
            </div>
        </div>
    </div>

    <main id="main" tabindex="-1" class="flex-1 focus:outline-none">
        {{ $slot }}
    </main>

    {{-- Footer minimalis (SRS FR-FE-003), mengikuti company profile: Location, Email, General information, Back to top. --}}
    <footer class="mt-24 border-t border-line bg-bg-soft">
        <div class="container-page grid gap-10 py-14 md:grid-cols-12 md:gap-12">
            <div class="md:col-span-4">
                <p class="text-[0.75rem] font-medium uppercase leading-[1.3] tracking-[0.16em]">
                    {{ $brandLines[0] }}@if (isset($brandLines[1]))<br>{{ $brandLines[1] }}@endif
                </p>
                @if ($site['tagline'])
                    <p class="mt-4 max-w-xs text-small text-ink-muted">{{ $site['tagline'] }}</p>
                @endif
            </div>

            <div class="md:col-span-3">
                @if ($site['company_address'])
                    <p class="label text-ink-muted">Location</p>
                    <p class="mt-3 whitespace-pre-line text-small">{{ $site['company_address'] }}</p>
                @endif
            </div>

            <div class="md:col-span-3">
                @if ($site['contact_email'])
                    <p class="label text-ink-muted">Email</p>
                    <p class="mt-3 text-small"><a href="mailto:{{ $site['contact_email'] }}" class="break-all hover:underline">{{ $site['contact_email'] }}</a></p>
                @endif
                @if ($site['contact_phone'])
                    <p class="label mt-6 text-ink-muted">General information</p>
                    <p class="mt-3 text-small"><a href="tel:{{ preg_replace('/[^\d+]/', '', $site['contact_phone']) }}" class="hover:underline">{{ $site['contact_phone'] }}</a></p>
                @endif
            </div>

            <div class="md:col-span-2 md:text-right">
                @if (count($site['social']))
                    <ul class="-mr-3 mb-4 flex flex-wrap gap-1 md:justify-end">
                        @foreach ($site['social'] as $name => $url)
                            <li><x-social-link :name="$name" :url="$url" class="hover:text-ink-muted" /></li>
                        @endforeach
                    </ul>
                @endif
                <a href="#" class="label border-b border-ink pb-0.5">Back to top</a>
            </div>
        </div>
        <div class="border-t border-line">
            <div class="container-page flex flex-wrap items-center justify-between gap-x-8 gap-y-2 py-5 text-small text-ink-muted">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <p>&copy; {{ date('Y') }} {{ $site['company_name'] ?: $siteName }}. All rights reserved.</p>
                    {{-- Pintu masuk dashboard untuk tim: kecil dan tidak mencolok. nofollow agar mesin pencari tidak menelusurinya
                         (robots.txt juga melarang /admin). Perlindungan sebenarnya ada di kata sandi dan pembatasan percobaan login. --}}
                    <a href="{{ route('filament.admin.auth.login') }}" rel="nofollow" class="hover:text-ink hover:underline">Staff login</a>
                </div>
                {{-- Kredit pembuat website: wajib ada di footer dan tidak dapat diubah dari dashboard. --}}
                <p>Design by Webyouneed</p>
            </div>
        </div>
    </footer>

    {{-- Tombol melayang "Back to top" di semua halaman; muncul setelah scroll (js/back-to-top.js). --}}
    <button type="button" id="back-to-top" data-visible="false" tabindex="-1" aria-label="Back to top"
            class="fixed bottom-6 right-6 z-30 flex h-12 w-12 items-center justify-center border border-ink bg-bg text-ink shadow-lg hover:bg-ink hover:text-white md:bottom-8 md:right-8">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"></path></svg>
    </button>
</body>
</html>
