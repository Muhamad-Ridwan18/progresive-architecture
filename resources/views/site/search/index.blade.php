<x-layouts.app title="Search" :noindex="true">
    <div class="container-page py-14 md:py-20">
        <h1 class="text-display font-semibold tracking-tight">Search</h1>

        <form method="GET" action="{{ route('search') }}" role="search" class="mt-8 max-w-2xl">
            @if ($type) <input type="hidden" name="type" value="{{ $type }}"> @endif
            <label for="page-search" class="sr-only">Cari di website</label>
            <input id="page-search" name="q" type="search" value="{{ $q }}" autocomplete="off" maxlength="100"
                   placeholder="Cari proyek, artikel, atau lowongan"
                   class="block w-full border-0 border-b border-ink bg-transparent px-0 py-3 text-h2 placeholder:text-ink-muted/60">
        </form>

        {{-- Filter jenis konten (SRS FR-FE-052). Tautan biasa agar bekerja tanpa JavaScript. --}}
        <nav aria-label="Filter jenis konten" class="mt-8 flex flex-wrap gap-x-6 gap-y-2 border-b border-line pb-4">
            <a href="{{ route('search', array_filter(['q' => $q])) }}" class="label {{ $type ? 'text-ink-muted hover:text-ink' : 'text-ink' }}"
               @unless ($type) aria-current="true" @endunless>All</a>
            @foreach ($labels as $key => $label)
                <a href="{{ route('search', array_filter(['q' => $q, 'type' => $key])) }}"
                   class="label {{ $type === $key ? 'text-ink' : 'text-ink-muted hover:text-ink' }}"
                   @if ($type === $key) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="mt-10 max-w-3xl" role="status">
            @if ($q === '')
                <p class="text-ink-muted">Masukkan kata kunci untuk mulai mencari.</p>
            @else
                @include('site.search._results', ['tooShort' => $tooShort])
            @endif
        </div>
    </div>
</x-layouts.app>
