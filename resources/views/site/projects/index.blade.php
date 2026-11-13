<x-layouts.app :title="$active?->name">

    <div class="container-page">
        <h1 class="sr-only">Projects{{ $active ? ' — '.$active->name : '' }}</h1>

        {{-- Pencarian proyek (mockup): mengirim ke halaman hasil; tanpa JavaScript tetap bekerja. --}}
        <form method="GET" action="{{ route('search') }}" role="search" class="max-w-md pt-10">
            <label for="home-search" class="label text-ink-muted">Search</label>
            <input id="home-search" name="q" type="search" autocomplete="off" maxlength="100" placeholder="Project . . ."
                   class="mt-1 block w-full border-0 border-b border-ink bg-transparent px-0 py-2.5 text-body placeholder:text-ink-muted/60">
        </form>

        <div id="project-grid"
             class="grid grid-cols-1 gap-x-6 gap-y-12 py-10 md:grid-cols-12 md:gap-y-14 md:py-12"
             data-loaded-pages="{{ $loadedPages }}"
             data-category="{{ $active?->slug }}">
            @include('site.projects._items')
        </div>

        {{-- Kerangka pemuatan untuk infinite scroll dan pergantian kategori (dipakai js/projects.js). --}}
        <template id="project-skeleton">@include('site.projects._skeleton')</template>

        {{-- Fallback tanpa JavaScript (SRS FR-FE-020). Disembunyikan saat JavaScript aktif. --}}
        @if ($prevUrl || $nextUrl)
            <nav class="pagination-fallback flex justify-between border-t border-line py-6" aria-label="Pagination">
                <span>@if ($prevUrl)<a href="{{ $prevUrl }}" class="label hover:underline">&larr; Newer</a>@endif</span>
                <span>@if ($nextUrl)<a href="{{ $nextUrl }}" class="label hover:underline">Older &rarr;</a>@endif</span>
            </nav>
        @endif
    </div>
</x-layouts.app>
