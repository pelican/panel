<x-filament-panels::layout.simple>
    <div class="fi-simple-page">
        <div class="fi-simple-page-content">
            <header class="fi-simple-header">
                <h1 class="fi-simple-header-heading flex">
                    @if(filled($icon))
                        <x-filament::icon :icon=$icon :size=\Filament\Support\Enums\IconSize::ExtraLarge />
                    @endif

                    {{$code}} | {{ $title }}
                </h1>

                <p class="fi-simple-header-subheading">
                    {{ $subtitle instanceof \Closure ? $subtitle() : $subtitle }}
                </p>
            </header>
        </div>
    </div>
</x-filament-panels::layout.simple>