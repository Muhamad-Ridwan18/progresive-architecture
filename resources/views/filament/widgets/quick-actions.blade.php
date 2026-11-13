<x-filament-widgets::widget>
    @include('filament.widgets._dashboard-styles')

    <x-filament::section heading="Aksi cepat">
        <div class="dx-qa">
            @foreach ($this->actions() as $action)
                <a href="{{ $action['url'] }}">
                    <span class="dx-ico"><x-filament::icon :icon="$action['icon']" /></span>
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
