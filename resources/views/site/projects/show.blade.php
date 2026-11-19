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
                    <div data-enter style="--i: 2"><x-prose :text="$project->description" class="mt-10 max-w-[62ch] text-lead" /></div>
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

        {{-- Galeri: foto dikelompokkan; tiap grup (1 sampai beberapa foto) berbagi satu caption di tengah, di bawah foto-fotonya.
             Tiap baris simetris: 1 sampai 3 kolom sama lebar dengan rasio seragam (lihat PublicProject::photoRows/rowAspect).
             Foto membuka lightbox. --}}
        @if ($project->gallery->isNotEmpty())
            @php
                $photoNumber = 0;
                $columns = [1 => '', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3'];
                $sizesByCount = [1 => '(min-width: 768px) 92vw, 100vw', 2 => '(min-width: 768px) 46vw, 100vw', 3 => '(min-width: 768px) 31vw, 100vw'];
            @endphp

            <section aria-label="Gallery" class="mt-16 md:mt-24">
                <div id="gallery" data-gallery class="space-y-16 md:space-y-24">
                    @foreach ($project->galleryGroups() as $group)
                        <div data-photo-group>
                            <div class="space-y-4">
                                @foreach (\App\Support\PublicProject::photoRows($group['photos']) as $row)
                                    @php $aspect = \App\Support\PublicProject::rowAspect($row); @endphp

                                    <div data-photo-row class="grid gap-4 {{ $columns[$row->count()] }}">
                                        @foreach ($row as $photo)
                                            @php
                                                $file = $photo->file;
                                                $photoNumber++;
                                                // Resolusi tinggi dimuat saat foto dibuka (SRS FR-FE-044).
                                                $largeWidth = $file->bestVariant(2560) ?? $file->width;
                                                $largeHeight = (int) round($largeWidth * $file->height / max(1, $file->width));
                                            @endphp

                                            <figure data-photo-cell class="min-w-0">
                                                <a href="{{ $file->url(2560) }}"
                                                   data-pswp-width="{{ $largeWidth }}"
                                                   data-pswp-height="{{ $largeHeight }}"
                                                   class="block overflow-hidden bg-bg-soft"
                                                   style="aspect-ratio: {{ $aspect }}"
                                                   aria-label="Open photo {{ $photoNumber }} of {{ $project->gallery->count() }}">
                                                    <x-responsive-image :usage="$photo" :alt="$project->name" :sizes="$sizesByCount[$row->count()]" cover />
                                                </a>
                                            </figure>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>

                            {{-- Caption grup: di tengah, di bawah foto-fotonya. Kutipan bila pengucapnya diisi. --}}
                            @if ($group['caption'])
                                <div class="mx-auto mt-6 text-center">
                                    @if ($group['note'] && $group['note_by'])
                                        <blockquote class="mx-auto max-w-[48ch]">
                                            <p class="whitespace-pre-line text-h2 tracking-[-0.02em]">&ldquo;{{ $group['note'] }}&rdquo;</p>
                                            <footer class="mt-4 text-label uppercase tracking-[0.08em] text-ink-muted">{{ $group['note_by'] }}</footer>
                                        </blockquote>
                                    @else
                                        <x-prose :text="$group['caption']" class="mx-auto max-w-[60ch] text-balance text-small text-ink/80" />
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Peta lokasi proyek (bila koordinat diisi). --}}
        @if ($project->hasMap())
            <section aria-label="Location" class="mt-16 grid gap-6 border-t border-line pt-6 md:mt-24 md:grid-cols-12 md:gap-x-6">
                <h2 class="label text-ink-muted md:col-span-3">Location</h2>
                <div class="md:col-span-9">
                    @include('site.projects._map', ['project' => $project, 'class' => 'aspect-[4/3] w-full md:aspect-[16/7]'])
                </div>
            </section>
        @endif

        {{-- Kredit proyek: tim dan kolaborator. --}}
        @if ($project->credits)
            <section aria-label="Credits" class="mt-16 grid gap-6 border-t border-line pt-6 md:mt-24 md:grid-cols-12 md:gap-x-6">
                <h2 class="label text-ink-muted md:col-span-3">Credits</h2>
                <div class="md:col-span-9">
                    @include('site.projects._credits', ['credits' => $project->credits, 'columns' => 'columns-2 md:columns-3'])
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
