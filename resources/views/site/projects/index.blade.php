<x-layouts.app :title="$active?->name">

    <div class="container-page">
        <h1 class="sr-only">Projects{{ $active ? ' — '.$active->name : '' }}</h1>

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
