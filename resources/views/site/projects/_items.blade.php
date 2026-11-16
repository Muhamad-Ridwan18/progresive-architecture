{{--
    Item daftar proyek. Dipakai halaman penuh dan sebagai fragmen untuk infinite scroll (permintaan AJAX).
    Pola tata letak ditentukan dari posisi item secara keseluruhan ($offset + $i), sehingga tetap
    berkesinambungan antar batch.
--}}
@use('App\Support\GridPattern')

@forelse ($items as $i => $p)
    @php $pattern = GridPattern::project($offset + $i); @endphp

    <article @if (($offset + $i) >= 2) data-reveal @endif style="--d: {{ ($offset + $i) % 2 }}" class="col-span-1 {{ $pattern['col'] }} {{ $pattern['offset'] }}">
        <a href="{{ $p->url() }}" data-project-link data-slug="{{ $p->slug }}" data-panel="{{ route('projects.panel', $p->slug) }}" aria-expanded="false" class="group block">
            <div data-cover class="{{ $pattern['ratio'] }} overflow-hidden bg-bg-soft">
                @if ($p->cover)
                    <x-responsive-image
                        :usage="$p->cover"
                        :alt="$p->name"
                        :sizes="$pattern['sizes']"
                        cover
                        :eager="($offset + $i) < 2"
                        class="transition-transform duration-300 ease-out group-hover:scale-[1.02] motion-reduce:transition-none"
                    />
                @endif
            </div>
            <h2 class="mt-3 text-small font-medium uppercase tracking-[0.08em]">{{ $p->name }}</h2>
            <p class="mt-1 text-[0.75rem] uppercase tracking-[0.08em] text-ink-muted">
                @if ($p->year){{ $p->year }}@endif{{ $p->location ? ' – '.$p->location : '' }}
            </p>
        </a>
    </article>
@empty
    @if ($offset === 0)
        <p class="col-span-1 py-24 text-ink-muted md:col-span-12">Belum ada proyek yang dipublikasikan.</p>
    @endif
@endforelse

@if ($nextUrl)
    <div data-sentinel data-next="{{ $nextUrl }}" data-next-count="{{ $nextCount }}" class="col-span-1 h-px md:col-span-12" aria-hidden="true"></div>
@endif
