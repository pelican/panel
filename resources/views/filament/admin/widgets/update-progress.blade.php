{{--
    Polls a plain JSON endpoint with fetch instead of wire:poll: the update swaps out the code this component runs on,
    so Livewire requests can fail mid-update. Failed polls are ignored and the page reloads once the update is done.
--}}
<x-filament-widgets::widget>
    <div
        x-data="{
            status: @js($status),
            steps: @js($steps),
            states: @js($states),
            timer: null,
            get active() { return ['queued', 'running'].includes(this.status?.state) },
            get headline() {
                const template = this.states[this.status?.state] ?? ''
                return template.replace(':from', this.status?.from ?? '').replace(':to', this.status?.to ?? '')
            },
            stepState(step) {
                const keys = Object.keys(this.steps)
                const current = keys.indexOf(this.status?.step)
                const index = keys.indexOf(step)
                if (this.status?.state === 'succeeded') return 'done'
                if (current === -1) return 'pending'
                if (index < current) return 'done'
                if (index === current) return this.active ? 'running' : 'failed'
                return 'pending'
            },
            async poll() {
                try {
                    const response = await fetch(@js($statusUrl), { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
                    if (!response.ok) return
                    const wasActive = this.active
                    this.status = await response.json()
                    this.$nextTick(() => { if (this.$refs.log) this.$refs.log.scrollTop = this.$refs.log.scrollHeight })
                    if (wasActive && !this.active) {
                        clearInterval(this.timer)
                        setTimeout(() => window.location.reload(), 1500)
                    }
                } catch (e) {
                    // The panel is mid-swap, try again on the next tick.
                }
            },
            init() {
                if (this.active) this.timer = setInterval(() => this.poll(), 2000)
            },
        }"
    >
        <x-filament::section :heading="trans('admin/update.progress.heading')">
            <x-slot name="afterHeader">
                <template x-if="!active && status?.state !== 'rollback_failed'">
                    <x-filament::button color="gray" size="sm" wire:click="dismiss">
                        {{ trans('admin/update.progress.dismiss') }}
                    </x-filament::button>
                </template>
            </x-slot>

            <div class="flex flex-col gap-4">
                <p
                    class="text-sm font-medium"
                    x-text="headline"
                    x-bind:class="{
                        'text-success-600 dark:text-success-400': status?.state === 'succeeded',
                        'text-danger-600 dark:text-danger-400': ['failed', 'rollback_failed'].includes(status?.state),
                    }"
                ></p>

                <template x-if="status?.message">
                    <p class="text-sm text-danger-600 dark:text-danger-400 whitespace-pre-line" x-text="status.message"></p>
                </template>

                <ol class="flex flex-col gap-1 text-sm">
                    <template x-for="(label, step) in steps" x-bind:key="step">
                        <li class="flex items-center gap-2">
                            <span class="inline-flex w-4 justify-center" x-show="stepState(step) === 'done'">
                                <x-filament::icon icon="tabler-circle-check" class="h-4 w-4 text-success-500" />
                            </span>
                            <span class="inline-flex w-4 justify-center" x-show="stepState(step) === 'running'">
                                <x-filament::loading-indicator class="h-4 w-4 text-primary-500" />
                            </span>
                            <span class="inline-flex w-4 justify-center" x-show="stepState(step) === 'failed'">
                                <x-filament::icon icon="tabler-circle-x" class="h-4 w-4 text-danger-500" />
                            </span>
                            <span class="inline-flex w-4 justify-center" x-show="stepState(step) === 'pending'">
                                <x-filament::icon icon="tabler-circle" class="h-4 w-4 text-gray-400" />
                            </span>
                            <span
                                x-text="label"
                                x-bind:class="stepState(step) === 'pending' ? 'text-gray-500 dark:text-gray-400' : ''"
                            ></span>
                        </li>
                    </template>
                </ol>

                <details x-bind:open="status?.state === 'rollback_failed'">
                    <summary class="cursor-pointer text-sm text-gray-500 dark:text-gray-400">{{ trans('admin/update.progress.log') }}</summary>
                    <pre
                        x-ref="log"
                        class="mt-2 max-h-72 overflow-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-200"
                        x-text="(status?.log ?? []).join('\n')"
                    ></pre>
                </details>
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
