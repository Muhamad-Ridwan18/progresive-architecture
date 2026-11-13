{{--
    Item daftar News. Dipakai halaman penuh dan sebagai fragmen untuk infinite scroll (permintaan AJAX).
    $layout: cards (kartu 3 kolom), rows (tahun, judul, penulis), blocks (sampul besar dan deskripsi).
--}}
@forelse ($items as $a)
    @if ($layout === 'rows')
        <a href="{{ $a->url() }}" class="group grid items-baseline gap-2 border-b border-line py-7 first:border-t md:grid-cols-12 md:gap-x-6">
            <span class="text-small text-ink-muted md:col-span-2">{{ $a->publishedAt?->format('Y') }}</span>
            <span class="text-[1.5rem] font-medium leading-[1.3] group-hover:underline md:col-span-7">{{ $a->title }}</span>
            <span class="text-small text-ink-muted md:col-span-3">{{ $a->authorName ?? $a->category?->name }}</span>
        </a>
    @elseif ($layout === 'blocks')
        <a href="{{ $a->url() }}" class="group grid items-center gap-8 py-10 md:grid-cols-12 md:gap-x-6">
            <div class="aspect-[4/5] overflow-hidden bg-bg-soft md:col-span-5">
                @if ($a->cover)
                    <x-responsive-image :usage="$a->cover" :alt="$a->title" sizes="(min-width: 768px) 42vw, 100vw" cover
                        class="transition-transform duration-300 ease-out group-hover:scale-[1.02] motion-reduce:transition-none" />
                @endif
            </div>
            <div class="md:col-span-6 md:col-start-7">
                <p class="text-[1.5rem] font-medium leading-[1.2] tracking-[-0.01em] md:text-[2rem]">{{ $a->title }}</p>
                @if ($a->excerpt)
                    <p class="mt-5 max-w-[48ch] text-[1.0625rem] leading-[1.7] text-ink-muted">{{ $a->excerpt }}</p>
                @endif
                <span class="label mt-8 inline-block border-b border-ink pb-0.5">Read</span>
            </div>
        </a>
    @else
        <article class="col-span-1">
            <a href="{{ $a->url() }}" class="group block">
                <div class="aspect-[4/5] overflow-hidden bg-bg-soft">
                    @if ($a->cover)
                        <x-responsive-image :usage="$a->cover" :alt="$a->title" sizes="(min-width: 768px) 33vw, 100vw" cover
                            class="transition-transform duration-300 ease-out group-hover:scale-[1.02] motion-reduce:transition-none" />
                    @endif
                </div>
                <p class="mt-4 text-[0.75rem] uppercase tracking-[0.08em] text-ink-muted">
                    {{ $a->category?->name }}@if ($a->publishedAt) · {{ $a->publishedAt->translatedFormat('d M Y') }}@endif
                </p>
                <h2 class="mt-1.5 text-h3 font-medium leading-[1.3]">{{ $a->title }}</h2>
            </a>
        </article>
    @endif
@empty
    @if ($offset === 0)
        <p class="col-span-1 py-12 text-ink-muted md:col-span-3">Belum ada artikel di kategori ini.</p>
    @endif
@endforelse

@if ($nextUrl)
    <div data-news-sentinel data-next="{{ $nextUrl }}" class="col-span-1 h-px md:col-span-3" aria-hidden="true"></div>
@endif
