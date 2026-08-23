<?php

return [
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

        'enabled' => 'Plugin enabled',
        'disabled' => 'Plugin disabled',
        'deleted' => 'Plugin deleted',

        'imported' => 'Plugin imported',
        'import_exists' => 'A plugin with that id already exists',
        'import_no_manifest' => 'The zip does not contain a valid plugin.json',
        'import_invalid_id' => 'The plugin.json contains an invalid id',
        'import_id_mismatch' => 'The zip is for plugin ":actual", not ":expected"',
        'import_failed' => 'Could not import plugin',
    ],
];
