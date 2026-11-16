<x-layouts.app title="Careers" description="Come and join us. Lowongan terbuka dan lamar langsung dari halaman lowongan, tanpa membuat akun.">

    {{-- Tab News (mockup): Careers berada di bawah News. --}}
    <div class="border-b border-line">
        <nav aria-label="News" class="container-page">
            <div class="category-bar">
                @foreach ($newsCategories as $category)
                    <a href="{{ route('news', ['category' => $category->slug]) }}" class="label">{{ $category->name }}</a>
                @endforeach
                <a href="{{ route('careers') }}" class="label" aria-current="true">Careers</a>
            </div>
        </nav>
    </div>

    <div class="container-page py-14 md:py-16">
        <h1 class="max-w-[14ch] text-display font-medium uppercase leading-[1.05] tracking-[-0.02em]">Come and join us</h1>

        @if ($intro)
            <x-prose :text="$intro" class="mt-10 max-w-[58ch] text-lead" />
        @endif

        {{-- Recruitments (mockup): lokasi di kiri, daftar posisi dengan tautan Apply di kanan. Formulir lamaran ada
             di halaman tiap posisi karena isiannya berbeda per posisi (Form Builder). --}}
        <section class="mt-24 grid gap-8 border-t border-line pt-6 md:mt-32 md:grid-cols-12 md:gap-x-6" aria-labelledby="recruitments">
            <div class="md:col-span-3">
                <h2 id="recruitments" class="label text-ink-muted">Recruitments</h2>
                @if ($site['company_address'])
                    <p class="mt-4 whitespace-pre-line text-small text-ink-muted">Base on {{ $site['company_address'] }}</p>
                @endif
            </div>

            <div class="md:col-span-9">
                <p class="label border-b border-line pb-3 text-ink-muted">Position</p>

                @if ($vacancies->isEmpty())
                    <p class="py-12 text-ink-muted">
                        {{ $activeCategory || $activeStatus ? 'Tidak ada lowongan yang cocok dengan filter ini.' : 'Belum ada lowongan terbuka saat ini.' }}
                    </p>
                @else
                    <ul>
                        @foreach ($vacancies as $vacancy)
                            @php $open = $vacancy->isOpen(); @endphp
                            <li class="border-b border-line">
                                <a href="{{ route('careers.show', $vacancy) }}"
                                   class="grid min-h-11 grid-cols-[1fr_auto] items-center gap-4 py-6 transition-colors duration-150 hover:bg-bg-soft md:px-2">
                                    <span>
                                        <span class="block text-h2 font-medium uppercase tracking-[0.08em]">{{ $vacancy->title }}</span>
                                        <span class="mt-1 block text-small text-ink-muted">
                                            {{ collect([$vacancy->location, $vacancy->employment_type, $vacancy->deadline ? 'Deadline '.$vacancy->deadline->translatedFormat('d M Y') : null])->filter()->implode(' · ') }}
                                        </span>
                                    </span>
                                    <span class="label {{ $open ? '' : 'text-ink-muted' }}">{{ $open ? 'Apply →' : 'Closed' }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </div>
</x-layouts.app>
