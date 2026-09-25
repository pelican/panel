<form method="POST" action="{{ filament()->getCurrentPanel()->route('theme') }}" class="fi-dropdown-list">
    @csrf

    <x-filament::input.wrapper>
        <x-filament::input.select name="theme" :aria-label="trans('profile.theme')" x-on:change="$el.form.submit()">
            @foreach ($themes as $themeId => $name)
                <option value="{{ $themeId }}" @selected($themeId === $selected)>{{ $name }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</form>
