{{--
    Panel proyek yang membesar di tempat pada daftar (js/project-panel.js). Kiri: nama, lokasi, data teknis, dan narasi;
    kanan: foto cover dan galeri berjajar dan digulir ke samping. Fragmen tanpa layout; halaman detail penuh tetap ada.
--}}
@php
    $f = $project->facts;
    $facts = array_filter([
        'Category' => $project->category?->name,
        'Client' => $f['Client'] ?? null,
        'Typology' => $f['Typology'] ?? null,
        'Land area' => $f['Land area'] ?? null,
        'Building area' => $f['Building area'] ?? null,
        'Storey' => $f['Storey'] ?? null,
        'Status' => $f['Status'] ?? null,
    ]);

    $slides = collect([$project->cover])->filter()->concat($project->gallery)->values();
@endphp

<section data-project-panel data-slug="{{ $project->slug }}" aria-label="{{ $project->name }}" class="project-panel col-span-1 scroll-mt-20 md:col-span-12">
    <div>
        <div class="border-y border-line py-8 md:py-10">
            <div class="grid gap-8 md:grid-cols-12 md:gap-x-6">
                <div class="md:col-span-3">
                    <div class="flex items-start justify-between gap-4">
                        <h2 class="text-h2 font-medium uppercase leading-[1.15] tracking-[-0.01em]">{{ $project->name }}</h2>
                        <button type="button" data-panel-close class="-mr-3 -mt-2 flex h-11 w-11 shrink-0 items-center justify-center text-2xl" aria-label="Close project">&times;</button>
                    </div>

                    <p class="mt-2 text-[0.75rem] uppercase tracking-[0.08em] text-ink-muted">
                        @if ($project->year){{ $project->year }}@endif{{ $project->location ? ' – '.$project->location : '' }}
                    </p>

                    @if ($facts)
                        <dl class="mt-8 text-small">
                            @foreach ($facts as $label => $value)
                                <div class="flex justify-between gap-4 border-t border-line py-2.5 last:border-b">
                                    <dt class="text-ink-muted">{{ $label }}</dt>
                                    <dd class="text-right">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @if ($project->description)
                        <x-prose :text="$project->description" class="mt-8 text-small leading-[1.7]" />
                    @endif

                    <a href="{{ route('projects.show', $project->slug) }}" class="label mt-8 inline-block border-b border-ink pb-0.5">Open project page &rarr;</a>
                </div>

                <div class="min-w-0 md:col-span-9">
                    @if ($slides->isNotEmpty())
                        {{-- Foto berjajar ke samping: gulir, seret, panah, atau tombol. --}}
                        <div data-panel-scroller tabindex="0" role="group" aria-roledescription="carousel" aria-label="Foto {{ $project->name }}"
                             class="no-scrollbar flex h-[52vh] snap-x snap-mandatory gap-3 overflow-x-auto md:h-[64vh]">
                            @foreach ($slides as $i => $slide)
                                @php $file = $slide->file; @endphp
                                <figure class="h-full shrink-0 snap-start bg-bg-soft">
                                    <img src="{{ $file->url(1280) }}" srcset="{{ $file->srcset() }}" sizes="80vw"
                                         width="{{ $file->width }}" height="{{ $file->height }}"
                                         alt="{{ $slide->alt_text ?: $project->name }}"
                                         @if ($i > 1) loading="lazy" @endif decoding="async" draggable="false"
                                         class="h-full w-auto max-w-none select-none">
                                </figure>
                            @endforeach
                        </div>

                        <div class="mt-4 flex items-center justify-between" data-panel-controls>
                            <span class="label text-ink-muted"><span data-panel-count>1</span> / {{ $slides->count() }}</span>
                            <div class="flex gap-1">
                                <button type="button" data-panel-prev class="flex h-11 w-11 items-center justify-center border border-line hover:bg-bg-soft" aria-label="Foto sebelumnya">&larr;</button>
                                <button type="button" data-panel-next class="flex h-11 w-11 items-center justify-center border border-line hover:bg-bg-soft" aria-label="Foto berikutnya">&rarr;</button>
                            </div>
                        </div>
                    @else
                        <p class="text-ink-muted">Belum ada foto.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
