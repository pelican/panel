<?php

return [
    'node' => 'Node',
    'tabs_wings' => 'Wings',
    'tabs_panel' => 'Panel',
    'wings_unreachable' => 'This node did not answer. It may be offline, or running a version of Wings without plugin support.',
    'api_version_missing' => '⚠ plugin.json does not declare an api_version, assuming 1',
    'nav_title' => 'Plugins',
    'model_label' => 'Plugin',
    'model_label_plural' => 'Plugins',

    'name' => 'Name',
    'update_available' => 'An update for this plugin is available',
    'author' => 'Author',
    'version' => 'Version',
    'category' => 'Category',
    'status' => 'Status',
    'visit_website' => 'Visit Website',
    'settings' => 'Settings',
    'readme' => 'Readme',
    'install' => 'Install',
    'uninstall' => 'Uninstall',
    'update' => 'Update',
    'enable' => 'Enable',
    'disable' => 'Disable',
    'import_from_file' => 'Import from File',
    'import_from_url' => 'Import from URL',
    'file' => 'File',
    'no_plugins' => 'No Plugins',
    'all' => 'All',
    'change_load_order' => 'Change load order',
    'apply_load_order' => 'Apply load order',
    'crashed_reload' => ':name crashed and has been disabled, reload the page to continue.',
    'safe_mode_active' => 'Safe mode is on, so no plugins are loaded. Unset PANEL_PLUGIN_SAFE_MODE to load them again.',

    'enable_theme_modal' => [
        'heading' => 'Theme already enabled',
        'description' => 'You already have a theme enabled. Enabling multiple themes can result in visual bugs. Do you want to continue?',
    ],

    'status_enum' => [
        'not_installed' => 'Not Installed',
        'disabled' => 'Disabled',
        'enabled' => 'Enabled',
        'errored' => 'Errored',
        'incompatible' => 'Incompatible',
        'crashed' => 'Crashed',
    ],

    'category_enum' => [
        'plugin' => 'Plugin',
        'theme' => 'Theme',
        'language' => 'Language Pack',
    ],

    'notifications' => [
        'goto_plugins' => 'Go to Plugins',
        'background_info' => 'This process can take a few seconds. You will be notified once it\'s finished.',

        'install_started' => 'Plugin install started in the background',
        'installed' => 'Plugin installed',
        'install_error' => 'Could not install plugin',

        'uninstall_started' => 'Plugin uninstall started in the background',
        'uninstalled' => 'Plugin uninstalled',
        'uninstall_error' => 'Could not uninstall plugin',

        'update_started' => 'Plugin update started in the background',
        'updated' => 'Plugin updated',
        'update_error' => 'Could not update plugin',
        'crashed' => 'A plugin crashed and has been disabled',

        'enabled' => 'Plugin enabled',
        'enable_error' => 'Could not enable plugin',
        'disabled' => 'Plugin disabled',
        'disable_error' => 'Could not disable plugin',
        'deleted' => 'Plugin deleted',

        'imported' => 'Plugin imported',
        'import_exists' => 'A plugin with that id already exists',
        'import_no_manifest' => 'The zip does not contain a valid plugin.json',
        'import_invalid_id' => 'The plugin.json contains an invalid id',
        'import_id_mismatch' => 'The zip is for plugin ":actual", not ":expected"',
        'import_checksum_mismatch' => 'The downloaded zip does not match the checksum from the update feed',
        'import_failed' => 'Could not import plugin',
    ],
];
