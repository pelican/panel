<?php

namespace App\Contracts\Plugins;

use Filament\Schemas\Components\Component;

interface HasPluginSettings
{
    /**
     * @return array<string, mixed>
     */
    public function getSettingsFormData(): array;

    /**
     * @return Component[]
     */
    public function getSettingsForm(): array;

    /**
     * @param  array<mixed, mixed>  $data
     */
    public function saveSettings(array $data): void;
}
