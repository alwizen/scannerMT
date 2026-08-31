@props([
    'config',
    'widget',
    'entry',
    'field',
    'column',
])

@php
    use Illuminate\Support\Js;
    $mapClass = match(true) {
        isset($field) => 'leafletMapField',
        isset($entry) => 'leafletMapEntry',
        isset($widget) => 'leafletMapWidget',
        isset($column) => 'leafletMapColumn',
    };
    $mapHeight = $config['mapHeight'] ?? 400;
@endphp

<div
    wire:ignore
    x-data="{{ $mapClass }}(
        $wire,
        {{ Js::from($config) }},
    )"
    {{
        $attributes->style([
            "height: {$mapHeight}px",
            "min-height: {$mapHeight}px",
            "width: 100%",
            "position: relative",
            "overflow: hidden"
        ])
    }}
>
    <div id="{{ $config['mapId'] }}" style="height: 100%; min-height: {{ $mapHeight }}px; width: 100%;"></div>

    @if (!empty($config['customStyles']))
        <style>
            {!! $config['customStyles'] !!}
        </style>
    @endif

    @if (!empty($config['customScripts']))
        <script>
            {!! $config['customScripts'] !!}
        </script>
    @endif
</div>