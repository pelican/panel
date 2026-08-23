<?php

return [
    'nav_title' => 'Webhooks',
    'model_label' => 'Webhook',
    'model_label_plural' => 'Webhooks',
    'endpoint' => 'Endpoint',
    'description' => 'Description',
    'name' => 'Name',
    'type' => 'Type',
    'server' => 'Server',
    'scope' => [
        'global' => 'Global',
        'server' => 'Server',
    ],
    'tabs' => [
        'global' => 'Global Webhooks',
        'server' => 'Server Webhooks',
    ],
    'information' => 'Information',
    'payload' => 'Payload',
    'events' => 'Events',
    'no_webhooks' => 'No Webhooks',
    'help' => 'Help',
    'help_text' => 'You have to wrap variable name in between {{ }} for example if you want to get the name from the api you can use {{name}}.',
    'test_now' => 'Test Now',
    'test_now_help' => 'This will fire a `created: Server` event',
    'table' => [
        'description' => 'Description',
        'endpoint' => 'Endpoint',
    ],
    'headers' => 'Headers',
    'regular' => 'Regular',
    'reset_headers' => 'Reset Headers',
    'unavailable_type' => 'Unavailable Type',
    'unavailable_type_option' => ':type (unavailable)',
    'unavailable_type_text' => 'This webhook uses the ":type" type, which is provided by a plugin that is not installed or enabled. It keeps firing with its saved payload, but cannot be edited until that plugin is available again.',
];
