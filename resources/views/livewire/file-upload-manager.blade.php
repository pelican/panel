@assets
    @vite('resources/js/file-uploader.js')
@endassets

<div
    x-data="fileUploader(@js([
        'success' => trans('server/file.actions.upload.success'),
        'failed' => trans('server/file.actions.upload.failed'),
        'partialFailure' => trans('server/file.actions.upload.partial_failure'),
        'tooLarge' => trans('server/file.actions.upload.too_large'),
        'uploadFailed' => trans('server/file.actions.upload.failed'),
        'networkError' => trans('server/file.actions.upload.network_error'),
        'tokenFailed' => trans('server/file.actions.upload.token_failed'),
        'sessionExpired' => trans('server/file.actions.upload.session_expired'),
        'cancelled' => trans('server/file.actions.upload.cancelled'),
    ]))"
    @server-file-upload.window="enqueue($event.detail)"
    @keydown.escape.window="open &amp;&amp; (busy ? (minimized = true) : close())"
>
    <div
        x-show="open && minimized"
        x-cloak
        class="fixed bottom-4 end-4 z-50 flex items-center gap-3 rounded-lg bg-white px-4 py-3 shadow-xl dark:bg-gray-800"
    >
        <span class="text-sm font-medium text-gray-950 dark:text-white">
            <span x-text="finishedCount"></span> / <span x-text="queue.length"></span>
        </span>
        <progress class="w-32" max="100" :value="queue.length ? (finishedCount / queue.length) * 100 : 0"></progress>
        <button
            type="button"
            @click="minimized = false"
            class="text-sm font-medium text-primary-600 dark:text-primary-400"
        >
            {{ trans('server/file.actions.upload.restore') }}
        </button>
    </div>

    <div
        x-show="open && !minimized"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4 dark:bg-gray-100/20"
    >
        <div
            x-trap.noscroll="open && !minimized"
            role="dialog"
            aria-modal="true"
            aria-labelledby="file-upload-heading"
            class="flex max-h-[50vh] w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-xl dark:bg-gray-800"
        >
            <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h3 id="file-upload-heading" class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ trans('server/file.actions.upload.header') }}
                    <span x-show="!confirming" class="text-gray-600 dark:text-gray-400" aria-live="polite">
                        <span x-text="finishedCount"></span> of <span x-text="queue.length"></span>
                    </span>
                </h3>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        x-show="busy"
                        @click="cancelAll()"
                        class="text-sm font-medium text-danger-600 dark:text-danger-400"
                    >
                        {{ trans('server/file.actions.upload.cancel') }}
                    </button>
                    <button
                        type="button"
                        x-show="busy"
                        @click="minimized = true"
                        class="text-sm font-medium text-gray-600 dark:text-gray-400"
                    >
                        {{ trans('server/file.actions.upload.minimize') }}
                    </button>
                    <button
                        type="button"
                        x-show="!busy"
                        @click="close()"
                        class="text-sm font-medium text-gray-600 dark:text-gray-400"
                    >
                        {{ trans('server/file.actions.upload.close') }}
                    </button>
                </div>
            </div>

            <div x-show="confirming" class="flex flex-col gap-4 px-6 py-6">
                <p class="text-sm text-gray-950 dark:text-white"
                   x-text="@js(trans('server/file.actions.upload.large_batch')).replace(':count', confirming?.files.length ?? 0)"></p>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="confirmLargeBatch()"
                        class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white"
                    >
                        {{ trans('server/file.actions.upload.upload_anyway') }}
                    </button>
                    <button
                        type="button"
                        @click="close()"
                        class="text-sm font-medium text-gray-600 dark:text-gray-400"
                    >
                        {{ trans('server/file.actions.upload.cancel') }}
                    </button>
                </div>
            </div>

            <div x-show="!confirming" class="flex-1 overflow-y-auto">
                <table class="w-full divide-y divide-gray-200 dark:divide-white/5">
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/5 dark:bg-gray-900">
                        <template x-for="entry in queue" :key="entry.name + entry.path + entry.size">
                            <tr class="transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-4 py-4 sm:px-6">
                                    <div class="flex flex-col gap-y-1">
                                        <div
                                            class="max-w-xs truncate text-sm font-medium leading-6 text-gray-950 dark:text-white"
                                            x-text="(entry.path ? entry.path + '/' : '') + entry.name"
                                        ></div>
                                        <div
                                            x-show="entry.status === 'error'"
                                            class="text-xs text-danger-600 dark:text-danger-400"
                                            x-text="entry.error"
                                        ></div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400 sm:px-6" x-text="formatBytes(entry.size)"></td>
                                <td class="px-4 py-4 sm:px-6">
                                    <div x-show="entry.status === 'uploading' || entry.status === 'complete'" class="flex items-center gap-2">
                                        <progress
                                            max="100"
                                            :value="entry.progress"
                                            :aria-label="entry.name"
                                            class="w-24"
                                        ></progress>
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="`${entry.progress}%`"></span>
                                        <span
                                            x-show="entry.status === 'uploading' && entry.speed > 0"
                                            class="text-sm text-gray-500 dark:text-gray-400"
                                            x-text="formatSpeed(entry.speed)"
                                        ></span>
                                    </div>
                                    <span x-show="entry.status === 'pending'" class="text-sm text-gray-500 dark:text-gray-400">
                                        &hellip;
                                    </span>
                                    <button
                                        type="button"
                                        x-show="entry.status === 'error'"
                                        @click="retry(entry)"
                                        class="text-sm font-medium text-primary-600 dark:text-primary-400"
                                    >
                                        {{ trans('server/file.actions.upload.retry') }}
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
