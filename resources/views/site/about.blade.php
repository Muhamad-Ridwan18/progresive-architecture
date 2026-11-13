@use('Illuminate\Support\Str')

@php
    $overview = $content['overview'];
    $history = $content['history'];
    $vision = $content['vision_mission']['vision'];
    $mission = $content['vision_mission']['mission'];
    $description = $overview['body'] ? Str::limit(trim(preg_replace('/\s+/', ' ', $overview['body'])), 160) : null;

    // Bagian kosong tidak ditampilkan (SRS 3.5). Tim ada di bagian "Team" halaman ini (kartu yang membuka profil dengan halus).
    $hasHistory = $history['body'] || $history['milestones'];
    $hasVisionMission = $vision || $mission;
    $gap = 'mt-20 md:mt-28';
    $statColumns = [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3', 4 => 'md:grid-cols-4'][min(max(count($content['statistics']), 1), 4)];

    // Pernyataan visi: kalimat pertama besar, sisanya paragraf biasa (mockup).
    $visionParts = $vision ? preg_split('/(?<=[.!?])\s+/', trim($vision), 2) : [];
@endphp

<x-layouts.app title="About" :description="$description" :noindex="$preview">
    @if ($preview)
        <x-preview-banner :edit-url="url('/admin/about-us')" />
    @endif

    {{-- Judul besar berhuruf kapital (mockup) --}}
    <section class="container-page pt-14 md:pt-16">
        <p class="label text-ink-muted">About</p>
        <h1 class="mt-6 max-w-[20ch] text-display font-medium uppercase leading-[1.05] tracking-[-0.02em]">{{ $overview['heading'] ?: 'About' }}</h1>
    </section>

    {{-- Studio (Company Overview) --}}
    @if ($overview['body'])
        <section class="container-page {{ $gap }} grid gap-6 border-t border-line pt-6 md:grid-cols-12 md:gap-x-6">
            <h2 class="label text-ink-muted md:col-span-3">Studio</h2>
            <x-prose :text="$overview['body']" class="max-w-[68ch] text-[1.1875rem] leading-[1.65] md:col-span-8" />
        </section>
    @endif

    {{-- Company History --}}
    @if ($hasHistory)
        <section class="container-page {{ $gap }} grid gap-6 border-t border-line pt-6 md:grid-cols-12 md:gap-x-6">
            <h2 class="label text-ink-muted md:col-span-3">History</h2>
            <div class="md:col-span-8">
                <x-prose :text="$history['body']" class="max-w-[68ch] text-[1.1875rem] leading-[1.65]" />

                @if ($history['milestones'])
                    <ol class="{{ $history['body'] ? 'mt-12' : '' }} divide-y divide-line border-y border-line">
                        @foreach ($history['milestones'] as $milestone)
                            <li class="grid gap-2 py-5 md:grid-cols-[7rem_1fr] md:gap-6">
                                <span class="label text-ink-muted">{{ $milestone['year'] }}</span>
                                <div>
                                    <p class="text-h3 font-medium">{{ $milestone['title'] }}</p>
                                    @if ($milestone['description'])
                                        <p class="mt-1 text-ink-muted">{{ $milestone['description'] }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </section>
    @endif

    {{-- Vision & Mission --}}
    @if ($hasVisionMission)
        <section class="container-page {{ $gap }} grid gap-6 border-t border-line pt-6 md:grid-cols-12 md:gap-x-6">
            <h2 class="label text-ink-muted md:col-span-3">{{ $vision && $mission ? 'Vision & Mission' : ($vision ? 'Vision' : 'Mission') }}</h2>
            <div class="md:col-span-8">
                @if ($vision)
                    <p class="max-w-[40ch] text-[1.5rem] font-medium leading-[1.3] tracking-[-0.01em] md:text-[2rem]">{{ $visionParts[0] }}</p>
                    @if (isset($visionParts[1]))
                        <p class="mt-6 max-w-[68ch] text-[1.0625rem] leading-[1.7] text-ink-muted">{{ $visionParts[1] }}</p>
                    @endif
                @endif
                @if ($mission)
                    <x-prose :text="$mission" class="{{ $vision ? 'mt-10' : '' }} max-w-[68ch] text-[1.0625rem] leading-[1.7]" />
                @endif
            </div>
        </section>
    @endif

    {{-- Company Values: baris besar berhuruf kapital (mockup memakai tiga semboyan) --}}
    @if ($content['values'])
        <section class="container-page {{ $gap }} border-t border-line" aria-label="Company values">
            @foreach ($content['values'] as $value)
                <div class="border-b border-line py-7">
                    <h2 class="text-[2rem] font-medium uppercase leading-[1.1] tracking-[-0.02em] md:text-[3.5rem]">{{ $value['title'] }}</h2>
                    @if ($value['description'])
                        <p class="mt-3 max-w-[60ch] text-ink-muted">{{ $value['description'] }}</p>
                    @endif
                </div>
            @endforeach
        </section>
    @endif

    {{-- Company Statistics --}}
    @if ($content['statistics'])
        <section class="container-page {{ $gap }}" aria-label="Company statistics">
            <dl class="grid gap-x-12 gap-y-12 {{ $statColumns }}">
                @foreach ($content['statistics'] as $stat)
                    <div class="flex flex-col-reverse border-t-2 border-ink pt-4">
                        <dt class="mt-3 text-small leading-[1.5] text-ink-muted">{{ $stat['label'] }}</dt>
                        <dd class="text-[3rem] font-medium leading-none tracking-[-0.02em] md:text-[4rem]">{{ $stat['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    @if ($members->isNotEmpty())
        @include('site._team', ['members' => $members, 'selectedId' => $selected['id'] ?? null, 'photos' => $photos, 'teamUrl' => $preview ? route('about.preview') : route('about'), 'gap' => $gap])
    @endif

    <div class="pb-8"></div>
</x-layouts.app>
