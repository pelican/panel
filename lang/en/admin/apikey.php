<?php

return [
    'title' => 'Application API Keys',
    'empty' => 'No API keys',
    'whitelist' => 'Whitelisted IPv4 Addresses',
    'whitelist_help' => 'API keys can be restricted to only work from specific IPv4 addresses. Enter each address on a new line.',
    'whitelist_placeholder' => 'Example: 127.0.0.1 or 192.168.1.1',
    'description' => 'Description',
    'description_help' => 'A brief description of what this key is for.',
    'return_to' => 'After you press Create, this key is sent to :host.',
    'return_failed' => 'The key was created, but it could not be sent to :host. Copy it from the list and paste it there instead.',
    'nav_title' => 'API Keys',
    'model_label' => 'Application API Key',
    'model_label_plural' => 'Application API Keys',
    'table' => [
        'key' => 'Key',
        'description' => 'Description',
        'last_used' => 'Last Used',
        'created' => 'Created',
        'created_by' => 'Created By',
        'never_used' => 'Never Used',
    ],
    'permissions' => [
        'all' => 'Set All Permissions',
        'all_description' => 'Quickly set all permissions below to the same level.',
        'none' => 'None',
        'read' => 'Read',
        'read_write' => 'Read & Write',
    ],
];
