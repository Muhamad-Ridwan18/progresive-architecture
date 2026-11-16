{{-- Bagian Team di halaman About. Awalnya semua anggota hanya kartu; profil dibuka dengan halus saat kartu diklik
     (js/team.js). Variabel: $members, $selectedId, $photos, $teamUrl. --}}
<section id="team" class="container-page {{ $gap }} scroll-mt-24 border-t border-line pt-6" aria-label="Team">
    <h2 class="label text-ink-muted">Team</h2>

    <div class="mt-10">
        {{-- Awalnya semua anggota hanya kartu. Mengeklik kartu membuka profilnya dengan halus di atas daftar (js/team.js);
             semua profil sudah ada di halaman. Tanpa JavaScript, kartu adalah tautan ?member= yang memuat ulang. --}}
        <section id="profile" data-team-profiles data-open="{{ $selectedId ? 'true' : 'false' }}" aria-live="polite" class="team-drawer scroll-mt-24">
            <div>
                <div class="pb-16 pt-10">
                    @foreach ($members as $member)
                        @php $photo = $photos->get($member['id']); @endphp

                        <div data-profile="{{ $member['id'] }}" @unless ($member['id'] === $selectedId) hidden @endunless
                             class="grid gap-10 md:grid-cols-12 md:items-start md:gap-x-6">
                            <div class="md:col-span-4">
                                <div class="aspect-[4/5] overflow-hidden bg-bg-soft">
                                    @if ($photo)
                                        <x-responsive-image :usage="$photo" :alt="$member['name']" sizes="(min-width: 768px) 33vw, 100vw" cover />
                                    @endif
                                </div>
                            </div>
                            <div class="md:col-span-7 md:col-start-6">
                                <div class="flex items-start justify-between gap-6">
                                    <h2 tabindex="-1" class="text-display font-medium uppercase leading-[1.05] tracking-[-0.02em] focus:outline-none">{{ $member['name'] }}</h2>
                                    <a href="{{ $teamUrl }}" data-team-close class="label -mr-2 flex min-h-11 shrink-0 items-center px-2 text-ink-muted hover:text-ink">Close &times;</a>
                                </div>
                                @if ($member['position'])
                                    <p class="label mt-4 text-ink-muted">{{ $member['position'] }}</p>
                                @endif
                                @if ($member['bio'])
                                    <x-prose :text="$member['bio']" class="mt-10 max-w-[62ch] text-lead" />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Kartu semua anggota; kartu yang profilnya terbuka ditandai. --}}
        <section class="mt-4 border-t border-line pt-14" aria-label="Team members">
            <ul class="grid grid-cols-2 gap-x-6 gap-y-14 md:grid-cols-4">
                @foreach ($members as $member)
                    @php $cardPhoto = $photos->get($member['id']); @endphp
                    <li data-member-card="{{ $member['id'] }}">
                        <a href="{{ $teamUrl.'?member='.$member['id'].'#profile' }}" data-member="{{ $member['id'] }}"
                           @if ($member['id'] === $selectedId) aria-current="true" @endif class="group block">
                            <div class="aspect-[4/5] overflow-hidden bg-bg-soft">
                                @if ($cardPhoto)
                                    <x-responsive-image :usage="$cardPhoto" :alt="$member['name']" sizes="(min-width: 768px) 25vw, 50vw" cover
                                        class="transition-transform duration-500 ease-out group-hover:scale-[1.04] motion-reduce:transition-none" />
                                @endif
                            </div>
                            <p class="member-name mt-3 text-small font-medium uppercase tracking-[0.08em]">{{ $member['name'] }}</p>
                            @if ($member['position'])
                                <p class="mt-1 text-label uppercase tracking-[0.08em] text-ink-muted">{{ $member['position'] }}</p>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</section>
