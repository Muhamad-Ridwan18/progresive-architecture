{{--
    Panel proyek yang membesar di tempat pada daftar (js/project-panel.js), bergaya halaman proyek BIG: satu deretan
    yang digulir ke samping berisi info proyek (di desktop), foto cover dan galeri, narasi, dan teks tambahan dari admin.
    Seluruh deretan, termasuk info di kiri, ikut bergeser; foto berikutnya mengintip di tepi layar.
    Fragmen tanpa layout; halaman detail penuh tetap ada.
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
        <div class="border-y border-line py-6 md:py-8">
            <div class="flex justify-end">
                <button type="button" data-panel-close class="-mr-3 flex h-11 w-11 items-center justify-center text-2xl" aria-label="Close project">&times;</button>
            </div>

            @if ($slides->isNotEmpty())
                {{-- Seluruh deretan digulir ke samping (gulir, seret, panah, atau keyboard) dan meluber sampai tepi layar. --}}
                <div data-panel-scroller tabindex="0" role="group" aria-roledescription="carousel" aria-label="Foto {{ $project->name }}"
                     class="project-panel-bleed no-scrollbar flex snap-x snap-proximity gap-3 overflow-x-auto">

                    {{-- Info adalah slide pertama (di ponsel juga), jadi ikut bergeser keluar saat deretan digulir. --}}
                    <div class="w-[78vw] shrink-0 snap-start overflow-y-auto pr-6 text-left md:w-[17rem] md:text-right">
                        @include('site.projects._panel-info', ['project' => $project, 'facts' => $facts])
                    </div>

                    @foreach ($slides as $i => $slide)
                        @php
                            $file = $slide->file;
                            $ratio = round($file->width / max(1, $file->height), 5);
                        @endphp

                        {{-- Setiap foto punya keterangan sendiri (caption dari admin). Ruangnya selalu disediakan agar tinggi semua foto sama. --}}
                        <figure data-panel-slide class="panel-figure flex h-full shrink-0 snap-start flex-col" style="--r: {{ $ratio }}">
                            <div class="min-h-0 flex-1 bg-bg-soft">
                                <img src="{{ $file->url(1280) }}" srcset="{{ $file->srcset() }}" sizes="80vw"
                                     width="{{ $file->width }}" height="{{ $file->height }}"
                                     alt="{{ $slide->alt_text ?: $project->name }}"
                                     @if ($i > 1) loading="lazy" @endif decoding="async" draggable="false"
                                     class="h-full w-full select-none object-cover">
                            </div>
                            <figcaption class="h-12 overflow-hidden pt-2 text-label leading-snug text-ink-muted" style="max-width: 100%">{{ $slide->caption }}</figcaption>
                        </figure>

                        {{-- Teks yang ditambahkan admin setelah foto ini. Berisi pengucap = kutipan (huruf besar); selain itu narasi biasa. --}}
                        @if ($slide->note)
                            <div class="flex shrink-0 snap-start items-center overflow-y-auto pr-6 {{ $slide->note_by ? 'w-[85vw] md:w-[34rem]' : 'w-[78vw] md:w-[20rem]' }}">
                                <div>
                                    @if ($slide->note_by)
                                        <p class="whitespace-pre-line text-h2 leading-[1.35] tracking-[-0.02em]">&ldquo;{{ $slide->note }}&rdquo;</p>
                                        <p class="mt-6 text-label uppercase tracking-[0.08em] text-ink-muted">{{ $slide->note_by }}</p>
                                    @else
                                        <x-prose :text="$slide->note" class="text-caption" />
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Narasi proyek duduk tepat di kanan foto pertama; foto berikutnya mengintip di tepinya. --}}
                        @if ($i === 0 && $project->description)
                            <div class="w-[78vw] shrink-0 snap-start overflow-y-auto pl-2 pr-6 md:w-[20rem]">
                                <x-prose :text="$project->description" class="text-caption" />
                            </div>
                        @endif
                    @endforeach

                    {{-- Peta lokasi (bila koordinat diisi), lalu kredit. --}}
                    @if ($project->hasMap())
                        <div class="flex w-[88vw] shrink-0 snap-start flex-col pr-6 md:w-[40rem]">
                            @include('site.projects._map', ['project' => $project, 'class' => 'min-h-0 flex-1'])
                        </div>
                    @endif

                    {{-- Slide terakhir: kredit proyek (tim dan kolaborator), seperti halaman proyek BIG. --}}
                    @if ($project->credits)
                        <div class="w-[88vw] shrink-0 snap-start overflow-y-auto pr-6 md:w-[56rem]">
                            @include('site.projects._credits', ['credits' => $project->credits, 'columns' => 'columns-2 md:columns-3'])
                        </div>
                    @endif
                </div>

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
</section>
