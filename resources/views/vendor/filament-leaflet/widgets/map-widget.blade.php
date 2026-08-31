@php
    $config = $this->getMapData();
@endphp


<x-filament-widgets::widget>

    <x-filament::section>
        <x-slot name="heading">
            {{ $this->getHeading() }}
        </x-slot>

        <x-filament-leaflet::map
            :config="$config"
            widget
        />

    </x-filament::section>

    <x-filament-actions::modals />

</x-filament-widgets::widget>
