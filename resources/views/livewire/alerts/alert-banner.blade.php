@php
    $icon = $getIcon();
    $status = $getStatus() ?? 'info';
    $title = $getTitle();
    $body = $getBody();
    $actions = $getActions();
@endphp

<x-filament::callout
    :icon="$icon"
    :color="$status"
>
    @if (filled($title))
        <x-slot name="heading">
            {{str($title)->sanitizeHtml()->toHtmlString()}}
        </x-slot>
    @endif

    @if (filled($body))
        <x-slot name="description">
            {{str($body)->sanitizeHtml()->toHtmlString()}}
        </x-slot>
    @endif

    @if (count($actions) > 0)
        <x-slot name="footer">
            @foreach ($actions as $action)
                {{ $action }}
            @endforeach
        </x-slot>
    @endif
    
    @if ($isCloseable())
        <x-slot name="controls">
            <x-filament::icon-button
                icon="tabler-x"
                color="gray"
                label="Close"
                wire:click="remove('{{$getId()}}')"
            />
        </x-slot>
    @endif
</x-filament::callout>
