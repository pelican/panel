<?php

return [
    'title' => 'Schedules',
    'new' => 'New Schedule',
    'edit' => 'Edit Schedule',
    'save' => 'Save Schedule',
    'delete' => 'Delete Schedule',
    'import' => 'Import Schedule',
    'export' => 'Export Schedule',
    'name' => 'Name',
    'cron' => 'Cron',
    'status' => 'Status',
    'schedule_status' => [
        'new' => 'New',
        'inactive' => 'Inactive',
        'processing' => 'Processing',
        'active' => 'Active',
    ],
    'no_tasks' => 'No Tasks',
    'run_now' => 'Run Now',
    'online_only' => 'Only When Online',
    'last_run' => 'Last Run',
    'next_run' => 'Next Run',
    'never' => 'Never',
    'cancel' => 'Cancel',

    'only_online' => 'Only when Server is Online?',
    'only_online_hint' => 'Only execute this schedule when the server is in a running state.',
    'enabled' => 'Enable Schedule?',
    'enabled_hint' => 'This schedule will be executed automatically if enabled.',

    'cron_body' => 'The cron inputs below use your timezone.',
    'cron_timezone' => 'Next run in your timezone (:timezone): <b> :next_run </b>',

    'invalid' => 'Invalid',

    'time' => [
        'minute' => 'Minute',
        'hour' => 'Hour',
        'day' => 'Day',
        'week' => 'Week',
        'month' => 'Month',
        'day_of_month' => 'Day of Month',
        'day_of_week' => 'Day of Week',

        'hourly' => 'Hourly',
        'daily' => 'Daily',
        'weekly_mon' => 'Weekly (Monday)',
        'weekly_sun' => 'Weekly (Sunday)',
        'monthly' => 'Monthly',
        'every_min' => 'Every x minutes',
        'every_hour' => 'Every x hours',
        'every_day' => 'Every x days',
        'every_week' => 'Every x weeks',
        'every_month' => 'Every x months',
        'every_day_of_week' => 'Every x day of week',

        'every' => 'Every',
        'minutes' => 'Minutes',
        'hours' => 'Hours',
        'days' => 'Days',
        'months' => 'Months',

        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ],

    'tasks' => [
        'title' => 'Tasks',
        'create' => 'Create Task',
        'limit' => 'Task Limit Reached',
        'action' => 'Action',
        'payload' => 'Payload',
        'no_payload' => 'No Payload',
        'time_offset' => 'Time Offset',
        'first_task' => 'First Task',
        'seconds' => 'Second|Seconds',
        'continue_on_failure' => 'Continue On Failure',

        'actions' => [
            'title' => 'Action',
            'power' => [
                'title' => 'Send Power Action',
                'action' => 'Power action',
                'start' => 'Start',
                'stop' => 'Stop',
                'restart' => 'Restart',
                'kill' => 'Kill',
            ],
            'command' => [
                'title' => 'Send Command',
                'command' => 'Command',
            ],
            'backup' => [
                'title' => 'Create Backup',
                'files_to_ignore' => 'Files to Ignore',
            ],
            'delete_files' => [
                'title' => 'Delete Files',
                'files_to_delete' => 'Files to Delete',
            ],
        ],
    ],

    'notification_invalid_cron' => 'The cron data provided does not evaluate to a valid expression',

    'import_action' => [
        'file' => 'File',
        'url' => 'URL',
        'schedule_help' => 'This should be the raw .json file ( schedule-daily-restart.json )',
        'url_help' => 'URLs must point directly to the raw .json file',
        'add_url' => 'New URL',
        'import_failed' => 'Import Failed',
        'import_success' => 'Import Success',
    ],
];
