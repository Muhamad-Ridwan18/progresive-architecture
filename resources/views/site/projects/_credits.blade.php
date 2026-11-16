{{--
    Kredit proyek (tim dan kolaborator) seperti slide terakhir di halaman proyek BIG: kelompok berjudul berisi nama,
    ditata dalam kolom. Variabel: $credits (daftar {title, names[]}), $columns (kelas kolom).
--}}
<div class="{{ $columns }} gap-x-10">
    @foreach ($credits as $group)
        <section class="mb-8 break-inside-avoid">
            @if ($group['title'] !== '')
                <h3 class="text-label uppercase tracking-[0.08em] text-ink-muted">{{ $group['title'] }}</h3>
            @endif
            <ul class="mt-3 space-y-1 text-body leading-snug">
                @foreach ($group['names'] as $name)
                    <li>{{ $name }}</li>
                @endforeach
            </ul>
        </section>
    @endforeach
</div>
