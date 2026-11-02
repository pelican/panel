<div>
    <div class="flex items-start gap-2 mb-6">
        <div class="flex-1">
            <x-filament::input.wrapper prefix="{{ trans('passkeys.name') }}" :valid="! $errors->has('name')">
                <x-filament::input
                    type="text"
                    wire:model="name"
                    placeholder="{{ trans('passkeys.name') }}"
                />
            </x-filament::input.wrapper>

            @error('name')
                <p class="fi-fo-field-wrp-error-message text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <x-filament::button type="button" x-on:click="window.onClickCreatePasskey()" x-bind:disabled="! $wire.name?.trim()" class="mt-1">
            {{ trans('passkeys.create') }}
        </x-filament::button>
    </div>

    @if ($passkeys->isNotEmpty())
        <div>
            <span class="font-bold text-sm">{{ trans('passkeys.passkeys') }}</span>
            <ul class="space-y-4 mt-4">
                @foreach ($passkeys as $passkey)
                    <x-filament::fieldset>
                        <div class="flex items-center justify-between">
                            <div class="flex flex-col">
                                <span>{{ $passkey->name }}</span>
                                <span class="text-xs fi-sc-text">{{ trans('passkeys.last_used') }}: {{ $passkey->last_used_at?->diffForHumans() ?? trans('passkeys.not_used_yet') }}</span>
                            </div>

                            <x-filament::button 
                                color="danger" 
                                size="sm"
                                wire:click="confirmDelete({{ $passkey->id }})"
                            >
                                {{ trans('passkeys.delete') }}
                            </x-filament::button>
                        </div>
                    </x-filament::fieldset>
                @endforeach
            </ul>
        </div>
    @endif

    <x-filament-actions::modals />
</div>

@include('passkeys.livewire.partials.createScript')
