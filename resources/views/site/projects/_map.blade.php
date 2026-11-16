{{--
    Peta lokasi proyek (OpenStreetMap yang disematkan, dibuat hitam putih seperti di halaman proyek BIG). Dapat digeser
    dan diperbesar. Dimuat malas. Variabel: $project, $class (kelas ukuran).
--}}
<div class="{{ $class }}">
    <iframe src="{{ $project->mapEmbedUrl() }}" title="Peta lokasi {{ $project->name }}" loading="lazy" referrerpolicy="no-referrer"
            class="h-full w-full border-0 bg-bg-soft" style="filter: grayscale(1) contrast(1.05)"></iframe>
</div>
<a href="{{ $project->mapLinkUrl() }}" target="_blank" rel="noopener" class="label mt-3 inline-block border-b border-ink pb-0.5">Open map &rarr;</a>
