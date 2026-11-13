<?php

namespace App\Contracts\Plugins;

use Closure;
use Filament\Contracts\Plugin;

interface HasTheme extends Plugin
{
    /**
     * @return array<string, array<int|string, string|int>|string>
     */
    public function getThemeColors(): array;

    public function getThemeFont(): ?string;

    /**
     * @return string|array<string>|null
     */
    public function getThemeViteEntry(): string|array|null;

    /**
     * @return array<string, Closure>
     */
    public function getThemeRenderHooks(): array;
}
