<?php

return [
    'title' => 'Health',
    'results_refreshed' => 'Health check results updated',
    'checked' => 'Checked results from :time',
    'refresh' => 'Refresh',
    'results' => [
        'cache' => [
            'label' => 'Cache',
            'ok' => 'Ok',
            'failed_retrieve' => 'Could not set or retrieve an application cache value.',
            'failed' => 'An exception occurred with the application cache: :error',
        ],
        'database' => [
            'label' => 'Database',
            'ok' => 'Ok',
            'failed' => 'Could not connect to the database: :error',
        ],
        'debugmode' => [
            'label' => 'Debug Mode',
            'ok' => 'Debug mode is disabled',
            'failed' => 'The debug mode was expected to be :expected, but actually was :actual',
        ],
        'environment' => [
            'label' => 'Environment',
            'ok' => 'Ok, Set to :actual',
            'failed' => 'Environment is set to :actual , Expected :expected',
        ],
        'nodeversions' => [
            'label' => 'Node Versions',
            'ok' => 'Nodes are up-to-date',
            'failed' => ':outdated/:all Nodes are outdated',
            'no_nodes_created' => 'No Nodes created',
            'no_nodes' => 'No Nodes',
            'all_up_to_date' => 'All up-to-date',
            'outdated' => ':outdated/:all outdated',
        ],
        'panelversion' => [
            'label' => 'Panel Version',
            'ok' => 'Panel is up-to-date',
            'failed' => 'Installed version is :currentVersion but latest is :latestVersion',
            'up_to_date' => 'Up to date',
            'outdated' => 'Outdated',
            'unknown' => 'Unknown',
            'unavailable' => 'The latest version could not be fetched from GitHub',
        ],
        'schedule' => [
            'label' => 'Schedule',
            'ok' => 'Ok',
            'failed_last_ran' => 'The last run of the schedule was more than :time minutes ago',
            'failed_not_ran' => 'The schedule did not run yet.',
        ],
        'useddiskspace' => [
            'label' => 'Disk Space',
        ],
    ],
    'checks' => [
        'successful' => 'Successful',
        'failed' => 'Failed :checks',
    ],
];
