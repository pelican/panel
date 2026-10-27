@php
    $screenshots = array_values($getState() ?? []);
@endphp

<div
    x-data="{
        screenshots: @js($screenshots),
        current: null,
        open(index) { this.current = index },
        close() { this.current = null },
        next() { this.current = (this.current + 1) % this.screenshots.length },
        previous() { this.current = (this.current - 1 + this.screenshots.length) % this.screenshots.length },
    }"
    {{-- Capture Escape first so it closes the lightbox, not the slide-over behind it. --}}
    x-on:keydown.escape.window.capture="if (current !== null) { $event.stopImmediatePropagation(); close() }"
    x-on:keydown.arrow-right.window="current !== null && next()"
    x-on:keydown.arrow-left.window="current !== null && previous()"
    {{-- Keep the wide strip from stretching the slide-over; it scrolls instead. --}}
    class="w-full [contain:inline-size]"
>
    <div class="flex snap-x snap-mandatory gap-3 overflow-x-auto pb-2">
        @foreach ($screenshots as $index => $url)
            <button
                type="button"
                x-on:click="open({{ $index }})"
                class="group relative shrink-0 snap-start overflow-hidden rounded-lg ring-1 ring-gray-950/10 transition hover:ring-2 hover:ring-primary-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:ring-white/10"
            >
                <img
                    src="{{ $url }}"
                    alt=""
                    loading="lazy"
                    class="h-44 w-auto max-w-none object-cover transition group-hover:scale-[1.02]"
                />
            </button>
        @endforeach
    </div>

    <template x-teleport="body">
        <div
            x-show="current !== null"
            x-transition.opacity
            x-on:click.self="close()"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-950/90 p-4 sm:p-10"
            style="display: none"
        >
            <img
                x-bind:src="current !== null ? screenshots[current] : ''"
                alt=""
                class="max-h-full max-w-full rounded-lg object-contain shadow-2xl"
            />

            <button type="button" x-on:click="close()" class="absolute end-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" aria-label="{{ trans('filament::components/modal.actions.close.label') }}">
                <x-filament::icon icon="tabler-x" class="h-6 w-6" />
            </button>

            <template x-if="screenshots.length > 1">
                <div>
                    <button type="button" x-on:click="previous()" class="absolute start-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20">
                        <x-filament::icon icon="tabler-chevron-left" class="h-6 w-6" />
                    </button>
                    <button type="button" x-on:click="next()" class="absolute end-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 p-2 text-white hover:bg-white/20">
                        <x-filament::icon icon="tabler-chevron-right" class="h-6 w-6" />
                    </button>
                    <div class="absolute bottom-4 start-1/2 -translate-x-1/2 rounded-full bg-white/10 px-3 py-1 text-sm text-white" x-text="(current + 1) + ' / ' + screenshots.length"></div>
                </div>
            </template>
        </div>
    </template>
</div>
