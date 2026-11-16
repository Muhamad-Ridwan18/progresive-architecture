{{--
    Info proyek di panel: nama, lokasi, tahun, data teknis, dan tautan ke halaman penuh. Blok ini adalah slide pertama
    deretan geser (di desktop dan ponsel), jadi ikut bergeser ke samping bersama foto, seperti halaman proyek BIG.
    Variabel: $project, $facts.
--}}
<h2 class="text-h2 font-medium uppercase leading-[1.15] tracking-[-0.02em]">{{ $project->name }}</h2>

<p class="mt-2 text-label uppercase tracking-[0.08em] text-ink-muted">{{ $project->location }}</p>

@if ($project->year)
    <p class="mt-8 text-small text-ink-muted">{{ $project->year }}</p>
@endif

@if ($facts)
    <dl class="mt-6 space-y-4">
        @foreach ($facts as $label => $value)
            <div>
                <dt class="text-label uppercase tracking-[0.08em] text-ink-muted">{{ $label }}</dt>
                <dd class="mt-0.5 text-label font-medium uppercase leading-snug tracking-[0.08em]">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
@endif

<a href="{{ route('projects.show', $project->slug) }}" class="label mt-8 inline-block border-b border-ink pb-0.5">Open project page &rarr;</a>
