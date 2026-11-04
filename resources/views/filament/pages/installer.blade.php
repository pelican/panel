<x-filament-panels::page.simple>
    {{-- Load the lists once the form's components are ready: re-rendering one whose script is still loading throws errors. --}}
    <form
        wire:submit="submit"
        x-init="
            const startedAt = Date.now()
            const timer = setInterval(() => {
                const isReady = [...$el.querySelectorAll('[x-load]')].every((component) => component._x_dataStack)

                if (! isReady && Date.now() - startedAt < 5000) {
                    return
                }

                clearInterval(timer)
                $wire.loadCatalogs()
            }, 50)
        "
    >
        {{ $this->form }}
    </form>
    <x-filament-actions::modals />
</x-filament-panels::page.simple>
