<x-filament-panels::page>
    <div
        x-data="
        {
            serverUuid: @js(\Filament\Facades\Filament::getTenant()->uuid),
            currentPath: @js($this->path),
            isDragging: false,
            dragCounter: 0,

            handleDragEnter(e) {
                e.preventDefault();
                e.stopPropagation();
                this.dragCounter++;
                this.isDragging = true;
            },
            handleDragLeave(e) {
                e.preventDefault();
                e.stopPropagation();
                this.dragCounter--;
                if (this.dragCounter === 0) this.isDragging = false;
            },
            handleDragOver(e) {
                e.preventDefault();
                e.stopPropagation();
            },
            async handleDrop(e) {
                e.preventDefault();
                e.stopPropagation();
                this.isDragging = false;
                this.dragCounter = 0;

                const items = e.dataTransfer.items;
                const files = e.dataTransfer.files;

                if ((!items || items.length === 0) && (!files || files.length === 0)) return;

                let filesWithPaths = [];

                if (items && items.length > 0 && items[0].webkitGetAsEntry) {
                    filesWithPaths = await this.extractFilesFromItems(items);
                }

                if (files && files.length > 0 && filesWithPaths.length === 0) {
                    filesWithPaths = Array.from(files).map(f => ({
                        file: f,
                        path: ''
                    }));
                }

                if (filesWithPaths.length > 0) {
                    this.$dispatch('server-file-upload', filesWithPaths.map(f => ({
                        file: f.file,
                        path: f.path,
                        basePath: this.currentPath,
                        serverUuid: this.serverUuid,
                    })));
                }
            },

            async extractFilesFromItems(items) {
                const filesWithPaths = [];
                const traversePromises = [];

                for (let i = 0; i < items.length; i++) {
                    const entry = items[i].webkitGetAsEntry?.();

                    if (entry) {
                        traversePromises.push(this.traverseFileTree(entry, '', filesWithPaths));
                    } else if (items[i].kind === 'file') {
                        const file = items[i].getAsFile();
                        if (file) {
                            filesWithPaths.push({
                                file: file,
                                path: '',
                            });
                        }
                    }
                }

                await Promise.all(traversePromises);

                return filesWithPaths;
            },

            async traverseFileTree(entry, path, filesWithPaths) {
                return new Promise((resolve) => {
                    if (entry.isFile) {
                        entry.file((file) => {
                            filesWithPaths.push({
                                file: file,
                                path: path,
                            });
                            resolve();
                        });
                    } else if (entry.isDirectory) {
                        const reader = entry.createReader();
                        const readEntries = () => {
                            reader.readEntries(async (entries) => {
                                if (entries.length === 0) {
                                    resolve();
                                    return;
                                }

                                const subPromises = entries.map((e) =>
                                    this.traverseFileTree(
                                        e,
                                        path ? `${path}/${entry.name}` : entry.name,
                                        filesWithPaths
                                    )
                                );

                                await Promise.all(subPromises);
                                readEntries();
                            });
                        };
                        readEntries();
                    } else {
                        resolve();
                    }
                });
            },
        }"
        @dragenter.window="handleDragEnter($event)"
        @dragleave.window="handleDragLeave($event)"
        @dragover.window="handleDragOver($event)"
        @drop.window="handleDrop($event)"
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
                    <svg xmlns="http://www.w3.org/2000/svg"
                         class="icon icon-tabler icons-tabler-outline icon-tabler-upload size-12 text-success-500"
                         viewBox="0 0 36 36" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                        <path d="M7 9l5 -5l5 5" />
                        <path d="M12 4l0 12" />
                    </svg>
                    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ trans('server/file.actions.upload.drop_files') }}
                    </p>
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
