<div class="node-mass-action-table flex flex-col gap-4">
    <style>
        .node-mass-action-table .fi-ta-content-ctn {
            max-height: 45vh;
            overflow-y: auto;
        }

        /* The header row's own background is translucent in dark mode, which would let rows show through */
        .node-mass-action-table .fi-ta-table thead {
            position: sticky;
            top: 0;
            z-index: 1;
            background-color: var(--color-white, #fff);
        }

        .dark .node-mass-action-table .fi-ta-table thead {
            background-color: var(--gray-900);
        }
    </style>

    {{ $this->form }}

    {{ $this->table }}

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $this->summary() }}</p>

        <div class="flex gap-3">
            <x-filament::button color="gray" x-on:click="$dispatch('mass-action-close')">
                {{ trans('admin/node.mass_actions.cancel') }}
            </x-filament::button>

            <x-filament::button wire:click="submit" wire:loading.attr="disabled" wire:target="submit">
                {{ trans('admin/node.mass_actions.submit') }}
            </x-filament::button>
        </div>
    </div>

    <x-filament-actions::modals />
</div>
