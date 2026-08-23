@php
    $actiongroup = \App\Filament\App\Resources\Servers\Pages\ListServers::getPowerActionGroup()->record($server);
    $backgroundImage = $server->icon ?? $server->egg->icon;

    $serverEntryColumn = $column ?? \App\Filament\Components\Tables\Columns\ServerEntryColumn::make('server_entry');
    $serverNodeStatistics = $server->node->statistics();
    $serverNodeSystemInfo = $server->node->systemInformation();

    $warningPercent = $serverEntryColumn->getWarningThresholdPercent() ?? 0.7;
    $dangerPercent = $serverEntryColumn->getDangerThresholdPercent() ?? 0.9;
@endphp
<div wire:poll.15s
     class="relative cursor-pointer"
     x-on:click="{{ $component->redirectUrl() }}"
     x-on:auxclick.prevent="if ($event.button === 1) {{ $component->redirectUrl(true) }}">

    <div class="absolute left-0 top-1 bottom-0 w-1 rounded-lg fi-color fi-color-{{ $server->condition->getColor() }} fi-bg-color-600" style="background-color: var(--bg);"> </div>

    <div class="flex-1 dark:bg-gray-800 dark:text-white rounded-lg overflow-hidden p-3">
        @if($backgroundImage)
            <div style="
                position: absolute;
                inset: 0;
                background: url('{{ $backgroundImage }}') right no-repeat;
                background-size: contain;
                opacity: 0.20;
                max-width: 680px;
                max-height: 140px;
            "></div>
        @endif

        <div @class([
            'flex items-center gap-2',
            'mb-5' => !$server->description,
        ])>
            <x-filament::icon-button
                :icon="$server->condition->getIcon()"
                :color="$server->condition->getColor()"
                :tooltip="$server->condition->getLabel()"
                size="lg"
            />
            <h2 class="text-xl font-bold">
                {{ $server->name }}
                <span class="dark:text-gray-400">
                    ({{ $server->formatResource(\App\Enums\ServerResourceType::Uptime) }})
                </span>
            </h2>
            @if ($actiongroup->isVisible())
                <div class="end-0">
                    <div class="flex-1 dark:bg-gray-800 dark:text-white rounded-b-lg overflow-hidden p-1"
                         x-on:click.stop>
                        {{ $actiongroup }}
                    </div>
                </div>
            @endif
        </div>

        @if ($server->description)
            <div class="text-left mb-1 ml-4 pl-4">
                <p class="text-base dark:text-gray-400">{{ Str::limit($server->description, 40, preserveWords: true) }}</p>
            </div>
        @endif

        <div class="flex justify-between text-center items-center gap-4">
            <div class="w-full max-w-xs">
                @php
                    $cpuCurrent = \App\Enums\ServerResourceType::CPU->getResourceAmount($server);
                    $cpuMax = \App\Enums\ServerResourceType::CPULimit->getResourceAmount($server) === 0 ? (($serverNodeSystemInfo['cpu_count'] ?? 0) * 100) : \App\Enums\ServerResourceType::CPULimit->getResourceAmount($server);
                    $getState = fn() => $cpuCurrent;
                    $getMaxValue = fn() => $cpuMax;
                    $getProgressPercentage = fn() => $cpuMax > 0 ? ($cpuCurrent / $cpuMax) * 100 : 0;
                    $getProgressLabel = fn () => $server->formatResource(\App\Enums\ServerResourceType::CPU, 0) . ' / ' . $server->formatResource(\App\Enums\ServerResourceType::CPULimit, 0);
                    $getProgressStatus = fn() => ($cpuMax > 0 && ($cpuCurrent / $cpuMax) * 100 >= ($dangerPercent * 100)) ? 'danger' : (( $cpuMax > 0 && ($cpuCurrent / $cpuMax) * 100 >= ($warningPercent * 100)) ? 'warning' : 'success');
                    $getProgressColor = fn() => $serverEntryColumn->getProgressColorForStatus($getProgressStatus());
                @endphp

                @include('livewire.columns.progress-bar-column', [
                    'getState' => $getState,
                    'getMaxValue' => $getMaxValue,
                    'getProgressPercentage' => $getProgressPercentage,
                    'getProgressLabel' => $getProgressLabel,
                    'getProgressStatus' => $getProgressStatus,
                    'getProgressColor' => $getProgressColor,
                ])
            </div>

            <div class="w-full max-w-xs">
                @php
                    $memCurrent = \App\Enums\ServerResourceType::Memory->getResourceAmount($server);
                    $memMax = \App\Enums\ServerResourceType::MemoryLimit->getResourceAmount($server) === 0 ? $serverNodeStatistics['memory_total'] : \App\Enums\ServerResourceType::MemoryLimit->getResourceAmount($server);
                    $getState = fn() => $memCurrent;
                    $getMaxValue = fn() => $memMax > 0 ? $memMax : null;
                    $getProgressPercentage = fn() => ($memMax > 0) ? ($memCurrent / $memMax) * 100 : 0;
                    $getProgressLabel = fn() => $server->formatResource(\App\Enums\ServerResourceType::Memory) . ' / ' . $server->formatResource(\App\Enums\ServerResourceType::MemoryLimit);
                    $getProgressStatus = fn() => ($memMax > 0 && ($memCurrent / $memMax) * 100 >= ($dangerPercent * 100)) ? 'danger' : (( $memMax > 0 && ($memCurrent / $memMax) * 100 >= ($warningPercent * 100)) ? 'warning' : 'success');
                    $getProgressColor = fn() => $serverEntryColumn->getProgressColorForStatus($getProgressStatus());
                @endphp

                @include('livewire.columns.progress-bar-column', [
                    'getState' => $getState,
                    'getMaxValue' => $getMaxValue,
                    'getProgressPercentage' => $getProgressPercentage,
                    'getProgressLabel' => $getProgressLabel,
                    'getProgressStatus' => $getProgressStatus,
                    'getProgressColor' => $getProgressColor,
                ])
            </div>

            <div class="w-full max-w-xs">
                @php
                    $diskCurrent = \App\Enums\ServerResourceType::Disk->getResourceAmount($server);
                    $diskMax = \App\Enums\ServerResourceType::DiskLimit->getResourceAmount($server) === 0 ? $serverNodeStatistics['disk_total'] : \App\Enums\ServerResourceType::DiskLimit->getResourceAmount($server);
                    $getState = fn() => $diskCurrent;
                    $getMaxValue = fn() => $diskMax > 0 ? $diskMax : null;
                    $getProgressPercentage = fn() => ($diskMax > 0) ? ($diskCurrent / $diskMax) * 100 : 0;
                    $getProgressLabel = fn() => $server->formatResource(\App\Enums\ServerResourceType::Disk) . ' / ' . $server->formatResource(\App\Enums\ServerResourceType::DiskLimit);
                    $getProgressStatus = fn() => ($diskMax > 0 && ($diskCurrent / $diskMax) * 100 >= ($dangerPercent * 100)) ? 'danger' : (( $diskMax > 0 && ($diskCurrent / $diskMax) * 100 >= ($warningPercent * 100)) ? 'warning' : 'success');
                    $getProgressColor = fn() => $serverEntryColumn->getProgressColorForStatus($getProgressStatus());
                @endphp

                @include('livewire.columns.progress-bar-column', [
                    'getState' => $getState,
                    'getMaxValue' => $getMaxValue,
                    'getProgressPercentage' => $getProgressPercentage,
                    'getProgressLabel' => $getProgressLabel,
                    'getProgressStatus' => $getProgressStatus,
                    'getProgressColor' => $getProgressColor,
                ])
            </div>

            <div class="hidden sm:block">
                <p class="text-sm dark:text-gray-400">{{ trans('server/dashboard.network') }}</p>
                <p class="text-md font-semibold">{{ $server->allocation?->display_address ?? trans('server/dashboard.none') }}</p>
            </div>
        </div>
    </div>
</div>
