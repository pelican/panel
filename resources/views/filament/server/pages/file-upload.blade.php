<div
    x-data="{
        handleFileSelect(e) {
            const files = Array.from(e.target.files);
            if (files.length > 0) {
                this.$dispatch('server-file-upload', files.map(f => ({
                    file: f,
                    path: '',
                })));
            }
            e.target.value = '';
        },
        triggerBrowse() {
            this.$refs.fileInput.click();
        },
    }"
>
    {{ $this->fileUploadAction }}
    <x-filament-actions::modals />
    <input type="file" x-ref="fileInput" class="hidden" multiple @change="handleFileSelect">
</div>
