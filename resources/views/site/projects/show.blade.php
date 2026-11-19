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

        {{-- Galeri: foto dikelompokkan; tiap grup (1 sampai beberapa foto) berbagi satu deskripsi di sampingnya. Foto dalam
             satu baris dibuat sama tinggi (lebarnya mengikuti rasio asli) agar rapi. Foto membuka lightbox. --}}
        @if ($project->gallery->isNotEmpty())
            @php $photoNumber = 0; @endphp

            <section aria-label="Gallery" class="mt-16 md:mt-24">
                <div id="gallery" data-gallery class="space-y-14 md:space-y-24">
                    @foreach ($project->galleryGroups() as $group)
                        @php $hasText = filled($group['note']); @endphp

                        <div data-photo-group class="grid items-start gap-6 md:grid-cols-12 md:gap-x-8">
                            <div class="space-y-4 md:space-y-6 {{ $hasText ? 'md:col-span-8' : 'md:col-span-12' }}">
                                {{-- Baris disusun rapi (1 sampai 3 foto sama tinggi yang memenuhi lebar); lihat PublicProject::photoRows(). --}}
                                @foreach (\App\Support\PublicProject::photoRows($group['photos']) as $row)
                                    @php
                                        $sum = $row->sum(fn ($u) => $u->file->width / max(1, $u->file->height));
                                    @endphp

                                    <div class="photo-row" style="max-width: min(100%, calc(80vh * {{ round($sum, 4) }}))">
                                        @foreach ($row as $photo)
                                            @php
                                                $file = $photo->file;
                                                $ratio = $file->width / max(1, $file->height);
                                                $photoNumber++;
                                                // Resolusi tinggi dimuat saat foto dibuka (SRS FR-FE-044).
                                                $largeWidth = $file->bestVariant(2560) ?? $file->width;
                                                $largeHeight = (int) round($largeWidth * $file->height / max(1, $file->width));
                                                $share = max(20, (int) round(($hasText ? 62 : 92) * $ratio / max(0.01, $sum)));
                                            @endphp

                                            <figure class="photo-cell" style="--r: {{ round($ratio, 4) }}">
                                                <a href="{{ $file->url(2560) }}"
                                                   data-pswp-width="{{ $largeWidth }}"
                                                   data-pswp-height="{{ $largeHeight }}"
                                                   class="block overflow-hidden bg-bg-soft"
                                                   aria-label="Open photo {{ $photoNumber }} of {{ $project->gallery->count() }}">
                                                    <x-responsive-image :usage="$photo" :alt="$project->name" :sizes="'(min-width: 768px) '.$share.'vw, 100vw'" />
                                                </a>
                                                @if ($photo->caption)
                                                    <figcaption class="mt-2 text-small text-ink-muted">{{ $photo->caption }}</figcaption>
                                                @endif
                                            </figure>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>

                            {{-- Deskripsi grup: narasi, atau kutipan bila pengucapnya diisi. --}}
                            @if ($hasText)
                                <div class="md:sticky md:top-24 md:col-span-4">
                                    @if ($group['note_by'])
                                        <blockquote>
                                            <p class="whitespace-pre-line text-h2 tracking-[-0.02em]">&ldquo;{{ $group['note'] }}&rdquo;</p>
                                            <footer class="mt-4 text-label uppercase tracking-[0.08em] text-ink-muted">{{ $group['note_by'] }}</footer>
                                        </blockquote>
                                    @else
                                        <x-prose :text="$group['note']" class="text-body" />
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
