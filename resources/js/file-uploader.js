const MAX_CONCURRENT = 3;
const LARGE_BATCH = 500;
const UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

// Queue rows are keyed on this, since the same file can legitimately be queued twice.
let nextEntryId = 0;

/**
 * Everything below `/plugins` and friends is relative to the server root, so keep the leading
 * slash but drop empty and self segments picked up from the dragged folder names.
 */
const directoryFor = (entry) =>
    '/' +
    [entry.basePath, entry.path]
        .flatMap((part) => String(part ?? '').split('/'))
        .filter((part) => part !== '' && part !== '.')
        .join('/');

const formatBytes = (bytes) => {
    if (!bytes) {
        return '0.00 B';
    }

    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), UNITS.length - 1);

    return `${(bytes / 1024 ** index).toFixed(2)} ${UNITS[index]}`;
};

/** Depth-first walk of a dropped folder, keeping the relative path of every file. */
const walk = async (entry, path, files, directories) => {
    if (entry.isFile) {
        files.push({ file: await new Promise((resolve, reject) => entry.file(resolve, reject)), path });

        return;
    }

    if (!entry.isDirectory) {
        return;
    }

    const child = path ? `${path}/${entry.name}` : entry.name;
    const reader = entry.createReader();
    const children = [];

    // readEntries hands back at most 100 entries per call and an empty array when exhausted.
    for (;;) {
        const batch = await new Promise((resolve, reject) => reader.readEntries(resolve, reject));

        if (!batch.length) {
            break;
        }

        children.push(...batch);
    }

    // An empty directory contributes no files, so it has to be created explicitly or it is lost.
    if (!children.length) {
        directories.push(child);

        return;
    }

    await Promise.all(children.map((child_) => walk(child_, child, files, directories)));
};

const extract = async (dataTransfer) => {
    const files = [];
    const directories = [];

    const entries = [...dataTransfer.items].map((item) => item.webkitGetAsEntry?.()).filter(Boolean);

    if (entries.length) {
        await Promise.all(entries.map((entry) => walk(entry, '', files, directories)));
    } else {
        files.push(...[...dataTransfer.files].map((file) => ({ file, path: '' })));
    }

    return { files, directories };
};

/**
 * The drop zone and the browse button render in different parts of the page, so they each
 * hand their files to the manager through the same window event rather than sharing a scope.
 */
const dispatchUpload = (el, serverUuid, basePath, { files, directories }) => {
    if (!files.length && !directories.length) {
        return;
    }

    el.dispatchEvent(
        new CustomEvent('server-file-upload', {
            bubbles: true,
            detail: {
                files: files.map((entry) => ({ ...entry, basePath, serverUuid })),
                directories: directories.map((path) => ({ path, basePath, serverUuid })),
            },
        }),
    );
};

document.addEventListener('alpine:init', () => {
    Alpine.data('fileUploader', (config) => ({
        queue: [],
        open: false,
        minimized: false,
        activeWorkers: 0,
        stopped: false,
        stopReason: null,
        confirming: null,
        closeTimer: null,
        sizeLimits: {},
        unloadGuard: null,

        get pendingCount() {
            return this.queue.filter((entry) => entry.status === 'pending').length;
        },

        get finishedCount() {
            return this.queue.filter((entry) => entry.status !== 'pending' && entry.status !== 'uploading').length;
        },

        get failed() {
            return this.queue.filter((entry) => entry.status === 'error');
        },

        get busy() {
            return this.activeWorkers > 0 || this.pendingCount > 0;
        },

        /**
         * Entry point for the `server-file-upload` event. Files are appended to the one queue
         * that lives for as long as this component does, so a second drop while a batch is
         * still running just tops the workers back up instead of racing a new batch.
         */
        async enqueue({ files = [], directories = [] }, confirmed = false) {
            if (!files.length && !directories.length) {
                return;
            }

            // An accidental node_modules drop would otherwise build a queue big enough to hang
            // the tab, so a drop this size has to be confirmed first.
            if (files.length >= LARGE_BATCH && !confirmed) {
                this.confirming = { files, directories };
                this.open = true;
                this.minimized = false;

                return;
            }

            this.confirming = null;
            this.stopped = false;
            this.stopReason = null;
            this.open = true;
            clearTimeout(this.closeTimer);

            // Wings creates missing parent directories when it writes a file, so only the empty
            // ones in a dragged tree need creating up front.
            for (const directory of directories) {
                await this.call('createFolder', directory.serverUuid, directory.path, directory.basePath);
            }

            for (const item of files) {
                const limit = await this.uploadSizeLimit(item.serverUuid);
                const tooLarge = limit !== null && item.file.size > limit;

                this.queue.push({
                    id: ++nextEntryId,
                    reported: false,
                    file: item.file,
                    name: item.file.name,
                    path: item.path ?? '',
                    basePath: item.basePath ?? '/',
                    serverUuid: item.serverUuid,
                    size: item.file.size,
                    progress: 0,
                    speed: 0,
                    status: tooLarge ? 'error' : 'pending',
                    error: tooLarge
                        ? config.tooLarge.replace(':name', item.file.name).replace(':limit', formatBytes(limit))
                        : null,
                    xhr: null,
                });
            }

            this.guardUnload();
            this.drain();
        },

        confirmLargeBatch() {
            return this.enqueue(this.confirming, true);
        },

        drain() {
            while (!this.stopped && this.activeWorkers < MAX_CONCURRENT && this.nextPending()) {
                this.activeWorkers++;
                this.work();
            }

            if (!this.busy) {
                this.finish();
            }
        },

        nextPending() {
            return this.queue.find((entry) => entry.status === 'pending');
        },

        async work() {
            let entry;

            while (!this.stopped && (entry = this.nextPending())) {
                entry.status = 'uploading';
                await this.upload(entry);
            }

            this.activeWorkers--;

            if (!this.busy) {
                await this.finish();
            }
        },

        async upload(entry) {
            let url;

            try {
                // The daemon rejects a reused token (IsUniqueRequest), so every file needs its own.
                url = new URL(await this.fetchUploadUrl(entry.serverUuid));
            } catch {
                entry.status = 'error';
                entry.error = this.stopReason ?? config.tokenFailed;

                return;
            }

            url.searchParams.append('directory', directoryFor(entry));

            return new Promise((resolve) => {
                const xhr = new XMLHttpRequest();
                const body = new FormData();
                body.append('files', entry.file);
                entry.xhr = xhr;

                let lastLoaded = 0;
                let lastTime = performance.now();

                xhr.upload.addEventListener('progress', (e) => {
                    if (!e.lengthComputable) {
                        return;
                    }

                    entry.progress = Math.round((e.loaded / e.total) * 100);

                    const elapsed = (performance.now() - lastTime) / 1000;
                    if (elapsed > 0.1) {
                        entry.speed = (e.loaded - lastLoaded) / elapsed;
                        lastTime = performance.now();
                        lastLoaded = e.loaded;
                    }
                });

                xhr.onload = () => {
                    entry.xhr = null;

                    if (xhr.status >= 200 && xhr.status < 300) {
                        entry.status = 'complete';
                        entry.progress = 100;
                    } else {
                        entry.status = 'error';
                        entry.error = this.daemonError(xhr);
                    }

                    resolve();
                };

                xhr.onerror = () => {
                    entry.xhr = null;
                    entry.status = 'error';
                    entry.error = config.networkError;
                    resolve();
                };

                xhr.onabort = () => {
                    entry.xhr = null;
                    entry.status = 'error';
                    entry.error = this.stopReason ?? config.cancelled;
                    resolve();
                };

                xhr.open('POST', url.toString());
                xhr.send(body);
            });
        },

        /** The daemon answers with `{"error": "..."}`, which beats showing a bare status code. */
        daemonError(xhr) {
            try {
                return JSON.parse(xhr.responseText).error || `${config.uploadFailed} (${xhr.status})`;
            } catch {
                return `${config.uploadFailed} (${xhr.status})`;
            }
        },

        async fetchUploadUrl(serverUuid) {
            const response = await fetch(`/api/client/servers/${serverUuid}/files/upload`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                this.haltOnExpiredSession(response.status);

                throw new Error(`upload url request failed (${response.status})`);
            }

            return (await response.json()).attributes.url;
        },

        async uploadSizeLimit(serverUuid) {
            if (this.sizeLimits[serverUuid] === undefined) {
                const limit = await this.call('getUploadSizeLimit', serverUuid);

                // A failed lookup must not be cached, otherwise every later upload for this
                // server skips the size check for as long as the component lives.
                if (limit === null) {
                    return null;
                }

                this.sizeLimits[serverUuid] = limit;
            }

            return this.sizeLimits[serverUuid];
        },

        /**
         * A batch can outlive the session it started in, and a silently stalled queue is worse
         * than a stopped one, so treat an expired session as a hard stop with a readable reason.
         */
        async call(method, ...args) {
            try {
                return await this.$wire.call(method, ...args);
            } catch (error) {
                this.haltOnExpiredSession(error?.response?.status ?? error?.status);

                return null;
            }
        },

        haltOnExpiredSession(status) {
            if (status !== 401 && status !== 419) {
                return;
            }

            this.stop(config.sessionExpired);
        },

        stop(reason) {
            this.stopped = true;
            this.stopReason = reason;

            this.queue.forEach((entry) => {
                if (entry.status === 'uploading') {
                    entry.xhr?.abort();
                } else if (entry.status === 'pending') {
                    entry.status = 'error';
                    entry.error = reason;
                }
            });
        },

        cancelAll() {
            this.stop(config.cancelled);
        },

        retry(entry) {
            this.stopped = false;
            this.stopReason = null;
            entry.reported = false;
            entry.status = 'pending';
            entry.error = null;
            entry.progress = 0;
            entry.speed = 0;

            clearTimeout(this.closeTimer);
            this.guardUnload();
            this.drain();
        },

        async finish() {
            this.releaseUnload();

            // A batch ending in failure leaves its rows on screen and a later drop appends to
            // the same queue, so only entries that have not been reported yet belong to this
            // batch. Without that the earlier files are logged to the activity log twice and
            // counted again in the notification.
            const batch = this.queue.filter((entry) => !entry.reported);

            if (!batch.length) {
                return;
            }

            batch.forEach((entry) => (entry.reported = true));

            const uploaded = batch.filter((entry) => entry.status === 'complete');

            if (uploaded.length) {
                await this.logUploaded(uploaded);
                this.$wire.dispatch('server-files-uploaded');
            }

            this.notify(batch);

            if (!this.failed.length) {
                this.closeTimer = setTimeout(() => this.close(), 1000);
            }
        },

        /** Grouped so the activity log records one entry per server and target directory. */
        async logUploaded(uploaded) {
            const groups = {};

            for (const entry of uploaded) {
                const directory = directoryFor(entry);
                const key = `${entry.serverUuid}|${directory}`;
                groups[key] ??= { serverUuid: entry.serverUuid, directory, files: [] };
                groups[key].files.push(entry.name);
            }

            for (const group of Object.values(groups)) {
                await this.call('logUploadedFiles', group.serverUuid, group.files, group.directory);
            }
        },

        notify(batch) {
            const failed = batch.filter((entry) => entry.status === 'error').length;

            if (!failed) {
                return new window.FilamentNotification().title(config.success).success().send();
            }

            const title =
                failed === batch.length
                    ? config.failed
                    : config.partialFailure.replace(':failed', failed).replace(':total', batch.length);

            new window.FilamentNotification().title(title).danger().send();
        },

        close() {
            clearTimeout(this.closeTimer);
            this.open = false;
            this.minimized = false;
            this.confirming = null;
            this.queue = [];
        },

        /** SPA navigation keeps uploads alive; closing the tab is the one exit we can't survive. */
        guardUnload() {
            if (this.unloadGuard) {
                return;
            }

            this.unloadGuard = (e) => e.preventDefault();
            window.addEventListener('beforeunload', this.unloadGuard);
        },

        releaseUnload() {
            window.removeEventListener('beforeunload', this.unloadGuard);
            this.unloadGuard = null;
        },

        formatBytes,

        formatSpeed(bytesPerSecond) {
            return `${formatBytes(bytesPerSecond)}/s`;
        },
    }));

    Alpine.data('fileDropZone', (serverUuid) => ({
        isDragging: false,
        dragCounter: 0,

        onDragEnter(e) {
            e.preventDefault();
            this.dragCounter++;
            this.isDragging = true;
        },

        onDragLeave(e) {
            e.preventDefault();
            this.dragCounter--;
            this.isDragging = this.dragCounter > 0;
        },

        async onDrop(e) {
            e.preventDefault();
            this.isDragging = false;
            this.dragCounter = 0;

            dispatchUpload(this.$el, serverUuid, this.$wire.path, await extract(e.dataTransfer));
        },
    }));

    Alpine.data('fileBrowseButton', (serverUuid) => ({
        triggerBrowse() {
            this.$refs.fileInput.click();
        },

        onFileSelect(e) {
            dispatchUpload(this.$el, serverUuid, this.$wire.path, {
                files: [...e.target.files].map((file) => ({ file, path: '' })),
                directories: [],
            });

            e.target.value = '';
        },
    }));
});
