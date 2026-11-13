<x-layouts.app :title="$active ? $active->name.' — News' : 'News'" description="Insights, penghargaan, dan publikasi dari studio.">

    {{-- Tab News (mockup): kategori artikel dan Careers. Berupa tautan agar bekerja tanpa JavaScript. --}}
    <div class="border-b border-line">
        <nav aria-label="News" class="container-page">
            <div class="category-bar">
                @foreach ($categories as $category)
                    @php $isActive = $active?->is($category); @endphp
                    <a href="{{ route('news', ['category' => $category->slug]) }}" class="label"
                       @if ($isActive) aria-current="true" @endif>{{ $category->name }}</a>
                @endforeach
                {{-- Careers berada di bawah News pada company profile; lowongan dikelola di modul Careers. --}}
                <a href="{{ route('careers') }}" class="label">Careers</a>
            </div>
        </nav>
    </div>

    <div class="container-page">
        <h1 class="sr-only">News{{ $active ? ' — '.$active->name : '' }}</h1>

        <div id="news-grid"
             class="{{ $layout === 'cards' ? 'grid grid-cols-1 gap-x-6 gap-y-14 md:grid-cols-3' : 'grid grid-cols-1 gap-y-0' }} py-12 md:py-16">
            @if ($items->isNotEmpty())
                <p class="label col-span-1 pb-8 text-ink-muted md:col-span-3">News · {{ $active?->name }}</p>
            @endif
            @include('site.news._items')
        </div>

        {{-- Fallback tanpa JavaScript. Disembunyikan saat JavaScript aktif (infinite scroll, js/news.js). --}}
        @if ($prevUrl || $nextUrl)
            <nav class="pagination-fallback flex justify-between border-t border-line py-6" aria-label="Pagination">
                <span>@if ($prevUrl)<a href="{{ $prevUrl }}" class="label hover:underline">&larr; Newer</a>@endif</span>
                <span>@if ($nextUrl)<a href="{{ $nextUrl }}" class="label hover:underline">Older &rarr;</a>@endif</span>
            </nav>
        @endif
    </div>
</x-layouts.app>
