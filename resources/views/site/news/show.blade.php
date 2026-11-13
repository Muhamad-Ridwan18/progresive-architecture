<x-layouts.app :title="$article->title" :description="\Illuminate\Support\Str::limit((string) $article->excerpt, 160)"
    :image="$article->cover ? $article->cover->file->url(1280) : null" :noindex="$preview">

    @if ($preview)
        <x-preview-banner :edit-url="url('/admin/articles/'.$article->id.'/edit')" />
    @endif

    <article class="container-page py-14 md:py-20">
        <a href="{{ route('news') }}" class="label inline-flex min-h-11 items-center text-ink-muted hover:text-ink">&larr; News &amp; Articles</a>

        <header class="mt-6 max-w-3xl">
            <p class="label text-ink-muted">
                {{ $article->category?->name }}@if ($article->publishedAt) · {{ $article->publishedAt->translatedFormat('d M Y') }}@endif
            </p>
            <h1 class="mt-3 text-display font-semibold tracking-tight">{{ $article->title }}</h1>
            @if ($article->excerpt)
                <p class="mt-5 text-h3 text-ink-muted">{{ $article->excerpt }}</p>
            @endif
        </header>

        @if ($article->cover)
            <div class="mt-12 aspect-[4/5] overflow-hidden bg-bg-soft md:aspect-[16/9]">
                <x-responsive-image :usage="$article->cover" :alt="$article->title" sizes="100vw" cover eager />
            </div>
        @endif

        <div class="mt-12 grid gap-10 md:grid-cols-12">
            @if ($article->authorName)
                <div class="md:col-span-3">
                    <p class="label text-ink-muted">Penulis</p>
                    <p class="mt-1">{{ $article->authorName }}</p>
                </div>
            @endif

            {{-- Isi artikel: HTML dari editor, sudah disanitasi (PublicArticle::bodyHtml). --}}
            <div class="article-body md:col-span-7 {{ $article->authorName ? '' : 'md:col-start-4' }}">{!! $article->bodyHtml() !!}</div>
        </div>
    </article>

    @if ($related->isNotEmpty())
        <section class="container-page border-t border-line py-14" aria-labelledby="related-title">
            <h2 id="related-title" class="text-h2 font-semibold">Related Articles</h2>
            <div class="mt-8 grid gap-x-6 gap-y-10 md:grid-cols-3">
                @foreach ($related as $a)
                    <a href="{{ $a->url() }}" class="group block">
                        <div class="aspect-[4/5] overflow-hidden bg-bg-soft">
                            @if ($a->cover)
                                <x-responsive-image :usage="$a->cover" :alt="$a->title" sizes="(min-width: 768px) 33vw, 100vw" cover
                                    class="transition-transform duration-300 ease-out group-hover:scale-[1.02] motion-reduce:transition-none" />
                            @endif
                        </div>
                        <p class="mt-3 text-small text-ink-muted">
                            {{ $a->category?->name }}@if ($a->publishedAt) · {{ $a->publishedAt->translatedFormat('d M Y') }}@endif
                        </p>
                        <h3 class="mt-1 text-h3 font-semibold">{{ $a->title }}</h3>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
