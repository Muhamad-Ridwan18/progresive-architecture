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
                <div class="md:col-span-3 md:text-right">
                    <div class="flex items-start justify-between gap-4 md:flex-row-reverse">
                        <h2 class="text-h2 font-medium uppercase leading-[1.15] tracking-[-0.01em]">{{ $project->name }}</h2>
                        <button type="button" data-panel-close class="-mr-3 -mt-2 flex h-11 w-11 shrink-0 items-center justify-center text-2xl md:-ml-3 md:mr-0" aria-label="Close project">&times;</button>
                    </div>

                    <p class="mt-2 text-[0.75rem] uppercase tracking-[0.08em] text-ink-muted">
                        {{ $project->location }}
                    </p>

                    @if ($project->year)
                        <p class="mt-8 text-small text-ink-muted">{{ $project->year }}</p>
                    @endif

                    @if ($facts)
                        <dl class="mt-6 space-y-4">
                            @foreach ($facts as $label => $value)
                                <div>
                                    <dt class="text-[0.6875rem] uppercase tracking-[0.08em] text-ink-muted">{{ $label }}</dt>
                                    <dd class="mt-0.5 text-[0.75rem] font-medium uppercase leading-snug tracking-[0.04em]">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    {{-- Di layar kecil narasi tampil di sini; di desktop ia menjadi bagian dari deretan foto (kanan). --}}
                    @if ($project->description)
                        <x-prose :text="$project->description" class="mt-8 text-left text-small leading-[1.7] md:hidden" />
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
                                {{-- Setiap foto punya keterangan sendiri (caption yang diisi di admin). Ruangnya selalu disediakan agar tinggi semua foto sama. --}}
                                <figure data-panel-slide class="flex h-full shrink-0 snap-start flex-col">
                                    <div class="min-h-0 flex-1 bg-bg-soft">
                                        <img src="{{ $file->url(1280) }}" srcset="{{ $file->srcset() }}" sizes="80vw"
                                             width="{{ $file->width }}" height="{{ $file->height }}"
                                             alt="{{ $slide->alt_text ?: $project->name }}"
                                             @if ($i > 1) loading="lazy" @endif decoding="async" draggable="false"
                                             class="h-full w-auto max-w-none select-none">
                                    </div>
                                    <figcaption class="h-12 overflow-hidden pt-2 text-[0.75rem] leading-snug text-ink-muted" style="max-width: 100%">{{ $slide->caption }}</figcaption>
                                </figure>

                                {{-- Teks yang ditambahkan admin setelah foto ini. Berisi pengucap = kutipan (huruf besar); selain itu narasi biasa. --}}
                                @if ($slide->note)
                                    <div class="hidden shrink-0 snap-start items-center overflow-y-auto pr-6 md:flex {{ $slide->note_by ? 'w-[34rem]' : 'w-[20rem]' }}">
                                        <div>
                                            @if ($slide->note_by)
                                                <p class="whitespace-pre-line text-[1.375rem] leading-[1.35] tracking-[-0.01em]">&ldquo;{{ $slide->note }}&rdquo;</p>
                                                <p class="mt-6 text-[0.6875rem] uppercase tracking-[0.08em] text-ink-muted">{{ $slide->note_by }}</p>
                                            @else
                                                <x-prose :text="$slide->note" class="text-[0.8125rem] leading-[1.6]" />
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                {{-- Narasi duduk tepat di kanan foto pertama, seperti halaman proyek BIG; foto berikutnya mengintip di tepinya. --}}
                                @if ($i === 0 && $project->description)
                                    <div class="hidden w-[20rem] shrink-0 snap-start overflow-y-auto pl-2 pr-6 md:block">
                                        <x-prose :text="$project->description" class="text-[0.8125rem] leading-[1.6]" />
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        @if ($slides->contains(fn ($slide) => filled($slide->note)))
                            <div class="mt-6 space-y-6 md:hidden">
                                @foreach ($slides->filter(fn ($slide) => filled($slide->note)) as $slide)
                                    @if ($slide->note_by)
                                        <blockquote>
                                            <p class="whitespace-pre-line text-[1.125rem] leading-[1.4]">&ldquo;{{ $slide->note }}&rdquo;</p>
                                            <footer class="mt-3 text-[0.6875rem] uppercase tracking-[0.08em] text-ink-muted">{{ $slide->note_by }}</footer>
                                        </blockquote>
                                    @else
                                        <x-prose :text="$slide->note" class="text-small leading-[1.7]" />
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-4 flex items-center justify-between" data-panel-controls>
                            <span class="label text-ink-muted"><span data-panel-count>01</span> / {{ str_pad((string) $slides->count(), 2, '0', STR_PAD_LEFT) }}</span>
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
