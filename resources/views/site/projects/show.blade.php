@use('App\Support\GridPattern')
@use('Illuminate\Support\Str')

@php
    $description = $project->description ? Str::limit(trim(preg_replace('/\s+/', ' ', $project->description)), 160) : null;
    // Urutan seperti lembar data di company profile: klien, tipologi, luas, lantai, lokasi, tahun, status.
    $f = $project->facts;
    $facts = array_filter([
        'Category' => $project->category?->name,
        'Client' => $f['Client'] ?? null,
        'Typology' => $f['Typology'] ?? null,
        'Land area' => $f['Land area'] ?? null,
        'Building area' => $f['Building area'] ?? null,
        'Storey' => $f['Storey'] ?? null,
        'Location' => $project->location,
        'Year' => $project->year,
        'Status' => $f['Status'] ?? null,
    ]);
@endphp

<x-layouts.app
    :title="$project->name"
    :description="$description"
    :image="$project->cover?->file->url(1280)"
    :noindex="$preview"
>
    @if ($preview)
        <x-preview-banner :edit-url="url('/admin/projects/'.$project->id.'/edit')" />
    @endif

    <article class="container-page pt-8 md:pt-8">
        <a href="{{ route('home') }}" data-enter style="--i: 0" class="label inline-flex min-h-11 items-center text-ink-muted hover:text-ink">&larr; Project / {{ $project->name }}</a>

        {{-- Judul besar berhuruf kapital dan narasi di kiri, lembar data di kanan (mockup). --}}
        <header class="mt-8 grid gap-10 md:grid-cols-12 md:items-start md:gap-x-6">
            <div class="md:col-span-7">
                <h1 data-enter style="--i: 1" class="text-display font-medium uppercase leading-[1.05] tracking-[-0.02em]">{{ $project->name }}</h1>
                @if ($project->description)
                    <div data-enter style="--i: 2"><x-prose :text="$project->description" class="mt-10 max-w-[62ch] text-[1.0625rem] leading-[1.7]" /></div>
                @endif
            </div>

            @if ($facts)
                <dl class="text-small md:col-span-4 md:col-start-9">
                    @foreach ($facts as $label => $value)
                        <div data-enter style="--i: {{ min(2 + $loop->iteration, 9) }}" class="flex justify-between gap-4 border-t border-line py-3 last:border-b">
                            <dt class="text-ink-muted">{{ $label }}</dt>
                            <dd class="text-right">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </header>

        {{-- Cover: 16:9 di desktop, 4:5 di mobile, dipotong mengikuti titik fokus (SRS 12.5). --}}
        @if ($project->cover)
            <div class="mt-16 aspect-[4/5] overflow-hidden bg-bg-soft md:aspect-[16/9]" style="view-transition-name: project-cover">
                <x-responsive-image :usage="$project->cover" :alt="$project->name" sizes="100vw" cover eager />
            </div>
        @endif

        {{-- Galeri: pola ditentukan sistem dari urutan foto (SRS FR-FE-031). Foto membuka lightbox. --}}
        @if ($project->gallery->isNotEmpty())
            <section aria-label="Gallery" class="mt-16 md:mt-24">
                <div id="gallery" data-gallery class="grid grid-cols-1 items-start gap-6 md:grid-cols-12 md:gap-8">
                    @foreach ($project->gallery as $i => $photo)
                        @php
                            $file = $photo->file;
                            $pattern = GridPattern::gallery($i);
                            // Resolusi tinggi dimuat saat foto dibuka (SRS FR-FE-044).
                            $largeWidth = $file->bestVariant(2560) ?? $file->width;
                            $largeHeight = (int) round($largeWidth * $file->height / max(1, $file->width));
                        @endphp

                        <figure class="col-span-1 {{ $pattern['col'] }} {{ $pattern['offset'] }}">
                            <a href="{{ $file->url(2560) }}"
                               data-pswp-width="{{ $largeWidth }}"
                               data-pswp-height="{{ $largeHeight }}"
                               class="block overflow-hidden bg-bg-soft"
                               aria-label="Open photo {{ $i + 1 }} of {{ $project->gallery->count() }}">
                                <x-responsive-image :usage="$photo" :alt="$project->name" :sizes="$pattern['sizes']" />
                            </a>
                            @if ($photo->caption)
                                <figcaption class="mt-2 text-small text-ink-muted">{{ $photo->caption }}</figcaption>
                            @endif
                        </figure>

                        {{-- Teks tambahan setelah foto (narasi atau kutipan), selebar baris. --}}
                        @if ($photo->note)
                            <div class="col-span-1 md:col-span-12">
                                @if ($photo->note_by)
                                    <blockquote class="max-w-[48ch] md:ml-[25%]">
                                        <p class="whitespace-pre-line text-[1.5rem] leading-[1.35] tracking-[-0.01em]">&ldquo;{{ $photo->note }}&rdquo;</p>
                                        <footer class="mt-4 text-[0.75rem] uppercase tracking-[0.08em] text-ink-muted">{{ $photo->note_by }}</footer>
                                    </blockquote>
                                @else
                                    <x-prose :text="$photo->note" class="max-w-[62ch] text-[1.0625rem] leading-[1.7] md:ml-[25%]" />
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif
    </article>

    {{-- Next / Previous Project (SRS FR-FE-033). --}}
    @if (! $preview && ($next || $previous))
        <nav class="container-page mt-24 grid gap-px border-y border-line md:grid-cols-2" aria-label="More projects">
            <div class="py-8 md:pr-8">
                @if ($previous)
                    <a href="{{ $previous->url() }}" data-project-nav class="group block">
                        <p class="label text-ink-muted">&larr; Previous project</p>
                        <p class="mt-2 text-h2 font-semibold group-hover:underline">{{ $previous->name }}</p>
                    </a>
                @endif
            </div>
            <div class="py-8 md:border-l md:border-line md:pl-8 md:text-right">
                @if ($next)
                    <a href="{{ $next->url() }}" data-project-nav class="group block">
                        <p class="label text-ink-muted">Next project &rarr;</p>
                        <p class="mt-2 text-h2 font-semibold group-hover:underline">{{ $next->name }}</p>
                    </a>
                @endif
            </div>
        </nav>
    @endif
</x-layouts.app>
