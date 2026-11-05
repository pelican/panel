<?php

use App\Settings\ActivitySettings;
use App\Settings\BackupSettings;
use App\Settings\ClientFeatureSettings;
use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use App\Settings\PluginHubSettings;
use App\Settings\RateLimitSettings;

// Only the keys Pelican changes; everything else falls back to spatie/laravel-settings defaults.
return [
    'settings' => [
        ActivitySettings::class,
        BackupSettings::class,
        ClientFeatureSettings::class,
        GeneralSettings::class,
        MailSettings::class,
        PluginHubSettings::class,
        RateLimitSettings::class,
    ],

    // Settings are seeded by a regular migration in database/migrations.
    'migrations_paths' => [],

    'auto_discover_settings' => [],

    'cache' => [
        'enabled' => (bool) env('SETTINGS_CACHE_ENABLED', true),
        'store' => null,
        'prefix' => null,
        'ttl' => null,
        'memo' => true,
    ],
];
