<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $app_name;

    public ?string $app_logo;

    public string $app_favicon;

    public bool $app_debug;

    public string $filament_avatar_provider;

    public bool $filament_uploadable_avatars;

    public bool $panel_use_binary_prefix;

    public string $filament_default_navigation;

    public int $app_2fa_required;

    public string $filament_width;

    public ?string $trusted_proxies;

    public float $guzzle_timeout;

    public float $guzzle_connect_timeout;

    public static function group(): string
    {
        return 'general';
    }
}
