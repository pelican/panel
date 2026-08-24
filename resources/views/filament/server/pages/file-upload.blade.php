<div x-data="fileBrowseButton(@js(\Filament\Facades\Filament::getTenant()->uuid))">
    {{ $this->fileUploadAction }}
    <x-filament-actions::modals />
    <input type="file" x-ref="fileInput" class="hidden" multiple @change="onFileSelect($event)">
</div>
