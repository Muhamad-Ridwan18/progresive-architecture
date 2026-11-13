<x-filament-widgets::widget>
    @include('filament.widgets._dashboard-styles')

    <section class="dx-att" aria-label="Perlu perhatian hari ini">
        @foreach ($this->items() as $item)
            <a href="{{ $item['url'] }}">
                <span class="dx-ico dx-c-{{ $item['color'] }}"><x-filament::icon :icon="$item['icon']" /></span>
                <span>
                    <b>{{ $item['count'] }}</b>
                    <span class="t">{{ $item['label'] }}</span>
                </span>
                <span class="go"><x-filament::icon icon="heroicon-o-arrow-right" /></span>
            </a>
        @endforeach
    </section>
</x-filament-widgets::widget>
