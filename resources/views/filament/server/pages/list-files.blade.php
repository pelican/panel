<x-filament-panels::page>
    <div
        x-data="fileDropZone(@js(\Filament\Facades\Filament::getTenant()->uuid))"
        @dragenter.window="onDragEnter($event)"
        @dragleave.window="onDragLeave($event)"
        @dragover.window.prevent
        @drop.window="onDrop($event)"
        class="relative"
    >
        <div
            x-show="isDragging"
            x-cloak
            x-transition:enter="transition-[opacity] duration-200 ease-out"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-[opacity] duration-150 ease-in"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 dark:bg-gray-100/20"
        >
            <div class="rounded-lg bg-white p-8 shadow-xl dark:bg-gray-800">
                <div class="flex flex-col items-center gap-4">
                    <x-filament::icon icon="tabler-upload" class="size-12 text-success-500" />
                    <p class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ trans('server/file.actions.upload.drop_files') }}
                    </p>
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
