<?php

return [

    'auth' => [
        '2fa_required' => env('APP_2FA_REQUIRED', 0),
        '2fa' => [
            'bytes' => 32,
            'window' => env('APP_2FA_WINDOW', 4),
            'verify_newer' => true,
        ],
    ],

    'guzzle' => [
        'timeout' => env('GUZZLE_TIMEOUT', 15),
        'connect_timeout' => env('GUZZLE_CONNECT_TIMEOUT', 5),
    ],

    'cdn' => [
        'cache_time' => 60,
        'egg_index_url' => env('PANEL_EGG_INDEX_URL', 'https://raw.githubusercontent.com/pelican-eggs/pelican-eggs.github.io/refs/heads/main/content/pelican.json'),
    ],

    'client_features' => [
        'databases' => [
            'enabled' => env('PANEL_CLIENT_DATABASES_ENABLED', true),
            'allow_random' => env('PANEL_CLIENT_DATABASES_ALLOW_RANDOM', true),
        ],

        'schedules' => [
            // The total number of tasks that can exist for any given schedule at once.
            'per_schedule_task_limit' => env('PANEL_PER_SCHEDULE_TASK_LIMIT', 10),
        ],

        'allocations' => [
            'enabled' => env('PANEL_CLIENT_ALLOCATIONS_ENABLED', false),
            'create_new' => env('PANEL_CLIENT_ALLOCATIONS_CREATE_NEW', true),
            'range_start' => env('PANEL_CLIENT_ALLOCATIONS_RANGE_START'),
            'range_end' => env('PANEL_CLIENT_ALLOCATIONS_RANGE_END'),
        ],
    ],

    'files' => [
        'max_edit_size' => env('PANEL_FILES_MAX_EDIT_SIZE', 1024 * 1024 * 4),
    ],

    'email' => [
        // Should an email be sent to a new user when their account is created?
        'send_account_created_notification' => env('PANEL_SEND_ACCOUNT_CREATED_NOTIFICATION', true),
        // Should an email be sent to a user when they are added as a subuser to a server?
        'send_added_to_server_notification' => env('PANEL_SEND_ADDED_TO_SERVER_NOTIFICATION', true),
        // Should an email be sent to a user when they are removed as a subuser from a server?
        'send_removed_from_server_notification' => env('PANEL_SEND_REMOVED_FROM_SERVER_NOTIFICATION', true),
        // Should an email be sent to a server owner once their server has completed it's first install process?
        'send_install_notification' => env('PANEL_SEND_INSTALL_NOTIFICATION', true),
        // Should an email be sent to a server owner whenever their server is reinstalled?
        'send_reinstall_notification' => env('PANEL_SEND_REINSTALL_NOTIFICATION', true),
    ],

    'filament' => [
        'display-width' => env('FILAMENT_WIDTH', 'screen-2xl'),
        'avatar-provider' => env('FILAMENT_AVATAR_PROVIDER', 'gravatar'),
        'uploadable-avatars' => env('FILAMENT_UPLOADABLE_AVATARS', false),
        'default-navigation' => env('FILAMENT_DEFAULT_NAVIGATION', 'sidebar'),
    ],

    'use_binary_prefix' => env('PANEL_USE_BINARY_PREFIX', true),

    'editable_server_descriptions' => env('PANEL_EDITABLE_SERVER_DESCRIPTIONS', true),

    'api' => [
        'key_limit' => env('API_KEYS_LIMIT', 25),
        'key_expire_time' => env('API_KEYS_EXPIRE_TIME', 720),
    ],

    'webhook' => [
        'prune_days' => env('APP_WEBHOOK_PRUNE_DAYS', 30),

        /*
        | Request timeout in seconds used when delivering a webhook. A webhook type can
        | override this by implementing its own deliver() method.
        */
        'timeout' => env('APP_WEBHOOK_TIMEOUT', 30),
    ],

    'plugin' => [
        'dev_mode' => env('PANEL_PLUGIN_DEV_MODE', false),
        'max_import_size' => env('PANEL_PLUGIN_MAX_IMPORT_SIZE', 1024 * 1024 * 100),
    ],

    'updates' => [
        // Snapshots contain .env and possibly SQLite data, so retain only a small recovery window.
        'retained_snapshots' => env('PANEL_UPDATE_RETAINED_SNAPSHOTS', 3),
    ],
];
