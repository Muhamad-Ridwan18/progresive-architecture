{{--
    Hasil pencarian terkelompok (Projects, News & Articles, Careers; SRS FR-FE-051). Dipakai overlay live
    (fragmen AJAX) dan halaman hasil lengkap. Semua teks di-escape.
--}}
@php $compact = $compact ?? false; @endphp

@if ($tooShort ?? false)
    <p class="text-ink-muted">Ketik minimal 2 huruf untuk mencari.</p>
@elseif ($total === 0)
    <p class="text-ink-muted">Tidak ada hasil untuk &ldquo;{{ $q }}&rdquo;.</p>
@else
    <div class="{{ $compact ? 'space-y-6' : 'space-y-10' }}">
        @foreach ($groups as $key => $items)
            @continue($items->isEmpty())

            <section aria-labelledby="group-{{ $key }}">
                <h2 id="group-{{ $key }}" class="label text-ink-muted">{{ $labels[$key] }}</h2>
                <ul class="{{ $compact ? 'mt-2' : 'mt-3' }} divide-y divide-line border-y border-line">
                    @foreach ($items as $item)
                        <li>
                            <a href="{{ $item['url'] }}" class="{{ $compact ? 'block py-3' : 'block py-4' }} transition-opacity duration-150 hover:opacity-60">
                                <span class="{{ $compact ? 'block text-body font-semibold' : 'block text-h3 font-semibold' }}">{{ $item['title'] }}</span>
                                @if ($item['meta'])
                                    <span class="mt-0.5 block text-small text-ink-muted">{{ $item['meta'] }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>
@endif
