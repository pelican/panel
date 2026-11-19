<?php

return [

    'name' => env('APP_NAME', 'Pelican'),
    'logo' => env('APP_LOGO'),
    'favicon' => env('APP_FAVICON', '/pelican.ico'),

    'version' => '1.0.0-rc.4',

    'timezone' => 'UTC',

    'installed' => env('APP_INSTALLED', true),

    'exceptions' => [
        'report_all' => env('APP_REPORT_ALL_EXCEPTIONS', false),
    ],

    'fallback_locale' => 'en',

];
