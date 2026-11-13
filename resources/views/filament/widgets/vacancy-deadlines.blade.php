<x-filament-widgets::widget>
    @include('filament.widgets._dashboard-styles')

    <x-filament::section heading="Rekrutmen: deadline lowongan">
        @forelse ($this->vacancies() as $vacancy)
            <div class="dx-dl">
                <a href="{{ $vacancy['url'] }}" style="flex: 1; min-width: 0">
                    <div style="font-weight: 600; font-size: .875rem">{{ $vacancy['title'] }}</div>
                    <div class="dx-muted" style="font-size: .75rem">{{ $vacancy['applicants'] }} pelamar</div>
                </a>
                <span class="dx-pill dx-p-{{ $vacancy['tone'] }}">
                    <x-filament::icon icon="heroicon-o-clock" />
                    {{ $vacancy['days'] === 0 ? 'Hari ini' : $vacancy['days'].' hari lagi' }}
                </span>
            </div>
        @empty
            <p class="dx-muted">Tidak ada lowongan terbuka dengan deadline.</p>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
