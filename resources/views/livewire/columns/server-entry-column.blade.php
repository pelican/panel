@php
    /** @var \App\Models\Server $server */
    $server = $getRecord();
@endphp

<div class="w-full">
    @livewire('server-entry', ['server' => $server, 'lazy' => true], key($server->id))
</div>
