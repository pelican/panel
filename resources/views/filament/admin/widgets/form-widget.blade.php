<x-filament-widgets::widget>
    {{ $this->form }}

    @if ($this instanceof \Filament\Actions\Contracts\HasActions)
        <x-filament-actions::modals />
    @endif
</x-filament-widgets::widget>
