{{--
    Info proyek di panel: nama, lokasi, tahun, data teknis, dan tautan ke halaman penuh. Di desktop blok ini adalah
    slide pertama deretan geser (ikut bergeser ke samping bersama foto, seperti halaman proyek BIG); di layar kecil
    ia tampil sebagai blok biasa di atas deretan foto, lengkap dengan narasi.
    Variabel: $project, $facts, $mobile.
--}}
<h2 class="text-h2 font-medium uppercase leading-[1.15] tracking-[-0.01em]">{{ $project->name }}</h2>

<p class="mt-2 text-[0.75rem] uppercase tracking-[0.08em] text-ink-muted">{{ $project->location }}</p>

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

@if ($mobile && $project->description)
    <x-prose :text="$project->description" class="mt-8 text-small leading-[1.7]" />
@endif

<a href="{{ route('projects.show', $project->slug) }}" class="label mt-8 inline-block border-b border-ink pb-0.5">Open project page &rarr;</a>
