<x-filament-panels::page>
    <div
        x-data="
        {
            serverUuid: @js(\Filament\Facades\Filament::getTenant()->uuid),
            isDragging: false,
            dragCounter: 0,
            isUploading: false,
            isMinimized: false,
            uploadQueue: [],
            totalFiles: 0,
            autoCloseTimer: null,

            get completedCount() {
                return this.uploadQueue.filter(f => f.status === 'complete').length;
            },
            get failedCount() {
                return this.uploadQueue.filter(f => f.status === 'error').length;
            },
            get hasActiveUploads() {
                return this.uploadQueue.some(f => f.status === 'uploading' || f.status === 'pending');
            },
            get totalBytes() {
                return this.uploadQueue.reduce((sum, f) => sum + f.size, 0);
            },
            get uploadedBytesTotal() {
                return this.uploadQueue.reduce((sum, f) => sum + (f.status === 'complete' ? f.size : (f.uploadedBytes || 0)), 0);
            },
            get overallProgress() {
                return this.totalBytes > 0 ? Math.round((this.uploadedBytesTotal / this.totalBytes) * 100) : 0;
            },

            async fetchUploadUrl() {
                const r = await fetch(`/api/client/servers/${this.serverUuid}/files/upload`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!r.ok) throw new Error(`upload url request failed (${r.status})`);
                return (await r.json()).attributes.url;
            },

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
                    await this.uploadFilesWithFolders(filesWithPaths);
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
            async uploadFilesWithFolders(filesWithPaths) {
                if (this.autoCloseTimer) {
                    clearTimeout(this.autoCloseTimer);
                    this.autoCloseTimer = null;
                }

                this.isUploading = true;
                this.isMinimized = false;
                this.uploadQueue = [];
                this.totalFiles = 0;

                try {
                    const uploadSizeLimit = await $wire.getUploadSizeLimit();

                    for (const {
                            file
                        }
                        of filesWithPaths) {
                        if (file.size > uploadSizeLimit) {
                            new window.FilamentNotification()
                                .title(`File ${file.name} exceeds the upload limit.`)
                                .danger()
                                .send();
                            this.isUploading = false;
                            return;
                        }
                    }

                    const folderPaths = new Set();
                    for (const {
                            path
                        }
                        of filesWithPaths) {
                        if (path) {
                            const parts = path.split('/').filter(Boolean);
                            let currentPath = '';
                            for (const part of parts) {
                                currentPath += part + '/';
                                folderPaths.add(currentPath);
                            }
                        }
                    }

                    for (const folderPath of folderPaths) {
                        try {
                            await $wire.createFolder(folderPath.slice(0, -1));
                        } catch (error) {
                            console.warn(`Folder ${folderPath} already exists or failed to create.`);
                        }
                    }

                    for (const f of filesWithPaths) {
                        this.uploadQueue.push({
                            file: f.file,
                            name: f.file.name,
                            path: f.path,
                            size: f.file.size,
                            progress: 0,
                            speed: 0,
                            uploadedBytes: 0,
                            status: 'pending',
                            error: null
                        });
                    }
                    this.totalFiles = this.uploadQueue.length;

                    await this.runQueue();
                } catch (error) {
                    console.error('Upload error:', error);
                    new window.FilamentNotification()
                            .title('{{ preg_replace("/'/", "\\'", trans('server/file.actions.upload.error')) }}')
                        .danger()
                        .send();
                    if (this.uploadQueue.length === 0) {
                        this.isUploading = false;
                    }
                }
            },

            async runQueue() {
                const maxConcurrent = 3;
                const executing = new Set();
                const newlyUploaded = [];

                for (let i = 0; i < this.uploadQueue.length; i++) {
                    const item = this.uploadQueue[i];
                    if (item.status !== 'pending') continue;

                    const uploadPromise = this.uploadFile(i)
                        .then(() => {
                            const relativePath = (item.path ? item.path.replace(/^\/+/, '') + '/' : '') + item.name;
                            newlyUploaded.push(relativePath);
                        })
                        .catch(() => {})
                        .finally(() => executing.delete(uploadPromise));

                    executing.add(uploadPromise);

                    if (executing.size >= maxConcurrent) {
                        await Promise.race(executing);
                    }
                }

                await Promise.allSettled(executing);
                await this.finishRun(newlyUploaded);
            },

            async finishRun(newlyUploaded) {
                await $wire.$refresh();

                if (newlyUploaded.length > 0) {
                    this.$nextTick(() => {
                        if (typeof $wire !== 'undefined' && $wire && typeof $wire.call === 'function') {
                            $wire.call('logUploadedFiles', newlyUploaded);
                        } else if (typeof window.livewire !== 'undefined' && typeof window.livewire.call === 'function') {
                            window.livewire.call('logUploadedFiles', newlyUploaded);
                        } else if (typeof Livewire !== 'undefined' && typeof Livewire.call === 'function') {
                            Livewire.call('logUploadedFiles', newlyUploaded);
                        } else {
                            console.warn('Could not call Livewire method logUploadedFiles; Livewire not found.');
                        }
                    });
                }

                if (this.failedCount === 0) {
                    new window.FilamentNotification()
                        .title('{{ preg_replace("/'/", "\\'", trans('server/file.actions.upload.success')) }}')
                        .success()
                        .send();

                    this.autoCloseTimer = setTimeout(() => {
                        this.isUploading = false;
                        this.isMinimized = false;
                        this.uploadQueue = [];
                        this.totalFiles = 0;
                    }, 1000);
                } else {
                    new window.FilamentNotification()
                        .title('{{ preg_replace("/'/", "\\'", trans('server/file.actions.upload.failed')) }}')
                        .danger()
                        .send();
                }
            },

            async retryUpload(index) {
                if (this.hasActiveUploads) return;
                this.resetFileEntry(this.uploadQueue[index]);
                await this.runQueue();
            },

            async retryFailedUploads() {
                if (this.hasActiveUploads) return;
                for (const f of this.uploadQueue) {
                    if (f.status === 'error') this.resetFileEntry(f);
                }
                await this.runQueue();
            },

            resetFileEntry(fileData) {
                fileData.status = 'pending';
                fileData.progress = 0;
                fileData.speed = 0;
                fileData.uploadedBytes = 0;
                fileData.error = null;
            },

            async uploadFile(index) {
                const fileData = this.uploadQueue[index];
                fileData.status = 'uploading';
                try {
                    const uploadUrl = await this.fetchUploadUrl();
                    const url = new URL(uploadUrl);
                    let basePath = @js($this->path);

                    if (fileData.path && fileData.path.trim() !== '') {
                        basePath = basePath.replace(/\/+$/, '') + '/' + fileData.path.replace(/^\/+/, '');
                    }

                    url.searchParams.append('directory', basePath);

                    return new Promise((resolve, reject) => {
                        const xhr = new XMLHttpRequest();
                        const formData = new FormData();
                        formData.append('files', fileData.file);

                        let lastLoaded = 0;
                        let lastTime = Date.now();

                        xhr.upload.addEventListener('progress', (e) => {
                            if (e.lengthComputable) {
                                fileData.uploadedBytes = e.loaded;
                                fileData.progress = Math.round((e.loaded / e.total) * 100);

                                const now = Date.now();
                                const timeDiff = (now - lastTime) / 1000;
                                if (timeDiff > 0.1) {
                                    const bytesDiff = e.loaded - lastLoaded;
                                    fileData.speed = bytesDiff / timeDiff;
                                    lastTime = now;
                                    lastLoaded = e.loaded;
                                }
                            }
                        });

                        xhr.onload = () => {
                            if (xhr.status >= 200 && xhr.status < 300) {
                                fileData.status = 'complete';
                                fileData.progress = 100;
                                resolve();
                            } else {
                                fileData.status = 'error';
                                fileData.error = `Upload failed (${xhr.status})`;
                                reject(new Error(fileData.error));
                            }
                        };

                        xhr.onerror = () => {
                            fileData.status = 'error';
                            fileData.error = 'Network error';
                            reject(new Error('Network error'));
                        };

                        xhr.open('POST', url.toString());
                        xhr.send(formData);
                    });
                } catch (err) {
                    fileData.status = 'error';
                    fileData.error = 'Failed to get upload token';
                    throw err;
                }
            },

            formatBytes(bytes) {
                if (bytes === 0) return '0.00 B';
                const k = 1024;
                const sizes = ['B', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i];
            },
            formatSpeed(bytesPerSecond) {
                return this.formatBytes(bytesPerSecond) + '/s';
            },
            failedCountText() {
                return @js(trans('server/file.actions.upload.files_failed')).replace(':count', this.failedCount);
            },
            minimizeUpload() {
                this.isMinimized = true;
            },
            expandUpload() {
                this.isMinimized = false;
            },
            closeUploadOverlay() {
                if (this.hasActiveUploads) return;
                if (this.autoCloseTimer) {
                    clearTimeout(this.autoCloseTimer);
                    this.autoCloseTimer = null;
                }
                this.isUploading = false;
                this.isMinimized = false;
                this.uploadQueue = [];
                this.totalFiles = 0;
            },
            handleEscapeKey(e) {
                if (e.key === 'Escape' && this.isUploading && !this.isMinimized) {
                    this.minimizeUpload();
                }
            },
            handleBeforeUnload(e) {
                if (this.hasActiveUploads) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            },
        }"
        @dragenter.window="handleDragEnter($event)"
        @dragleave.window="handleDragLeave($event)"
        @dragover.window="handleDragOver($event)"
        @drop.window="handleDrop($event)"
        @keydown.window="handleEscapeKey($event)"
        @beforeunload.window="handleBeforeUnload($event)"
        @server-file-upload.window="uploadFilesWithFolders($event.detail)"
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

        <div
            x-show="isUploading && !isMinimized"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 dark:bg-gray-100/20 p-4"
        >
            <div
                class="rounded-lg bg-white shadow-xl dark:bg-gray-800 w-full max-w-1/2 max-h-[60vh] overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ trans('server/file.actions.upload.header') }} -
                            <span class="text-lg text-gray-600 dark:text-gray-400">
                                <span x-text="completedCount"></span> of <span x-text="totalFiles"></span>
                            </span>
                        </h3>
                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                @click="minimizeUpload()"
                                title="{{ trans('server/file.actions.upload.minimize') }}"
                                class="p-1.5 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-white/5"
                            >
                                <x-filament::icon icon="tabler-minus" class="w-5 h-5" />
                            </button>
                            <button
                                type="button"
                                x-show="!hasActiveUploads"
                                @click="closeUploadOverlay()"
                                title="{{ trans('server/file.actions.upload.close') }}"
                                class="p-1.5 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-white/5"
                            >
                                <x-filament::icon icon="tabler-x" class="w-5 h-5" />
                            </button>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div
                            class="relative rounded-full overflow-hidden w-full h-2 bg-gray-200 dark:bg-gray-700"
                            role="progressbar"
                            :aria-valuenow="overallProgress"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-label="{{ trans('server/file.actions.upload.header') }}"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-300 ease-in-out"
                                :class="failedCount > 0 && !hasActiveUploads ? 'bg-danger-600' : 'bg-primary-600'"
                                :style="`width: ${overallProgress}%`"
                            ></div>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span x-text="`${formatBytes(uploadedBytesTotal)} / ${formatBytes(totalBytes)}`"></span>
                            <span x-text="`${overallProgress}%`"></span>
                        </div>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <div class="overflow-hidden">
                        <table class="w-full divide-y divide-gray-200 dark:divide-white/5">
                            <tbody class="divide-y divide-gray-200 dark:divide-white/5 bg-white dark:bg-gray-900">
                            <template x-for="(fileData, index) in uploadQueue" :key="index">
                                <tr class="transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="px-4 py-4 sm:px-6">
                                        <div class="flex flex-col gap-y-1.5">
                                            <div class="flex items-center gap-2">
                                                <svg
                                                    x-show="fileData.status === 'uploading'"
                                                    class="w-4 h-4 shrink-0 animate-spin text-primary-600"
                                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                >
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                                <x-filament::icon
                                                    x-show="fileData.status === 'complete'"
                                                    icon="tabler-check"
                                                    class="w-4 h-4 shrink-0 text-success-500"
                                                />
                                                <x-filament::icon
                                                    x-show="fileData.status === 'error'"
                                                    icon="tabler-alert-circle"
                                                    class="w-4 h-4 shrink-0 text-danger-500"
                                                />
                                                <div
                                                    class="text-sm font-medium leading-6 text-gray-950 dark:text-white truncate max-w-xs"
                                                    x-text="(fileData.path ? fileData.path + '/' : '') + fileData.name">
                                                </div>
                                            </div>
                                            <div
                                                class="relative rounded-full overflow-hidden w-full h-1.5 bg-gray-200 dark:bg-gray-700"
                                                role="progressbar"
                                                :aria-valuenow="fileData.progress"
                                                aria-valuemin="0"
                                                aria-valuemax="100"
                                                :aria-label="fileData.name"
                                            >
                                                <div
                                                    class="h-full rounded-full transition-all duration-300 ease-in-out"
                                                    :class="fileData.status === 'error' ? 'bg-danger-600' : 'bg-primary-600'"
                                                    :style="`width: ${fileData.progress}%`"
                                                ></div>
                                            </div>
                                            <div x-show="fileData.status === 'error'"
                                                 class="text-xs text-danger-600 dark:text-danger-400"
                                                 x-text="fileData.error"></div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 sm:px-6">
                                        <div class="text-sm text-gray-500 dark:text-gray-400"
                                             x-text="formatBytes(fileData.size)"></div>
                                    </td>
                                    <td class="px-4 py-4 sm:px-6">
                                        <div x-show="fileData.status === 'uploading' || fileData.status === 'complete'"
                                             class="flex justify-between items-center text-sm gap-2">
                                                <span class="font-medium text-gray-700 dark:text-gray-300"
                                                      x-text="`${fileData.progress}%`"></span>
                                            <span x-show="fileData.status === 'uploading' && fileData.speed > 0"
                                                  class="text-gray-500 dark:text-gray-400"
                                                  x-text="formatSpeed(fileData.speed)"></span>
                                        </div>
                                        <span x-show="fileData.status === 'pending'"
                                              class="text-sm text-gray-500 dark:text-gray-400">
                                                —
                                            </span>
                                        <button
                                            type="button"
                                            x-show="fileData.status === 'error'"
                                            @click="retryUpload(index)"
                                            :disabled="hasActiveUploads"
                                            title="{{ trans('server/file.actions.upload.retry') }}"
                                            class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-danger-600 hover:bg-danger-50 dark:text-danger-400 dark:hover:bg-danger-500/10 disabled:opacity-50 disabled:pointer-events-none"
                                        >
                                            <x-filament::icon icon="tabler-refresh" class="w-4 h-4" />
                                            {{ trans('server/file.actions.upload.retry') }}
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    x-show="failedCount > 0 && !hasActiveUploads"
                    class="px-6 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between gap-4"
                >
                    <span class="text-sm text-danger-600 dark:text-danger-400" x-text="failedCountText()"></span>
                    <button
                        type="button"
                        @click="retryFailedUploads()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                        <x-filament::icon icon="tabler-refresh" class="w-4 h-4" />
                        {{ trans('server/file.actions.upload.retry_failed') }}
                    </button>
                </div>
            </div>
        </div>

        <div
            x-show="isUploading && isMinimized"
            x-cloak
            class="fixed bottom-4 right-4 z-50 w-80 max-w-[calc(100vw-2rem)]"
        >
            <div class="rounded-lg bg-white shadow-xl dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <svg
                            x-show="hasActiveUploads"
                            class="w-4 h-4 shrink-0 animate-spin text-primary-600"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <x-filament::icon
                            x-show="!hasActiveUploads && failedCount > 0"
                            icon="tabler-alert-circle"
                            class="w-4 h-4 shrink-0 text-danger-500"
                        />
                        <x-filament::icon
                            x-show="!hasActiveUploads && failedCount === 0"
                            icon="tabler-check"
                            class="w-4 h-4 shrink-0 text-success-500"
                        />
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                            {{ trans('server/file.actions.upload.header') }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            x-show="failedCount > 0 && !hasActiveUploads"
                            @click="retryFailedUploads()"
                            title="{{ trans('server/file.actions.upload.retry_failed') }}"
                            class="p-1.5 rounded-md text-danger-500 hover:bg-danger-50 dark:text-danger-400 dark:hover:bg-danger-500/10"
                        >
                            <x-filament::icon icon="tabler-refresh" class="w-4 h-4" />
                        </button>
                        <button
                            type="button"
                            @click="expandUpload()"
                            title="{{ trans('server/file.actions.upload.expand') }}"
                            class="p-1.5 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-white/5"
                        >
                            <x-filament::icon icon="tabler-arrows-maximize" class="w-4 h-4" />
                        </button>
                        <button
                            type="button"
                            x-show="!hasActiveUploads"
                            @click="closeUploadOverlay()"
                            title="{{ trans('server/file.actions.upload.close') }}"
                            class="p-1.5 rounded-md text-gray-500 hover:text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-white/5"
                        >
                            <x-filament::icon icon="tabler-x" class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <div
                    class="mt-2 relative rounded-full overflow-hidden w-full h-2 bg-gray-200 dark:bg-gray-700"
                    role="progressbar"
                    :aria-valuenow="overallProgress"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="{{ trans('server/file.actions.upload.header') }}"
                >
                    <div
                        class="h-full rounded-full transition-all duration-300 ease-in-out"
                        :class="failedCount > 0 && !hasActiveUploads ? 'bg-danger-600' : 'bg-primary-600'"
                        :style="`width: ${overallProgress}%`"
                    ></div>
                </div>
                <div class="mt-1 flex items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span><span x-text="completedCount"></span> of <span x-text="totalFiles"></span></span>
                    <span x-show="failedCount > 0" class="text-danger-600 dark:text-danger-400" x-text="failedCountText()"></span>
                    <span x-text="`${overallProgress}%`"></span>
                </div>
            </div>
        </div>

        {{ $this->table }}
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
