<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class PluginHubSettings extends Settings
{
    public string $panel_plugin_hub_url;

    public ?string $panel_plugin_hub_api_key;

    public static function group(): string
    {
        return 'plugin_hub';
    }

    /** @return string[] */
    public static function encrypted(): array
    {
        return ['panel_plugin_hub_api_key'];
    }
}
